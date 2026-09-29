<?php

namespace App\Services\Vendor;

use App\Services\BaseService;
use App\Services\Audit\AuditLogService;
use PDO;
use InvalidArgumentException;
use RuntimeException;

class VendorService extends BaseService
{
    protected ?AuditLogService $auditLogService = null;

    public function __construct(?AuditLogService $auditLogService = null)
    {
        parent::__construct();
        $this->auditLogService = $auditLogService ?: new AuditLogService();
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

    /**
     * List vendors with filtering, search, and pagination.
     */
    public function listVendors(
        int $organizationId,
        int $page = 1,
        int $perPage = 15,
        ?string $search = null,
        ?string $status = null,
        ?string $vendorType = null
    ): array {
        if (!$this->pdo) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'limit' => $perPage, 'total_pages' => 0];
        }

        $where = ["v.organization_id = :org_id", "v.deleted_at IS NULL"];
        $params = [':org_id' => $organizationId];

        if ($search) {
            $where[] = "(v.company_name LIKE :search OR v.vendor_code LIKE :search OR v.contact_person LIKE :search OR v.email LIKE :search OR v.phone LIKE :search OR v.city LIKE :search OR v.gst_number LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if ($status && in_array($status, ['active', 'inactive', 'blacklisted'], true)) {
            $where[] = "v.status = :status";
            $params[':status'] = $status;
        }

        if ($vendorType) {
            $where[] = "v.vendor_type = :vendor_type";
            $params[':vendor_type'] = $vendorType;
        }

