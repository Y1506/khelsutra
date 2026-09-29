<?php

namespace App\Services\Purchase;

use App\Services\BaseService;
use App\Services\Audit\AuditLogService;
use PDO;
use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * Service handling the full Procurement & Purchase lifecycle:
 * 1. Purchase Requests (Creation, Itemization, Submission, Approval, Rejection, Conversion)
 * 2. Purchase Orders (Standalone or PR-driven, Vendor Assignment, Pricing/Taxes, State machine)
 * 3. Goods Receipts (Receipt generation, item quantity verification, PO fulfillment)
 * 4. Inventory Integration (Atomic stock level increments & stock_transactions recording)
 * 5. Procurement History & Tenant Isolation
 */
class PurchaseService extends BaseService
{
    protected ?AuditLogService $auditLogService;

    public function __construct(?AuditLogService $auditLogService = null)
    {
        parent::__construct();
        $this->auditLogService = $auditLogService ?? new AuditLogService();
    }

    /**
     * Resolve a valid user ID for audit logging.
     */
    protected function resolveAuditUserId(?int $userId, int $organizationId): ?int
    {
        if (!$this->pdo) return null;
        if ($userId) {
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $userId]);
            if ($stmt->fetchColumn()) {
                return $userId;
            }
        }

        $stmt = $this->pdo->prepare("
            SELECT u.id 
            FROM users u
            JOIN organization_users ou ON u.id = ou.user_id
            WHERE ou.organization_id = :org_id AND ou.access_status = 'active'
            ORDER BY u.id ASC 
            LIMIT 1
        ");
        $stmt->execute([':org_id' => $organizationId]);
        $found = $stmt->fetchColumn();
        return $found ? (int)$found : null;
    }

    // ==========================================
    // SECTION 1: REFERENCE NUMBER GENERATORS
    // ==========================================

    /**
     * Generate unique Purchase Request reference: PR-YYYYMMDD-XXXX
     */
    public function generateRequestReference(int $organizationId): string
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $datePrefix = date('Ymd');
        $stmt = $this->pdo->prepare("
            SELECT request_reference 
            FROM purchase_requests 
            WHERE organization_id = :org_id AND request_reference LIKE :prefix
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([
            ':org_id' => $organizationId,
            ':prefix' => "PR-{$datePrefix}-%",
        ]);
        $lastRef = $stmt->fetchColumn();

        if ($lastRef && preg_match('/-(\d+)$/', $lastRef, $matches)) {
            $seq = (int)$matches[1] + 1;
        } else {
            $seq = mt_rand(1000, 9999);
        }

        return sprintf("PR-%s-%04d", $datePrefix, $seq);
    }

    /**
     * Generate unique Purchase Order number: PO-YYYYMMDD-XXXX
     */
    public function generatePoNumber(int $organizationId): string
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $datePrefix = date('Ymd');
        $stmt = $this->pdo->prepare("
            SELECT po_number 
            FROM purchase_orders 
            WHERE organization_id = :org_id AND po_number LIKE :prefix
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([
            ':org_id' => $organizationId,
            ':prefix' => "PO-{$datePrefix}-%",
        ]);
        $lastPo = $stmt->fetchColumn();

        if ($lastPo && preg_match('/-(\d+)$/', $lastPo, $matches)) {
            $seq = (int)$matches[1] + 1;
        } else {
            $seq = mt_rand(1000, 9999);
        }

        return sprintf("PO-%s-%04d", $datePrefix, $seq);
    }

    /**
     * Generate unique Goods Receipt number: GRN-YYYYMMDD-XXXX
     */
    public function generateReceiptNumber(int $organizationId): string
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $datePrefix = date('Ymd');
        $stmt = $this->pdo->prepare("
            SELECT receipt_number 
            FROM goods_receipts 
            WHERE organization_id = :org_id AND receipt_number LIKE :prefix
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([
            ':org_id' => $organizationId,
            ':prefix' => "GRN-{$datePrefix}-%",
        ]);
        $lastGrn = $stmt->fetchColumn();

        if ($lastGrn && preg_match('/-(\d+)$/', $lastGrn, $matches)) {
            $seq = (int)$matches[1] + 1;
        } else {
            $seq = mt_rand(1000, 9999);
        }

        return sprintf("GRN-%s-%04d", $datePrefix, $seq);
    }

    // ==========================================
    // SECTION 2: PURCHASE REQUESTS
    // ==========================================

    /**
     * List purchase requests with filtering and pagination.
     */
    public function listPurchaseRequests(
        int $organizationId,
        int $page = 1,
        int $perPage = 15,
        ?string $search = null,
        ?string $status = null
    ): array {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = ["pr.organization_id = :org_id"];
        $params = [':org_id' => $organizationId];

        if ($search) {
            $conditions[] = "(pr.request_reference LIKE :search OR pr.purpose LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
            $params[':search'] = '%' . trim($search) . '%';
        }

        if ($status && in_array($status, ['draft', 'submitted', 'approved', 'rejected', 'converted', 'cancelled'], true)) {
            $conditions[] = "pr.status = :status";
            $params[':status'] = $status;
        }

        $whereClause = implode(' AND ', $conditions);

        // Count total
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM purchase_requests pr
            LEFT JOIN users u ON pr.requested_by = u.id
            WHERE {$whereClause}
        ");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Fetch records
        $stmt = $this->pdo->prepare("
            SELECT pr.*, 
                   COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Staff Member') as requester_name,
                   COALESCE(CONCAT(ap.first_name, ' ', ap.last_name), '') as approver_name,
                   (SELECT COUNT(*) FROM purchase_request_items pri WHERE pri.purchase_request_id = pr.id) as item_count,
                   (SELECT COALESCE(SUM(pri.estimated_total), 0) FROM purchase_request_items pri WHERE pri.purchase_request_id = pr.id) as total_estimated_cost
            FROM purchase_requests pr
            LEFT JOIN users u ON pr.requested_by = u.id
            LEFT JOIN users ap ON pr.approved_by = ap.id
            WHERE {$whereClause}
            ORDER BY pr.request_date DESC, pr.id DESC
            LIMIT :offset, :per_page
        ");

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $perPage,
            'total_pages' => $total > 0 ? (int)ceil($total / $perPage) : 1,
        ];
    }

    /**
     * Get a single purchase request by ID with line items and requester/approver details.
     */
    public function getPurchaseRequest(int $organizationId, int $id): ?array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $stmt = $this->pdo->prepare("
            SELECT pr.*, 
                   COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Staff Member') as requester_name,
                   COALESCE(CONCAT(ap.first_name, ' ', ap.last_name), '') as approver_name
            FROM purchase_requests pr
            LEFT JOIN users u ON pr.requested_by = u.id
            LEFT JOIN users ap ON pr.approved_by = ap.id
            WHERE pr.id = :id AND pr.organization_id = :org_id
        ");
        $stmt->execute([':id' => $id, ':org_id' => $organizationId]);
        $pr = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pr) {
            return null;
        }

        // Fetch line items
        $itemStmt = $this->pdo->prepare("
            SELECT pri.*, 
                   ii.item_code, ii.item_name as stock_item_name, ii.quantity as in_stock_quantity
            FROM purchase_request_items pri
            LEFT JOIN inventory_items ii ON pri.inventory_item_id = ii.id
            WHERE pri.purchase_request_id = :pr_id
            ORDER BY pri.id ASC
        ");
        $itemStmt->execute([':pr_id' => $id]);
        $pr['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totalEstimatedCost = 0.0;
        foreach ($pr['items'] as $item) {
            $totalEstimatedCost += (float)($item['estimated_total'] ?? 0);
        }
        $pr['total_estimated_cost'] = round($totalEstimatedCost, 2);
        $pr['item_count'] = count($pr['items']);

        return $pr;
    }

    /**
     * Create a new purchase request with line items inside a transaction.
     */
    public function createPurchaseRequest(int $organizationId, array $data, ?int $userId = null): array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $requestedBy = $this->resolveAuditUserId($userId, $organizationId);
        $requestDate = !empty($data['request_date']) ? trim($data['request_date']) : date('Y-m-d');
        $requiredDate = !empty($data['required_date']) ? trim($data['required_date']) : null;
        $purpose = !empty($data['purpose']) ? trim($data['purpose']) : null;
        $departmentId = !empty($data['department_id']) ? (int)$data['department_id'] : null;

        $items = $data['items'] ?? [];
        if (empty($items) || !is_array($items)) {
            throw new InvalidArgumentException("Purchase request must contain at least one line item.");
        }

        $ref = !empty($data['request_reference']) ? trim($data['request_reference']) : $this->generateRequestReference($organizationId);

        $this->pdo->beginTransaction();
        try {
            // Check reference uniqueness within organization
            $refCheck = $this->pdo->prepare("SELECT id FROM purchase_requests WHERE organization_id = :org_id AND request_reference = :ref");
            $refCheck->execute([':org_id' => $organizationId, ':ref' => $ref]);
            if ($refCheck->fetch()) {
                throw new InvalidArgumentException("A purchase request with reference '{$ref}' already exists in this organization.");
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO purchase_requests (
                    organization_id, request_reference, requested_by, department_id,
                    request_date, required_date, purpose, status, created_at, updated_at
                ) VALUES (
                    :org_id, :ref, :requested_by, :dept_id,
                    :request_date, :required_date, :purpose, 'draft', NOW(), NOW()
                )
            ");
            $stmt->execute([
                ':org_id' => $organizationId,
                ':ref' => $ref,
                ':requested_by' => $requestedBy,
                ':dept_id' => $departmentId,
                ':request_date' => $requestDate,
                ':required_date' => $requiredDate,
                ':purpose' => $purpose,
            ]);
            $prId = (int)$this->pdo->lastInsertId();

            // Insert line items
            $itemStmt = $this->pdo->prepare("
                INSERT INTO purchase_request_items (
                    purchase_request_id, inventory_item_id, item_name, description,
                    quantity, estimated_unit_cost, estimated_total
                ) VALUES (
                    :pr_id, :inv_id, :item_name, :description,
                    :quantity, :unit_cost, :total
                )
            ");

            $validItemsCount = 0;
            foreach ($items as $item) {
                $itemName = !empty($item['item_name']) ? trim($item['item_name']) : '';
                $invItemId = !empty($item['inventory_item_id']) ? (int)$item['inventory_item_id'] : null;

                // Auto-fill item name from inventory if linked
                if ($invItemId && empty($itemName)) {
                    $invRow = $this->pdo->prepare("SELECT item_name FROM inventory_items WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL");
                    $invRow->execute([':id' => $invItemId, ':org_id' => $organizationId]);
                    $itemName = (string)$invRow->fetchColumn();
                }

                if (empty($itemName)) {
                    continue; // Skip invalid or blank item rows
                }

                $qty = isset($item['quantity']) ? (float)$item['quantity'] : 0.0;
                if ($qty <= 0) {
                    throw new InvalidArgumentException("Quantity for item '{$itemName}' must be greater than 0.");
                }

                $unitCost = isset($item['estimated_unit_cost']) ? max(0, (float)$item['estimated_unit_cost']) : 0.0;
                $estimatedTotal = round($qty * $unitCost, 2);

                $itemStmt->execute([
                    ':pr_id' => $prId,
                    ':inv_id' => $invItemId,
                    ':item_name' => $itemName,
                    ':description' => !empty($item['description']) ? trim($item['description']) : null,
                    ':quantity' => $qty,
                    ':unit_cost' => $unitCost,
                    ':total' => $estimatedTotal,
                ]);
                $validItemsCount++;
            }

            if ($validItemsCount === 0) {
                throw new InvalidArgumentException("At least one valid item name with quantity > 0 must be provided.");
            }

            $this->pdo->commit();

            if ($this->auditLogService && $requestedBy) {
                try {
                    $this->auditLogService->log(
                        $organizationId,
                        $requestedBy,
                        'create_purchase_request',
                        'purchase_request',
                        $prId,
                        null,
                        ['reference' => $ref, 'item_count' => $validItemsCount]
                    );
                } catch (\Throwable $e) {}
            }

            return $this->getPurchaseRequest($organizationId, $prId);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Submit purchase request for administrative approval.
     */
    public function submitPurchaseRequest(int $organizationId, int $id, ?int $userId = null): array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $pr = $this->getPurchaseRequest($organizationId, $id);
        if (!$pr) {
            throw new InvalidArgumentException("Purchase request not found or access denied.");
        }

        if (!in_array($pr['status'], ['draft', 'rejected'], true)) {
            throw new InvalidArgumentException("Only draft or rejected purchase requests can be submitted. Current status: {$pr['status']}.");
        }

        if (empty($pr['items'])) {
            throw new InvalidArgumentException("Cannot submit a purchase request with no line items.");
        }

        $stmt = $this->pdo->prepare("UPDATE purchase_requests SET status = 'submitted', rejection_reason = NULL, updated_at = NOW() WHERE id = :id AND organization_id = :org_id");
        $stmt->execute([':id' => $id, ':org_id' => $organizationId]);

        $auditUser = $this->resolveAuditUserId($userId, $organizationId);
        if ($this->auditLogService && $auditUser) {
            try {
                $this->auditLogService->log($organizationId, $auditUser, 'submit', 'purchase_request', $id, ['status' => 'draft'], ['status' => 'submitted']);
            } catch (\Throwable $e) {}
        }

        return $this->getPurchaseRequest($organizationId, $id);
    }

    /**
     * Approve purchase request.
     */
    public function approvePurchaseRequest(int $organizationId, int $id, ?int $userId = null): array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $pr = $this->getPurchaseRequest($organizationId, $id);
        if (!$pr) {
            throw new InvalidArgumentException("Purchase request not found or access denied.");
        }

        if ($pr['status'] !== 'submitted') {
            throw new InvalidArgumentException("Only submitted purchase requests can be approved. Current status: {$pr['status']}.");
        }

        $approverId = $this->resolveAuditUserId($userId, $organizationId);

        $stmt = $this->pdo->prepare("
            UPDATE purchase_requests 
            SET status = 'approved', approved_by = :approved_by, approved_at = NOW(), rejection_reason = NULL, updated_at = NOW() 
            WHERE id = :id AND organization_id = :org_id
        ");
        $stmt->execute([':approved_by' => $approverId, ':id' => $id, ':org_id' => $organizationId]);

        if ($this->auditLogService && $approverId) {
            try {
                $this->auditLogService->log($organizationId, $approverId, 'approve', 'purchase_request', $id, ['status' => 'submitted'], ['status' => 'approved']);
            } catch (\Throwable $e) {}
        }

        return $this->getPurchaseRequest($organizationId, $id);
    }

    /**
     * Reject purchase request with justification.
     */
    public function rejectPurchaseRequest(int $organizationId, int $id, ?string $reason = null, ?int $userId = null): array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $pr = $this->getPurchaseRequest($organizationId, $id);
        if (!$pr) {
            throw new InvalidArgumentException("Purchase request not found or access denied.");
        }

        if ($pr['status'] !== 'submitted') {
            throw new InvalidArgumentException("Only submitted purchase requests can be rejected. Current status: {$pr['status']}.");
        }

        $approverId = $this->resolveAuditUserId($userId, $organizationId);

        $stmt = $this->pdo->prepare("
            UPDATE purchase_requests 
            SET status = 'rejected', approved_by = :approved_by, approved_at = NOW(), rejection_reason = :reason, updated_at = NOW() 
            WHERE id = :id AND organization_id = :org_id
        ");
        $stmt->execute([
            ':approved_by' => $approverId,
            ':reason' => $reason ? trim($reason) : 'Rejected by administrator',
            ':id' => $id,
            ':org_id' => $organizationId
        ]);

        if ($this->auditLogService && $approverId) {
            try {
                $this->auditLogService->log($organizationId, $approverId, 'reject', 'purchase_request', $id, ['status' => 'submitted'], ['status' => 'rejected', 'reason' => $reason]);
            } catch (\Throwable $e) {}
        }

        return $this->getPurchaseRequest($organizationId, $id);
    }

    /**
     * Cancel purchase request (allowed from draft or submitted).
     */
    public function cancelPurchaseRequest(int $organizationId, int $id, ?int $userId = null): array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $pr = $this->getPurchaseRequest($organizationId, $id);
        if (!$pr) {
            throw new InvalidArgumentException("Purchase request not found or access denied.");
        }

        if (!in_array($pr['status'], ['draft', 'submitted'], true)) {
            throw new InvalidArgumentException("Only draft or submitted purchase requests can be cancelled. Current status: {$pr['status']}.");
        }

        $stmt = $this->pdo->prepare("UPDATE purchase_requests SET status = 'cancelled', updated_at = NOW() WHERE id = :id AND organization_id = :org_id");
        $stmt->execute([':id' => $id, ':org_id' => $organizationId]);

        return $this->getPurchaseRequest($organizationId, $id);
    }

    // ==========================================
    // SECTION 3: PURCHASE ORDERS
    // ==========================================

    /**
     * List purchase orders with filtering and pagination.
     */
    public function listPurchaseOrders(
        int $organizationId,
        int $page = 1,
        int $perPage = 15,
        ?string $search = null,
        ?string $status = null,
        ?int $vendorId = null
    ): array {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = ["po.organization_id = :org_id"];
        $params = [':org_id' => $organizationId];

        if ($search) {
            $conditions[] = "(po.po_number LIKE :search OR v.company_name LIKE :search OR po.notes LIKE :search)";
            $params[':search'] = '%' . trim($search) . '%';
        }

        if ($status && in_array($status, ['draft', 'sent', 'confirmed', 'partially_received', 'received', 'cancelled'], true)) {
            $conditions[] = "po.status = :status";
            $params[':status'] = $status;
        }

        if ($vendorId) {
            $conditions[] = "po.vendor_id = :vendor_id";
            $params[':vendor_id'] = $vendorId;
        }

        $whereClause = implode(' AND ', $conditions);

        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM purchase_orders po
            LEFT JOIN vendors v ON po.vendor_id = v.id
            WHERE {$whereClause}
        ");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT po.*, 
                   v.company_name as vendor_name,
                   v.vendor_code,
                   v.contact_person as vendor_contact,
                   pr.request_reference,
                   (SELECT COUNT(*) FROM purchase_order_items poi WHERE poi.purchase_order_id = po.id) as item_count,
                   (SELECT COUNT(*) FROM goods_receipts gr WHERE gr.purchase_order_id = po.id) as receipt_count
            FROM purchase_orders po
            LEFT JOIN vendors v ON po.vendor_id = v.id
            LEFT JOIN purchase_requests pr ON po.purchase_request_id = pr.id
            WHERE {$whereClause}
            ORDER BY po.order_date DESC, po.id DESC
            LIMIT :offset, :per_page
        ");

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $perPage,
            'total_pages' => $total > 0 ? (int)ceil($total / $perPage) : 1,
        ];
    }

    /**
     * Get a single purchase order by ID with line items, vendor details, receipts, and invoices.
     */
    public function getPurchaseOrder(int $organizationId, int|string $id): ?array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $stmt = $this->pdo->prepare("
            SELECT po.*, 
                   v.company_name as vendor_name,
                   v.vendor_code,
                   v.contact_person as vendor_contact,
                   v.phone as vendor_phone,
                   v.email as vendor_email,
                   v.gst_number as vendor_gst,
                   pr.request_reference
            FROM purchase_orders po
            LEFT JOIN vendors v ON po.vendor_id = v.id
            LEFT JOIN purchase_requests pr ON po.purchase_request_id = pr.id
            WHERE (po.id = :id OR po.po_number = :po_num) AND po.organization_id = :org_id
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => is_numeric($id) ? (int)$id : 0,
            ':po_num' => (string)$id,
            ':org_id' => $organizationId,
        ]);
        $po = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$po) {
            return null;
        }

        $poId = (int)$po['id'];

        // Line items with inventory item codes
        $itemStmt = $this->pdo->prepare("
            SELECT poi.*, 
                   ii.item_code, ii.item_name as stock_item_name, ii.quantity as current_stock
            FROM purchase_order_items poi
            LEFT JOIN inventory_items ii ON poi.inventory_item_id = ii.id
            WHERE poi.purchase_order_id = :po_id
            ORDER BY poi.id ASC
        ");
        $itemStmt->execute([':po_id' => $poId]);
        $po['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Linked Goods Receipts
        $grStmt = $this->pdo->prepare("
            SELECT gr.*, COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Staff') as received_by_name
            FROM goods_receipts gr
            LEFT JOIN users u ON gr.received_by = u.id
            WHERE gr.purchase_order_id = :po_id AND gr.organization_id = :org_id
            ORDER BY gr.receipt_date DESC, gr.id DESC
        ");
        $grStmt->execute([':po_id' => $poId, ':org_id' => $organizationId]);
        $po['receipts'] = $grStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Linked Vendor Invoices
        $invStmt = $this->pdo->prepare("
            SELECT vi.* 
            FROM vendor_invoices vi
            WHERE vi.purchase_order_id = :po_id AND vi.organization_id = :org_id
            ORDER BY vi.invoice_date DESC, vi.id DESC
        ");
        $invStmt->execute([':po_id' => $poId, ':org_id' => $organizationId]);
        $po['invoices'] = $invStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $po;
    }

    /**
     * Create a new purchase order (standalone or from an approved purchase request).
     */
    public function createPurchaseOrder(int $organizationId, array $data, ?int $userId = null): array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $vendorId = !empty($data['vendor_id']) ? (int)$data['vendor_id'] : 0;
        if ($vendorId <= 0) {
            throw new InvalidArgumentException("A valid vendor must be selected for the purchase order.");
        }

        // Verify vendor belongs to this organization and is not blacklisted
        $vStmt = $this->pdo->prepare("SELECT id, company_name, status FROM vendors WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL");
        $vStmt->execute([':id' => $vendorId, ':org_id' => $organizationId]);
        $vendor = $vStmt->fetch(PDO::FETCH_ASSOC);
        if (!$vendor) {
            throw new InvalidArgumentException("Vendor not found or access denied.");
        }
        if ($vendor['status'] === 'blacklisted') {
            throw new InvalidArgumentException("Cannot issue purchase order to blacklisted vendor '{$vendor['company_name']}'.");
        }

        // Check if created from Purchase Request
        $prId = !empty($data['purchase_request_id']) ? (int)$data['purchase_request_id'] : null;
        if ($prId) {
            $pr = $this->getPurchaseRequest($organizationId, $prId);
            if (!$pr) {
                throw new InvalidArgumentException("The linked purchase request does not exist or access was denied.");
            }
            if ($pr['status'] !== 'approved') {
                throw new InvalidArgumentException("A purchase order can only be created from an approved purchase request. Current request status: {$pr['status']}.");
            }
        }

        $items = $data['items'] ?? [];
        // If items are not passed explicitly but PR is linked, copy items from PR
        if (empty($items) && $prId && !empty($pr['items'])) {
            foreach ($pr['items'] as $pri) {
                $items[] = [
                    'inventory_item_id' => $pri['inventory_item_id'],
                    'item_name' => $pri['item_name'],
                    'description' => $pri['description'],
                    'ordered_quantity' => $pri['quantity'],
                    'unit_cost' => $pri['estimated_unit_cost'],
                    'tax_amount' => 0.0,
                    'discount_amount' => 0.0,
                ];
            }
        }

        if (empty($items) || !is_array($items)) {
            throw new InvalidArgumentException("Purchase order must contain at least one line item.");
        }

        $poNumber = !empty($data['po_number']) ? trim($data['po_number']) : $this->generatePoNumber($organizationId);
        $orderDate = !empty($data['order_date']) ? trim($data['order_date']) : date('Y-m-d');
        $expectedDeliveryDate = !empty($data['expected_delivery_date']) ? trim($data['expected_delivery_date']) : null;
        $notes = !empty($data['notes']) ? trim($data['notes']) : null;
        $initialStatus = !empty($data['status']) && in_array($data['status'], ['draft', 'sent', 'confirmed'], true) ? $data['status'] : 'draft';

        $this->pdo->beginTransaction();
        try {
            // Uniqueness check for po_number within org
            $poCheck = $this->pdo->prepare("SELECT id FROM purchase_orders WHERE organization_id = :org_id AND po_number = :po_num");
            $poCheck->execute([':org_id' => $organizationId, ':po_num' => $poNumber]);
            if ($poCheck->fetch()) {
                throw new InvalidArgumentException("A purchase order with number '{$poNumber}' already exists in this organization.");
            }

            // Insert initial PO header
            $stmt = $this->pdo->prepare("
                INSERT INTO purchase_orders (
                    organization_id, purchase_request_id, vendor_id, po_number,
                    order_date, expected_delivery_date, subtotal, tax_amount,
                    discount_amount, total_amount, status, notes, created_at, updated_at
                ) VALUES (
                    :org_id, :pr_id, :vendor_id, :po_num,
                    :order_date, :expected_delivery_date, 0, 0,
                    0, 0, :status, :notes, NOW(), NOW()
                )
            ");
            $stmt->execute([
                ':org_id' => $organizationId,
                ':pr_id' => $prId,
                ':vendor_id' => $vendorId,
                ':po_num' => $poNumber,
                ':order_date' => $orderDate,
                ':expected_delivery_date' => $expectedDeliveryDate,
                ':status' => $initialStatus,
                ':notes' => $notes,
            ]);
            $poId = (int)$this->pdo->lastInsertId();

            // Insert items and calculate totals
            $itemStmt = $this->pdo->prepare("
                INSERT INTO purchase_order_items (
                    purchase_order_id, inventory_item_id, item_name, description,
                    ordered_quantity, received_quantity, unit_cost, tax_amount,
                    discount_amount, total_amount
                ) VALUES (
                    :po_id, :inv_id, :item_name, :description,
                    :ordered_qty, 0, :unit_cost, :tax_amt,
                    :discount_amt, :total_amt
                )
            ");

            $subtotal = 0.0;
            $taxAmount = 0.0;
            $discountAmount = 0.0;
            $validItemsCount = 0;

            foreach ($items as $item) {
                $itemName = !empty($item['item_name']) ? trim($item['item_name']) : '';
                $invItemId = !empty($item['inventory_item_id']) ? (int)$item['inventory_item_id'] : null;

                if ($invItemId && empty($itemName)) {
                    $invRow = $this->pdo->prepare("SELECT item_name FROM inventory_items WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL");
                    $invRow->execute([':id' => $invItemId, ':org_id' => $organizationId]);
                    $itemName = (string)$invRow->fetchColumn();
                }

                if (empty($itemName)) {
                    continue;
                }

                $qty = isset($item['ordered_quantity']) ? (float)$item['ordered_quantity'] : (isset($item['quantity']) ? (float)$item['quantity'] : 0.0);
                if ($qty <= 0) {
                    throw new InvalidArgumentException("Ordered quantity for item '{$itemName}' must be greater than 0.");
                }

                $unitCost = isset($item['unit_cost']) ? max(0, (float)$item['unit_cost']) : (isset($item['unit_price']) ? max(0, (float)$item['unit_price']) : 0.0);
                $itemSubtotal = round($qty * $unitCost, 2);

                if (isset($item['tax_amount'])) {
                    $itemTax = max(0, (float)$item['tax_amount']);
                } elseif (isset($item['tax_percent'])) {
                    $itemTax = round($itemSubtotal * ((float)$item['tax_percent'] / 100.0), 2);
                } else {
                    $itemTax = 0.0;
                }

                if (isset($item['discount_amount'])) {
                    $itemDiscount = max(0, (float)$item['discount_amount']);
                } elseif (isset($item['discount_percent'])) {
                    $itemDiscount = round($itemSubtotal * ((float)$item['discount_percent'] / 100.0), 2);
                } else {
                    $itemDiscount = 0.0;
                }

                $itemTotal = max(0.0, round($itemSubtotal + $itemTax - $itemDiscount, 2));

                $itemStmt->execute([
                    ':po_id' => $poId,
                    ':inv_id' => $invItemId,
                    ':item_name' => $itemName,
                    ':description' => !empty($item['description']) ? trim($item['description']) : null,
                    ':ordered_qty' => $qty,
                    ':unit_cost' => $unitCost,
                    ':tax_amt' => $itemTax,
                    ':discount_amt' => $itemDiscount,
                    ':total_amt' => $itemTotal,
                ]);

                $subtotal += $itemSubtotal;
                $taxAmount += $itemTax;
                $discountAmount += $itemDiscount;
                $validItemsCount++;
            }

            if ($validItemsCount === 0) {
                throw new InvalidArgumentException("At least one valid item name with ordered quantity > 0 must be provided.");
            }

            $totalAmount = max(0.0, round($subtotal + $taxAmount - $discountAmount, 2));

            // Update header totals
            $updPo = $this->pdo->prepare("
                UPDATE purchase_orders 
                SET subtotal = :subtotal, tax_amount = :tax, discount_amount = :disc, total_amount = :total 
                WHERE id = :id
            ");
            $updPo->execute([
                ':subtotal' => $subtotal,
                ':tax' => $taxAmount,
                ':disc' => $discountAmount,
                ':total' => $totalAmount,
                ':id' => $poId,
            ]);

            // If created from PR, mark PR as 'converted'
            if ($prId) {
                $updPr = $this->pdo->prepare("UPDATE purchase_requests SET status = 'converted', updated_at = NOW() WHERE id = :pr_id");
                $updPr->execute([':pr_id' => $prId]);
            }

            $this->pdo->commit();

            $auditUser = $this->resolveAuditUserId($userId, $organizationId);
            if ($this->auditLogService && $auditUser) {
                try {
                    $this->auditLogService->log(
                        $organizationId,
                        $auditUser,
                        'create_purchase_order',
                        'purchase_order',
                        $poId,
                        null,
                        ['po_number' => $poNumber, 'vendor_id' => $vendorId, 'total_amount' => $totalAmount]
                    );
                } catch (\Throwable $e) {}
            }

            return $this->getPurchaseOrder($organizationId, $poId);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Transition Purchase Order status ('draft' -> 'sent' -> 'confirmed' or 'cancelled').
     */
    public function updatePoStatus(int $organizationId, int $id, string $newStatus, ?int $userId = null): array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $po = $this->getPurchaseOrder($organizationId, $id);
        if (!$po) {
            throw new InvalidArgumentException("Purchase order not found or access denied.");
        }

        $validTransitions = [
            'draft' => ['sent', 'confirmed', 'cancelled'],
            'sent' => ['confirmed', 'cancelled'],
            'confirmed' => ['cancelled'],
        ];

        $allowedNext = $validTransitions[$po['status']] ?? [];
        if (!in_array($newStatus, $allowedNext, true)) {
            throw new InvalidArgumentException("Invalid status transition from '{$po['status']}' to '{$newStatus}'.");
        }

        // If cancelling, verify that no goods have been received
        if ($newStatus === 'cancelled') {
            foreach ($po['items'] as $item) {
                if ((float)$item['received_quantity'] > 0) {
                    throw new InvalidArgumentException("Cannot cancel purchase order: Goods have already been partially received.");
                }
            }
        }

        $stmt = $this->pdo->prepare("UPDATE purchase_orders SET status = :status, updated_at = NOW() WHERE id = :id AND organization_id = :org_id");
        $stmt->execute([':status' => $newStatus, ':id' => $id, ':org_id' => $organizationId]);

        $auditUser = $this->resolveAuditUserId($userId, $organizationId);
        if ($this->auditLogService && $auditUser) {
            try {
                $this->auditLogService->log($organizationId, $auditUser, 'update_status', 'purchase_order', $id, ['status' => $po['status']], ['status' => $newStatus]);
            } catch (\Throwable $e) {}
        }

        return $this->getPurchaseOrder($organizationId, $id);
    }

    // ==========================================
    // SECTION 4: GOODS RECEIPT & INVENTORY INTEGRATION
    // ==========================================

    /**
     * Process Goods Receipt against a purchase order.
     * Atomically:
     * - Records the Goods Receipt in goods_receipts
     * - Updates received_quantity on purchase_order_items
     * - Transitions purchase_orders status to 'partially_received' or 'received'
     * - Increments current_quantity on linked inventory_items
     * - Creates stock_transactions record of type 'purchase'
     *
     * @param int $organizationId
     * @param int $poId
     * @param array $receiptData ['receipt_date', 'remarks', 'items' => [itemId => receivedQty]]
     * @param int|null $userId
     * @return array
     */
    public function receiveGoods(int $organizationId, int $poId, array $receiptData, ?int $userId = null): array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $po = $this->getPurchaseOrder($organizationId, $poId);
        if (!$po) {
            throw new InvalidArgumentException("Purchase order not found or access denied.");
        }

        // Must be in sent, confirmed, or partially_received status
        if (!in_array($po['status'], ['sent', 'confirmed', 'partially_received'], true)) {
            throw new InvalidArgumentException("Cannot receive goods for purchase order with status '{$po['status']}'. Must be sent, confirmed, or partially received.");
        }

        $receivedItems = $receiptData['items'] ?? [];
        if (empty($receivedItems) || !is_array($receivedItems)) {
            throw new InvalidArgumentException("No received quantities specified for items.");
        }

        $receiptNumber = !empty($receiptData['receipt_number']) ? trim($receiptData['receipt_number']) : $this->generateReceiptNumber($organizationId);
        $receiptDate = !empty($receiptData['receipt_date']) ? trim($receiptData['receipt_date']) : date('Y-m-d');
        $receiverId = $this->resolveAuditUserId($userId, $organizationId);
        $remarks = !empty($receiptData['remarks']) ? trim($receiptData['remarks']) : null;

        $this->pdo->beginTransaction();
        try {
            // Insert Goods Receipt header
            $grStmt = $this->pdo->prepare("
                INSERT INTO goods_receipts (
                    organization_id, purchase_order_id, receipt_number, receipt_date,
                    received_by, remarks, created_at
                ) VALUES (
                    :org_id, :po_id, :receipt_num, :receipt_date,
                    :received_by, :remarks, NOW()
                )
            ");
            $grStmt->execute([
                ':org_id' => $organizationId,
                ':po_id' => $poId,
                ':receipt_num' => $receiptNumber,
                ':receipt_date' => $receiptDate,
                ':received_by' => $receiverId,
                ':remarks' => $remarks,
            ]);
            $receiptId = (int)$this->pdo->lastInsertId();

            // Map PO items by item id
            $poItemsMap = [];
            foreach ($po['items'] as $poi) {
                $poItemsMap[(int)$poi['id']] = $poi;
            }

            $totalItemsReceived = 0;
            $poItemUpdStmt = $this->pdo->prepare("UPDATE purchase_order_items SET received_quantity = :rec_qty WHERE id = :id");
            $invUpdStmt = $this->pdo->prepare("UPDATE inventory_items SET quantity = quantity + :qty, updated_at = NOW() WHERE id = :id AND organization_id = :org_id");
            $stockTxStmt = $this->pdo->prepare("
                INSERT INTO stock_transactions (
                    organization_id, inventory_item_id, transaction_type, quantity,
                    unit_cost, reference_type, reference_id, transaction_date,
                    performed_by, remarks, created_at
                ) VALUES (
                    :org_id, :inv_id, 'purchase', :qty,
                    :unit_cost, 'goods_receipt', :ref_id, :tx_date,
                    :performed_by, :remarks, NOW()
                )
            ");

            foreach ($receivedItems as $key => $val) {
                if (is_array($val) && isset($val['purchase_order_item_id'])) {
                    $poiId = (int)$val['purchase_order_item_id'];
                    $recQty = (float)($val['received_quantity'] ?? $val['quantity'] ?? 0);
                } else {
                    $poiId = (int)$key;
                    $recQty = (float)$val;
                }

                if ($recQty <= 0) {
                    continue; // Skip zero/negative rows
                }

                if (!isset($poItemsMap[$poiId])) {
                    throw new InvalidArgumentException("Item ID {$poiId} does not belong to this purchase order.");
                }

                $poi = $poItemsMap[$poiId];
                $currentReceived = (float)$poi['received_quantity'];
                $orderedQty = (float)$poi['ordered_quantity'];
                $newReceivedTotal = round($currentReceived + $recQty, 2);

                if ($newReceivedTotal > $orderedQty) {
                    throw new InvalidArgumentException(
                        "Cannot receive {$recQty} units of '{$poi['item_name']}': Ordered: {$orderedQty}, Previously Received: {$currentReceived}, Max Acceptable: " . ($orderedQty - $currentReceived)
                    );
                }

                // 1. Update received quantity on PO item
                $poItemUpdStmt->execute([
                    ':rec_qty' => $newReceivedTotal,
                    ':id' => $poiId,
                ]);

                // 2. Inventory Integration: If item is linked to inventory_items
                if (!empty($poi['inventory_item_id'])) {
                    $invItemId = (int)$poi['inventory_item_id'];

                    // Increase stock quantity
                    $invUpdStmt->execute([
                        ':qty' => $recQty,
                        ':id' => $invItemId,
                        ':org_id' => $organizationId,
                    ]);

                    // Insert stock transaction
                    $stockTxStmt->execute([
                        ':org_id' => $organizationId,
                        ':inv_id' => $invItemId,
                        ':qty' => $recQty,
                        ':unit_cost' => (float)$poi['unit_cost'],
                        ':ref_id' => $receiptId,
                        ':tx_date' => $receiptDate . ' ' . date('H:i:s'),
                        ':performed_by' => $receiverId,
                        ':remarks' => "Goods Receipt {$receiptNumber} for PO {$po['po_number']}",
                    ]);
                }

                $totalItemsReceived++;
            }

            if ($totalItemsReceived === 0) {
                throw new InvalidArgumentException("At least one line item must have received quantity > 0.");
            }

            // 3. Re-evaluate PO Fulfillment Status
            $chkStmt = $this->pdo->prepare("SELECT ordered_quantity, received_quantity FROM purchase_order_items WHERE purchase_order_id = :po_id");
            $chkStmt->execute([':po_id' => $poId]);
            $allItems = $chkStmt->fetchAll(PDO::FETCH_ASSOC);

            $allCompleted = true;
            foreach ($allItems as $itemRow) {
                if ((float)$itemRow['received_quantity'] < (float)$itemRow['ordered_quantity']) {
                    $allCompleted = false;
                    break;
                }
            }

            $newPoStatus = $allCompleted ? 'received' : 'partially_received';
            $poStatusStmt = $this->pdo->prepare("UPDATE purchase_orders SET status = :status, updated_at = NOW() WHERE id = :po_id");
            $poStatusStmt->execute([':status' => $newPoStatus, ':po_id' => $poId]);

            $this->pdo->commit();

            if ($this->auditLogService && $receiverId) {
                try {
                    $this->auditLogService->log(
                        $organizationId,
                        $receiverId,
                        'receive_goods',
                        'goods_receipt',
                        $receiptId,
                        null,
                        ['receipt_number' => $receiptNumber, 'po_number' => $po['po_number'], 'new_po_status' => $newPoStatus]
                    );
                } catch (\Throwable $e) {}
            }

            $gr = $this->getGoodsReceipt($organizationId, $receiptId);
            $updatedPo = $this->getPurchaseOrder($organizationId, $poId);

            return array_merge($gr ?: [], [
                'goods_receipt' => $gr,
                'purchase_order' => $updatedPo,
            ]);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * List goods receipts.
     */
    public function listGoodsReceipts(int $organizationId, int $page = 1, int $perPage = 15, ?int $poId = null): array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = ["gr.organization_id = :org_id"];
        $params = [':org_id' => $organizationId];

        if ($poId) {
            $conditions[] = "gr.purchase_order_id = :po_id";
            $params[':po_id'] = $poId;
        }

        $whereClause = implode(' AND ', $conditions);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM goods_receipts gr WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT gr.*, 
                   po.po_number,
                   v.company_name as vendor_name,
                   COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Warehouse Staff') as received_by_name
            FROM goods_receipts gr
            LEFT JOIN purchase_orders po ON gr.purchase_order_id = po.id
            LEFT JOIN vendors v ON po.vendor_id = v.id
            LEFT JOIN users u ON gr.received_by = u.id
            WHERE {$whereClause}
            ORDER BY gr.receipt_date DESC, gr.id DESC
            LIMIT :offset, :per_page
        ");

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $perPage,
            'total_pages' => $total > 0 ? (int)ceil($total / $perPage) : 1,
        ];
    }

    /**
     * Get a single goods receipt by ID.
     */
    public function getGoodsReceipt(int $organizationId, int $id): ?array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $stmt = $this->pdo->prepare("
            SELECT gr.*, 
                   po.po_number,
                   po.order_date as po_order_date,
                   po.status as po_status,
                   v.company_name as vendor_name,
                   v.vendor_code,
                   COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Warehouse Staff') as received_by_name
            FROM goods_receipts gr
            LEFT JOIN purchase_orders po ON gr.purchase_order_id = po.id
            LEFT JOIN vendors v ON po.vendor_id = v.id
            LEFT JOIN users u ON gr.received_by = u.id
            WHERE gr.id = :id AND gr.organization_id = :org_id
        ");
        $stmt->execute([':id' => $id, ':org_id' => $organizationId]);
        $gr = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$gr) {
            return null;
        }

        // Fetch items associated with the linked PO
        $itemStmt = $this->pdo->prepare("
            SELECT poi.*, ii.item_code
            FROM purchase_order_items poi
            LEFT JOIN inventory_items ii ON poi.inventory_item_id = ii.id
            WHERE poi.purchase_order_id = :po_id
            ORDER BY poi.id ASC
        ");
        $itemStmt->execute([':po_id' => $gr['purchase_order_id']]);
        $gr['po_items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $gr;
    }
}