        $whereClause = implode(" AND ", $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM vendors v WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT 
                v.*,
                COUNT(DISTINCT vi.id) as total_invoices_count,
                COALESCE(SUM(vi.total_amount), 0.00) as total_invoiced_amount,
                COUNT(DISTINCT CASE WHEN vi.payment_status = 'unpaid' THEN vi.id END) as unpaid_invoices_count,
                COUNT(DISTINCT po.id) as total_pos_count
            FROM vendors v
            LEFT JOIN vendor_invoices vi ON v.id = vi.vendor_id AND vi.organization_id = :org_id
            LEFT JOIN purchase_orders po ON v.id = po.vendor_id AND po.organization_id = :org_id
            WHERE {$whereClause}
            GROUP BY v.id
            ORDER BY v.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $perPage,
            'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 1
        ];
    }

    /**
     * Get vendor by ID with full details, invoices, and purchase orders.
     */
    public function getVendor(int $organizationId, int $id): ?array
    {
        if (!$this->pdo) return null;

        $stmt = $this->pdo->prepare("
            SELECT v.*
            FROM vendors v
            WHERE v.id = :id AND v.organization_id = :org_id AND v.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':org_id' => $organizationId]);
        $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$vendor) {
            return null;
        }

        // Summary metrics
        $metricStmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT vi.id) as total_invoices_count,
                COALESCE(SUM(vi.total_amount), 0.00) as total_invoiced_amount,
                COALESCE(SUM(CASE WHEN vi.payment_status = 'paid' THEN vi.total_amount ELSE 0 END), 0.00) as total_paid_amount,
                COALESCE(SUM(CASE WHEN vi.payment_status = 'unpaid' THEN vi.total_amount ELSE 0 END), 0.00) as total_unpaid_amount,
                COUNT(DISTINCT CASE WHEN vi.payment_status = 'unpaid' THEN vi.id END) as unpaid_invoices_count
            FROM vendor_invoices vi
            WHERE vi.vendor_id = :id AND vi.organization_id = :org_id
        ");
        $metricStmt->execute([':id' => $id, ':org_id' => $organizationId]);
        $vendor['metrics'] = $metricStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Recent Invoices (up to 50)
        $invStmt = $this->pdo->prepare("
            SELECT vi.*, po.po_number
            FROM vendor_invoices vi
            LEFT JOIN purchase_orders po ON vi.purchase_order_id = po.id
            WHERE vi.vendor_id = :id AND vi.organization_id = :org_id
            ORDER BY vi.invoice_date DESC, vi.id DESC
            LIMIT 50
        ");
        $invStmt->execute([':id' => $id, ':org_id' => $organizationId]);
        $vendor['invoices'] = $invStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Existing Purchase Orders (read-only history)
        $poStmt = $this->pdo->prepare("
            SELECT po.*
            FROM purchase_orders po
            WHERE po.vendor_id = :id AND po.organization_id = :org_id
            ORDER BY po.order_date DESC, po.id DESC
            LIMIT 50
        ");
        $poStmt->execute([':id' => $id, ':org_id' => $organizationId]);
        $vendor['purchase_orders'] = $poStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $vendor;
    }

    /**
     * Create a new vendor.
     */
    public function createVendor(int $organizationId, array $data, ?int $userId = null): array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $companyName = trim($data['company_name'] ?? '');
        if (empty($companyName)) {
            throw new InvalidArgumentException("Company name is required.");
        }
        if (strlen($companyName) > 200) {
            throw new InvalidArgumentException("Company name cannot exceed 200 characters.");
        }

        $vendorCode = trim($data['vendor_code'] ?? '');
        if (empty($vendorCode)) {
            $vendorCode = $this->generateVendorCode($organizationId);
        } else {
            // Check uniqueness per org
            $checkStmt = $this->pdo->prepare("
                SELECT id FROM vendors 
                WHERE organization_id = :org_id AND vendor_code = :code AND deleted_at IS NULL
                LIMIT 1
            ");
            $checkStmt->execute([':org_id' => $organizationId, ':code' => $vendorCode]);
            if ($checkStmt->fetchColumn()) {
                throw new InvalidArgumentException("A vendor with code '{$vendorCode}' already exists in this organization.");
            }
        }

        $status = trim($data['status'] ?? 'active');
        if (!in_array($status, ['active', 'inactive', 'blacklisted'], true)) {
            $status = 'active';
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO vendors (
                organization_id, vendor_code, company_name, contact_person,
                email, phone, alternate_phone, gst_number, pan_number,
                address_line1, address_line2, city, state, country,
                postal_code, latitude, longitude, bank_name,
                bank_account_number, bank_ifsc, vendor_type, status,
                notes, created_at, updated_at
            ) VALUES (
                :org_id, :vendor_code, :company_name, :contact_person,
                :email, :phone, :alternate_phone, :gst_number, :pan_number,
                :address_line1, :address_line2, :city, :state, :country,
                :postal_code, :latitude, :longitude, :bank_name,
                :bank_account_number, :bank_ifsc, :vendor_type, :status,
                :notes, NOW(), NOW()
            )
        ");

        $stmt->execute([
            ':org_id' => $organizationId,
            ':vendor_code' => $vendorCode,
            ':company_name' => $companyName,
            ':contact_person' => !empty($data['contact_person']) ? trim($data['contact_person']) : null,
            ':email' => !empty($data['email']) ? trim($data['email']) : null,
            ':phone' => !empty($data['phone']) ? trim($data['phone']) : null,
            ':alternate_phone' => !empty($data['alternate_phone']) ? trim($data['alternate_phone']) : null,
            ':gst_number' => !empty($data['gst_number']) ? strtoupper(trim($data['gst_number'])) : null,
            ':pan_number' => !empty($data['pan_number']) ? strtoupper(trim($data['pan_number'])) : null,
            ':address_line1' => !empty($data['address_line1']) ? trim($data['address_line1']) : null,
            ':address_line2' => !empty($data['address_line2']) ? trim($data['address_line2']) : null,
            ':city' => !empty($data['city']) ? trim($data['city']) : null,
            ':state' => !empty($data['state']) ? trim($data['state']) : null,
            ':country' => !empty($data['country']) ? trim($data['country']) : 'India',
            ':postal_code' => !empty($data['postal_code']) ? trim($data['postal_code']) : null,
            ':latitude' => isset($data['latitude']) && $data['latitude'] !== '' ? (float)$data['latitude'] : null,
            ':longitude' => isset($data['longitude']) && $data['longitude'] !== '' ? (float)$data['longitude'] : null,
            ':bank_name' => !empty($data['bank_name']) ? trim($data['bank_name']) : null,
            ':bank_account_number' => !empty($data['bank_account_number']) ? trim($data['bank_account_number']) : null,
            ':bank_ifsc' => !empty($data['bank_ifsc']) ? strtoupper(trim($data['bank_ifsc'])) : null,
            ':vendor_type' => !empty($data['vendor_type']) ? trim($data['vendor_type']) : null,
            ':status' => $status,
            ':notes' => !empty($data['notes']) ? trim($data['notes']) : null,
        ]);

        $newId = (int)$this->pdo->lastInsertId();

        $auditUser = $this->resolveAuditUserId($userId, $organizationId);
        if ($this->auditLogService && $auditUser) {
            try {
                $this->auditLogService->log(
                    $organizationId,
                    $auditUser,
                    'create',
                    'vendor',
                    $newId,
                    null,
                    [
                        'vendor_code' => $vendorCode,
                        'company_name' => $companyName,
                        'status' => $status,
                    ]
                );
            } catch (\Throwable $e) {}
        }

        return $this->getVendor($organizationId, $newId);
    }

    /**
     * Update an existing vendor.
     */
    public function updateVendor(int $organizationId, int $id, array $data, ?int $userId = null): bool
    {
        if (!$this->pdo) return false;

        $existing = $this->getVendor($organizationId, $id);
        if (!$existing) {
            throw new InvalidArgumentException("Vendor not found or access denied.");
        }

        $companyName = isset($data['company_name']) ? trim($data['company_name']) : $existing['company_name'];
        if (empty($companyName)) {
            throw new InvalidArgumentException("Company name cannot be empty.");
        }

        $vendorCode = isset($data['vendor_code']) ? trim($data['vendor_code']) : $existing['vendor_code'];
        if ($vendorCode !== $existing['vendor_code']) {
            $checkStmt = $this->pdo->prepare("
                SELECT id FROM vendors 
                WHERE organization_id = :org_id AND vendor_code = :code AND id != :id AND deleted_at IS NULL
                LIMIT 1
            ");
            $checkStmt->execute([':org_id' => $organizationId, ':code' => $vendorCode, ':id' => $id]);
            if ($checkStmt->fetchColumn()) {
                throw new InvalidArgumentException("A vendor with code '{$vendorCode}' already exists in this organization.");
            }
        }

        $status = isset($data['status']) ? trim($data['status']) : $existing['status'];
        if (!in_array($status, ['active', 'inactive', 'blacklisted'], true)) {
            $status = $existing['status'];
        }

        $stmt = $this->pdo->prepare("
            UPDATE vendors SET
                vendor_code = :vendor_code,
                company_name = :company_name,
                contact_person = :contact_person,
                email = :email,
                phone = :phone,
                alternate_phone = :alternate_phone,
                gst_number = :gst_number,
                pan_number = :pan_number,
                address_line1 = :address_line1,
                address_line2 = :address_line2,
                city = :city,
                state = :state,
                country = :country,
                postal_code = :postal_code,
                latitude = :latitude,
                longitude = :longitude,
                bank_name = :bank_name,
                bank_account_number = :bank_account_number,
                bank_ifsc = :bank_ifsc,
                vendor_type = :vendor_type,
                status = :status,
                notes = :notes,
                updated_at = NOW()
            WHERE id = :id AND organization_id = :org_id
        ");

        $ok = $stmt->execute([
            ':vendor_code' => $vendorCode,
            ':company_name' => $companyName,
            ':contact_person' => array_key_exists('contact_person', $data) ? (!empty($data['contact_person']) ? trim($data['contact_person']) : null) : $existing['contact_person'],
            ':email' => array_key_exists('email', $data) ? (!empty($data['email']) ? trim($data['email']) : null) : $existing['email'],
            ':phone' => array_key_exists('phone', $data) ? (!empty($data['phone']) ? trim($data['phone']) : null) : $existing['phone'],
            ':alternate_phone' => array_key_exists('alternate_phone', $data) ? (!empty($data['alternate_phone']) ? trim($data['alternate_phone']) : null) : $existing['alternate_phone'],
            ':gst_number' => array_key_exists('gst_number', $data) ? (!empty($data['gst_number']) ? strtoupper(trim($data['gst_number'])) : null) : $existing['gst_number'],
            ':pan_number' => array_key_exists('pan_number', $data) ? (!empty($data['pan_number']) ? strtoupper(trim($data['pan_number'])) : null) : $existing['pan_number'],
            ':address_line1' => array_key_exists('address_line1', $data) ? (!empty($data['address_line1']) ? trim($data['address_line1']) : null) : $existing['address_line1'],
            ':address_line2' => array_key_exists('address_line2', $data) ? (!empty($data['address_line2']) ? trim($data['address_line2']) : null) : $existing['address_line2'],
            ':city' => array_key_exists('city', $data) ? (!empty($data['city']) ? trim($data['city']) : null) : $existing['city'],
            ':state' => array_key_exists('state', $data) ? (!empty($data['state']) ? trim($data['state']) : null) : $existing['state'],
            ':country' => array_key_exists('country', $data) ? (!empty($data['country']) ? trim($data['country']) : 'India') : ($existing['country'] ?: 'India'),
            ':postal_code' => array_key_exists('postal_code', $data) ? (!empty($data['postal_code']) ? trim($data['postal_code']) : null) : $existing['postal_code'],
            ':latitude' => array_key_exists('latitude', $data) ? ($data['latitude'] !== '' && $data['latitude'] !== null ? (float)$data['latitude'] : null) : $existing['latitude'],
            ':longitude' => array_key_exists('longitude', $data) ? ($data['longitude'] !== '' && $data['longitude'] !== null ? (float)$data['longitude'] : null) : $existing['longitude'],
            ':bank_name' => array_key_exists('bank_name', $data) ? (!empty($data['bank_name']) ? trim($data['bank_name']) : null) : $existing['bank_name'],
            ':bank_account_number' => array_key_exists('bank_account_number', $data) ? (!empty($data['bank_account_number']) ? trim($data['bank_account_number']) : null) : $existing['bank_account_number'],
            ':bank_ifsc' => array_key_exists('bank_ifsc', $data) ? (!empty($data['bank_ifsc']) ? strtoupper(trim($data['bank_ifsc'])) : null) : $existing['bank_ifsc'],
            ':vendor_type' => array_key_exists('vendor_type', $data) ? (!empty($data['vendor_type']) ? trim($data['vendor_type']) : null) : $existing['vendor_type'],
            ':status' => $status,
            ':notes' => array_key_exists('notes', $data) ? (!empty($data['notes']) ? trim($data['notes']) : null) : $existing['notes'],
            ':id' => $id,
            ':org_id' => $organizationId,
        ]);

        $auditUser = $this->resolveAuditUserId($userId, $organizationId);
        if ($ok && $this->auditLogService && $auditUser) {
            try {
                $this->auditLogService->log(
                    $organizationId,
                    $auditUser,
                    'update',
                    'vendor',
                    $id,
                    ['company_name' => $existing['company_name'], 'status' => $existing['status']],
                    ['company_name' => $companyName, 'status' => $status]
                );
            } catch (\Throwable $e) {}
        }

        return $ok;
    }

    /**
     * Set vendor status (active, inactive, blacklisted).
     */
    public function setStatus(int $organizationId, int $id, string $status, ?int $userId = null, ?string $reason = null): array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $existing = $this->getVendor($organizationId, $id);
        if (!$existing) {
            throw new InvalidArgumentException("Vendor not found or access denied.");
        }

        if (!in_array($status, ['active', 'inactive', 'blacklisted'], true)) {
            throw new InvalidArgumentException("Invalid status. Allowed values: active, inactive, blacklisted.");
        }

        $stmt = $this->pdo->prepare("
            UPDATE vendors SET status = :status, updated_at = NOW()
            WHERE id = :id AND organization_id = :org_id
        ");
        $stmt->execute([':status' => $status, ':id' => $id, ':org_id' => $organizationId]);

        $auditUser = $this->resolveAuditUserId($userId, $organizationId);
        if ($this->auditLogService && $auditUser) {
            try {
                $newData = ['status' => $status];
                if ($reason) {
                    $newData['reason'] = $reason;
                }
                $this->auditLogService->log(
                    $organizationId,
                    $auditUser,
                    'set_status',
                    'vendor',
                    $id,
                    ['status' => $existing['status']],
                    $newData
                );
            } catch (\Throwable $e) {}
        }

        return [
            'id' => $id,
            'company_name' => $existing['company_name'],
            'previous_status' => $existing['status'],
            'status' => $status,
        ];
    }

    /**
     * Soft delete a vendor.
     */
    public function deleteVendor(int $organizationId, int $id, ?int $userId = null): bool
    {
        if (!$this->pdo) return false;

        $existing = $this->getVendor($organizationId, $id);
        if (!$existing) {
            throw new InvalidArgumentException("Vendor not found or access denied.");
        }

        // Guard: Check unpaid/pending invoices
        $chkStmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM vendor_invoices 
            WHERE vendor_id = :id AND organization_id = :org_id AND payment_status IN ('unpaid', 'partially_paid')
        ");
        $chkStmt->execute([':id' => $id, ':org_id' => $organizationId]);
        $unpaidCount = (int)$chkStmt->fetchColumn();
        if ($unpaidCount > 0) {
            throw new InvalidArgumentException("Cannot delete vendor '{$existing['company_name']}': There are {$unpaidCount} unpaid or partially paid invoice(s). Settle or cancel invoices before deleting.");
        }

        $stmt = $this->pdo->prepare("
            UPDATE vendors 
            SET deleted_at = NOW(), updated_at = NOW() 
            WHERE id = :id AND organization_id = :org_id
        ");
        $ok = $stmt->execute([':id' => $id, ':org_id' => $organizationId]);

        $auditUser = $this->resolveAuditUserId($userId, $organizationId);
        if ($ok && $this->auditLogService && $auditUser) {
            try {
                $this->auditLogService->log(
                    $organizationId,
                    $auditUser,
                    'delete',
                    'vendor',
                    $id,
                    ['company_name' => $existing['company_name'], 'vendor_code' => $existing['vendor_code']],
                    null
                );
            } catch (\Throwable $e) {}
        }

        return $ok;
    }

    // =========================================================================
    // VENDOR INVOICES
    // =========================================================================

    /**
     * List invoices for an organization, optionally filtered by vendor.
     */
    public function listInvoices(
        int $organizationId,
        ?int $vendorId = null,
        int $page = 1,
        int $perPage = 15,
        ?string $paymentStatus = null,
        ?string $search = null
    ): array {
        if (!$this->pdo) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'limit' => $perPage, 'total_pages' => 0];
        }

        $where = ["vi.organization_id = :org_id"];
        $params = [':org_id' => $organizationId];

        if ($vendorId) {
            $where[] = "vi.vendor_id = :vendor_id";
            $params[':vendor_id'] = $vendorId;
        }

        if ($paymentStatus && in_array($paymentStatus, ['unpaid', 'partially_paid', 'paid', 'cancelled'], true)) {
            $where[] = "vi.payment_status = :payment_status";
            $params[':payment_status'] = $paymentStatus;
        }

        if ($search) {
            $where[] = "(vi.invoice_number LIKE :search OR v.company_name LIKE :search OR po.po_number LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $whereClause = implode(" AND ", $where);

        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM vendor_invoices vi
            JOIN vendors v ON vi.vendor_id = v.id
            LEFT JOIN purchase_orders po ON vi.purchase_order_id = po.id
            WHERE {$whereClause}
        ");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT 
                vi.*,
                v.company_name as vendor_name,
                v.vendor_code,
                po.po_number
            FROM vendor_invoices vi
            JOIN vendors v ON vi.vendor_id = v.id
            LEFT JOIN purchase_orders po ON vi.purchase_order_id = po.id
            WHERE {$whereClause}
            ORDER BY vi.invoice_date DESC, vi.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $perPage,
            'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 1
        ];
    }

    /**
     * Get a single vendor invoice.
     */
    public function getInvoice(int $organizationId, int $invoiceId): ?array
    {
        if (!$this->pdo) return null;

        $stmt = $this->pdo->prepare("
            SELECT 
                vi.*,
                v.company_name as vendor_name,
                v.vendor_code,
                v.gst_number as vendor_gst,
                v.pan_number as vendor_pan,
                v.phone as vendor_phone,
                v.email as vendor_email,
                po.po_number,
                po.order_date as po_order_date
            FROM vendor_invoices vi
            JOIN vendors v ON vi.vendor_id = v.id
            LEFT JOIN purchase_orders po ON vi.purchase_order_id = po.id
            WHERE vi.id = :id AND vi.organization_id = :org_id
            LIMIT 1
        ");
        $stmt->execute([':id' => $invoiceId, ':org_id' => $organizationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Create a vendor invoice.
     */
    public function createInvoice(int $organizationId, int $vendorId, array $data, ?int $userId = null): array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        // Verify vendor belongs to org
        $vendor = $this->getVendor($organizationId, $vendorId);
        if (!$vendor) {
            throw new InvalidArgumentException("Vendor not found or access denied.");
        }

        $invoiceNumber = trim($data['invoice_number'] ?? '');
        if (empty($invoiceNumber)) {
            throw new InvalidArgumentException("Invoice number is required.");
        }

        // Check composite uniqueness: (organization_id, vendor_id, invoice_number)
        $chkStmt = $this->pdo->prepare("
            SELECT id FROM vendor_invoices 
            WHERE organization_id = :org_id AND vendor_id = :v_id AND invoice_number = :inv_num
            LIMIT 1
        ");
        $chkStmt->execute([':org_id' => $organizationId, ':v_id' => $vendorId, ':inv_num' => $invoiceNumber]);
        if ($chkStmt->fetchColumn()) {
            throw new InvalidArgumentException("An invoice with number '{$invoiceNumber}' already exists for this vendor.");
        }

        $invoiceDate = !empty($data['invoice_date']) ? trim($data['invoice_date']) : date('Y-m-d');
        $dueDate = !empty($data['due_date']) ? trim($data['due_date']) : null;

        $subtotal = max(0, (float)($data['subtotal'] ?? 0));
        $taxAmount = max(0, (float)($data['tax_amount'] ?? 0));
        $discountAmount = max(0, (float)($data['discount_amount'] ?? 0));
        $totalAmount = max(0, $subtotal + $taxAmount - $discountAmount);

        // Optional PO validation
        $purchaseOrderId = !empty($data['purchase_order_id']) ? (int)$data['purchase_order_id'] : null;
        if ($purchaseOrderId) {
            $poStmt = $this->pdo->prepare("
                SELECT id FROM purchase_orders 
                WHERE id = :po_id AND organization_id = :org_id AND vendor_id = :v_id
                LIMIT 1
            ");
            $poStmt->execute([':po_id' => $purchaseOrderId, ':org_id' => $organizationId, ':v_id' => $vendorId]);
            if (!$poStmt->fetchColumn()) {
                throw new InvalidArgumentException("The linked purchase order does not exist or does not belong to this vendor.");
            }
        }

        $paymentStatus = trim($data['payment_status'] ?? 'unpaid');
        if (!in_array($paymentStatus, ['unpaid', 'partially_paid', 'paid', 'cancelled'], true)) {
            $paymentStatus = 'unpaid';
        }

        $filePath = !empty($data['file_path']) ? trim($data['file_path']) : null;
        $notes = !empty($data['notes']) ? trim($data['notes']) : null;

        $stmt = $this->pdo->prepare("
            INSERT INTO vendor_invoices (
                organization_id, vendor_id, purchase_order_id, invoice_number,
                invoice_date, due_date, subtotal, tax_amount, discount_amount,
                total_amount, payment_status, file_path, notes,
                created_at, updated_at
            ) VALUES (
                :org_id, :vendor_id, :po_id, :invoice_number,
                :invoice_date, :due_date, :subtotal, :tax_amount, :discount_amount,
                :total_amount, :payment_status, :file_path, :notes,
                NOW(), NOW()
            )
        ");

        $stmt->execute([
            ':org_id' => $organizationId,
            ':vendor_id' => $vendorId,
            ':po_id' => $purchaseOrderId,
            ':invoice_number' => $invoiceNumber,
            ':invoice_date' => $invoiceDate,
            ':due_date' => $dueDate,
            ':subtotal' => $subtotal,
            ':tax_amount' => $taxAmount,
            ':discount_amount' => $discountAmount,
            ':total_amount' => $totalAmount,
            ':payment_status' => $paymentStatus,
            ':file_path' => $filePath,
            ':notes' => $notes,
        ]);

        $newId = (int)$this->pdo->lastInsertId();

        $auditUser = $this->resolveAuditUserId($userId, $organizationId);
        if ($this->auditLogService && $auditUser) {
            try {
                $this->auditLogService->log(
                    $organizationId,
                    $auditUser,
                    'create',
                    'vendor_invoice',
                    $newId,
                    null,
                    [
                        'vendor_id' => $vendorId,
                        'invoice_number' => $invoiceNumber,
                        'total_amount' => $totalAmount,
                        'payment_status' => $paymentStatus,
                    ]
                );
            } catch (\Throwable $e) {}
        }

        return $this->getInvoice($organizationId, $newId);
    }

    /**
     * Update a vendor invoice.
     */
    public function updateInvoice(int $organizationId, int $invoiceId, array $data, ?int $userId = null): bool
    {
        if (!$this->pdo) return false;

        $existing = $this->getInvoice($organizationId, $invoiceId);
        if (!$existing) {
            throw new InvalidArgumentException("Invoice not found or access denied.");
        }

        $invoiceNumber = isset($data['invoice_number']) ? trim($data['invoice_number']) : $existing['invoice_number'];
        if (empty($invoiceNumber)) {
            throw new InvalidArgumentException("Invoice number cannot be empty.");
        }

        if ($invoiceNumber !== $existing['invoice_number']) {
            $chkStmt = $this->pdo->prepare("
                SELECT id FROM vendor_invoices 
                WHERE organization_id = :org_id AND vendor_id = :v_id AND invoice_number = :inv_num AND id != :id
                LIMIT 1
            ");
            $chkStmt->execute([
                ':org_id' => $organizationId,
                ':v_id' => $existing['vendor_id'],
                ':inv_num' => $invoiceNumber,
                ':id' => $invoiceId,
            ]);
            if ($chkStmt->fetchColumn()) {
                throw new InvalidArgumentException("An invoice with number '{$invoiceNumber}' already exists for this vendor.");
            }
        }

        $invoiceDate = array_key_exists('invoice_date', $data) ? trim($data['invoice_date']) : $existing['invoice_date'];
        $dueDate = array_key_exists('due_date', $data) ? (!empty($data['due_date']) ? trim($data['due_date']) : null) : $existing['due_date'];

        $subtotal = array_key_exists('subtotal', $data) ? max(0, (float)$data['subtotal']) : (float)$existing['subtotal'];
        $taxAmount = array_key_exists('tax_amount', $data) ? max(0, (float)$data['tax_amount']) : (float)$existing['tax_amount'];
        $discountAmount = array_key_exists('discount_amount', $data) ? max(0, (float)$data['discount_amount']) : (float)$existing['discount_amount'];
        $totalAmount = max(0, $subtotal + $taxAmount - $discountAmount);

        $paymentStatus = isset($data['payment_status']) ? trim($data['payment_status']) : $existing['payment_status'];
        if (!in_array($paymentStatus, ['unpaid', 'partially_paid', 'paid', 'cancelled'], true)) {
            $paymentStatus = $existing['payment_status'];
        }

        $notes = array_key_exists('notes', $data) ? (!empty($data['notes']) ? trim($data['notes']) : null) : $existing['notes'];

        $stmt = $this->pdo->prepare("
            UPDATE vendor_invoices SET
                invoice_number = :invoice_number,
                invoice_date = :invoice_date,
                due_date = :due_date,
                subtotal = :subtotal,
                tax_amount = :tax_amount,
                discount_amount = :discount_amount,
                total_amount = :total_amount,
                payment_status = :payment_status,
                notes = :notes,
                updated_at = NOW()
            WHERE id = :id AND organization_id = :org_id
        ");

        $ok = $stmt->execute([
            ':invoice_number' => $invoiceNumber,
            ':invoice_date' => $invoiceDate,
            ':due_date' => $dueDate,
            ':subtotal' => $subtotal,
            ':tax_amount' => $taxAmount,
            ':discount_amount' => $discountAmount,
            ':total_amount' => $totalAmount,
            ':payment_status' => $paymentStatus,
            ':notes' => $notes,
            ':id' => $invoiceId,
            ':org_id' => $organizationId,
        ]);

        $auditUser = $this->resolveAuditUserId($userId, $organizationId);
        if ($ok && $this->auditLogService && $auditUser) {
            try {
                $this->auditLogService->log(
                    $organizationId,
                    $auditUser,
                    'update',
                    'vendor_invoice',
                    $invoiceId,
                    ['total_amount' => $existing['total_amount'], 'payment_status' => $existing['payment_status']],
                    ['total_amount' => $totalAmount, 'payment_status' => $paymentStatus]
                );
            } catch (\Throwable $e) {}
        }

        return $ok;
    }

    /**
     * Generate unique vendor code: VND-YYYYMMDD-XXXX
     */
    protected function generateVendorCode(int $organizationId): string
    {
        $prefix = 'VND-' . date('Ymd') . '-';
        $attempts = 0;
        do {
            $code = $prefix . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $this->pdo->prepare("
                SELECT id FROM vendors 
                WHERE organization_id = :org_id AND vendor_code = :code AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([':org_id' => $organizationId, ':code' => $code]);
            $exists = $stmt->fetchColumn();
            $attempts++;
        } while ($exists && $attempts < 15);

        return $code;
    }
}
