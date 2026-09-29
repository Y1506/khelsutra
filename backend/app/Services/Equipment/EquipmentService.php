<?php

namespace App\Services\Equipment;

use App\Services\BaseService;
use App\Services\Audit\AuditLogService;
use PDO;
use InvalidArgumentException;
use RuntimeException;

class EquipmentService extends BaseService
{
    protected ?AuditLogService $auditLogService = null;

    public function __construct(?AuditLogService $auditLogService = null)
    {
        parent::__construct();
        $this->auditLogService = $auditLogService ?: new AuditLogService();
    }

    /**
     * Resolve a safe user ID for audit logging.
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
     * List equipment with filters and pagination.
     */
    public function listEquipment(
        int $organizationId,
        int $page = 1,
        int $perPage = 15,
        ?string $search = null,
        ?string $status = null,
        ?string $condition = null,
        ?int $inventoryItemId = null
    ): array {
        if (!$this->pdo) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'limit' => $perPage, 'total_pages' => 0];
        }

        $where = ["eq.organization_id = :org_id", "eq.deleted_at IS NULL"];
        $params = [':org_id' => $organizationId];

        if ($search) {
            $where[] = "(eq.equipment_name LIKE :search OR eq.asset_code LIKE :search OR eq.serial_number LIKE :search OR eq.model_number LIKE :search OR eq.manufacturer LIKE :search OR eq.current_location LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if ($status && in_array($status, ['available', 'assigned', 'maintenance', 'lost', 'disposed'], true)) {
            $where[] = "eq.status = :status";
            $params[':status'] = $status;
        }

        if ($condition && in_array($condition, ['new', 'good', 'damaged', 'under_maintenance', 'lost', 'disposed'], true)) {
            $where[] = "eq.condition_status = :condition";
            $params[':condition'] = $condition;
        }

        if ($inventoryItemId) {
            $where[] = "eq.inventory_item_id = :inv_item_id";
            $params[':inv_item_id'] = $inventoryItemId;
        }

        $whereClause = implode(" AND ", $where);

        // Count total
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM equipment eq WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        // Fetch equipment list with linked inventory item and latest active assignment details
        $sql = "
            SELECT 
                eq.*,
                ii.item_name as linked_item_name,
                ii.item_code as linked_item_code,
                ea.id as current_assignment_id,
                ea.assignee_type as current_assignee_type,
                ea.assigned_date as current_assigned_date,
                ea.expected_return_date as current_expected_return_date,
                ea.condition_on_issue as current_condition_on_issue,
                CASE 
                    WHEN ea.assignee_type = 'athlete' THEN CONCAT(ath.first_name, ' ', ath.last_name)
                    WHEN ea.assignee_type = 'coach' THEN (
                        SELECT CONCAT(emp_c.first_name, ' ', emp_c.last_name) 
                        FROM coach_profiles cp 
                        JOIN employees emp_c ON cp.employee_id = emp_c.id 
                        WHERE cp.id = ea.coach_id
                    )
                    WHEN ea.assignee_type = 'employee' THEN CONCAT(emp.first_name, ' ', emp.last_name)
                    WHEN ea.assignee_type = 'team' THEN tm.name
                    WHEN ea.assignee_type = 'venue' THEN vn.name
                    ELSE NULL
                END as current_assignee_name,
                CASE 
                    WHEN ea.assignee_type = 'athlete' THEN ath.athlete_code
                    WHEN ea.assignee_type = 'coach' THEN (
                        SELECT cp.coach_code 
                        FROM coach_profiles cp 
                        WHERE cp.id = ea.coach_id
                    )
                    WHEN ea.assignee_type = 'employee' THEN emp.employee_code
                    WHEN ea.assignee_type = 'team' THEN tm.team_code
                    WHEN ea.assignee_type = 'venue' THEN vn.venue_code
                    ELSE NULL
                END as current_assignee_code
            FROM equipment eq
            LEFT JOIN inventory_items ii ON eq.inventory_item_id = ii.id AND ii.deleted_at IS NULL
            LEFT JOIN equipment_assignments ea ON eq.id = ea.equipment_id AND ea.status = 'assigned'
            LEFT JOIN athletes ath ON ea.athlete_id = ath.id
            LEFT JOIN employees emp ON ea.employee_id = emp.id
            LEFT JOIN teams tm ON ea.team_id = tm.id
            LEFT JOIN venues vn ON ea.venue_id = vn.id
            WHERE {$whereClause}
            ORDER BY eq.id DESC
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
     * Get single equipment item by ID with full details, active assignment, and assignment history.
     */
    public function getEquipment(int $organizationId, int $id): ?array
    {
        if (!$this->pdo) return null;

        $stmt = $this->pdo->prepare("
            SELECT 
                eq.*,
                ii.item_name as linked_item_name,
                ii.item_code as linked_item_code,
                ic.name as linked_category_name
            FROM equipment eq
            LEFT JOIN inventory_items ii ON eq.inventory_item_id = ii.id AND ii.deleted_at IS NULL
            LEFT JOIN inventory_categories ic ON ii.category_id = ic.id
            WHERE eq.id = :id AND eq.organization_id = :org_id AND eq.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':org_id' => $organizationId]);
        $equipment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$equipment) {
            return null;
        }

        // Active Assignment (if any)
        $assignStmt = $this->pdo->prepare("
            SELECT 
                ea.*,
                CONCAT(u_iss.first_name, ' ', u_iss.last_name) as issued_by_name,
                CASE 
                    WHEN ea.assignee_type = 'athlete' THEN CONCAT(ath.first_name, ' ', ath.last_name)
                    WHEN ea.assignee_type = 'coach' THEN (
                        SELECT CONCAT(emp_c.first_name, ' ', emp_c.last_name) 
                        FROM coach_profiles cp 
                        JOIN employees emp_c ON cp.employee_id = emp_c.id 
                        WHERE cp.id = ea.coach_id
                    )
                    WHEN ea.assignee_type = 'employee' THEN CONCAT(emp.first_name, ' ', emp.last_name)
                    WHEN ea.assignee_type = 'team' THEN tm.name
                    WHEN ea.assignee_type = 'venue' THEN vn.name
                    ELSE NULL
                END as assignee_name,
                CASE 
                    WHEN ea.assignee_type = 'athlete' THEN ath.athlete_code
                    WHEN ea.assignee_type = 'coach' THEN (
                        SELECT cp.coach_code 
                        FROM coach_profiles cp 
                        WHERE cp.id = ea.coach_id
                    )
                    WHEN ea.assignee_type = 'employee' THEN emp.employee_code
                    WHEN ea.assignee_type = 'team' THEN tm.team_code
                    WHEN ea.assignee_type = 'venue' THEN vn.venue_code
                    ELSE NULL
                END as assignee_code
            FROM equipment_assignments ea
            LEFT JOIN users u_iss ON ea.issued_by = u_iss.id
            LEFT JOIN athletes ath ON ea.athlete_id = ath.id
            LEFT JOIN employees emp ON ea.employee_id = emp.id
            LEFT JOIN teams tm ON ea.team_id = tm.id
            LEFT JOIN venues vn ON ea.venue_id = vn.id
            WHERE ea.equipment_id = :eq_id AND ea.organization_id = :org_id AND ea.status = 'assigned'
            ORDER BY ea.id DESC
            LIMIT 1
        ");
        $assignStmt->execute([':eq_id' => $id, ':org_id' => $organizationId]);
        $equipment['active_assignment'] = $assignStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // Assignment History (Up to 50 records)
        $equipment['assignment_history'] = $this->getAssignmentHistory($organizationId, $id);

        return $equipment;
    }

    /**
     * Create a new equipment asset.
     */
    public function createEquipment(int $organizationId, array $data, ?int $userId = null): array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $equipmentName = trim($data['equipment_name'] ?? '');
        if (empty($equipmentName)) {
            throw new InvalidArgumentException("Equipment name is required.");
        }
        if (strlen($equipmentName) > 150) {
            throw new InvalidArgumentException("Equipment name cannot exceed 150 characters.");
        }

        // Validate or generate Asset Code
        $assetCode = trim($data['asset_code'] ?? '');
        if (empty($assetCode)) {
            $assetCode = $this->generateAssetCode($organizationId);
        } else {
            // Check uniqueness per org
            $checkStmt = $this->pdo->prepare("
                SELECT id FROM equipment 
                WHERE organization_id = :org_id AND asset_code = :code AND deleted_at IS NULL
                LIMIT 1
            ");
            $checkStmt->execute([':org_id' => $organizationId, ':code' => $assetCode]);
            if ($checkStmt->fetchColumn()) {
                throw new InvalidArgumentException("An equipment item with asset code '{$assetCode}' already exists.");
            }
        }

        // Validate serial number uniqueness if provided
        $serialNumber = !empty($data['serial_number']) ? trim($data['serial_number']) : null;
        if ($serialNumber !== null) {
            $snStmt = $this->pdo->prepare("
                SELECT id FROM equipment 
                WHERE organization_id = :org_id AND serial_number = :sn AND deleted_at IS NULL
                LIMIT 1
            ");
            $snStmt->execute([':org_id' => $organizationId, ':sn' => $serialNumber]);
            if ($snStmt->fetchColumn()) {
                throw new InvalidArgumentException("An equipment item with serial number '{$serialNumber}' already exists.");
            }
        }

        // Validate linked inventory item
        $inventoryItemId = !empty($data['inventory_item_id']) ? (int)$data['inventory_item_id'] : null;
        if ($inventoryItemId !== null) {
            $itemStmt = $this->pdo->prepare("
                SELECT id FROM inventory_items 
                WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
                LIMIT 1
            ");
            $itemStmt->execute([':id' => $inventoryItemId, ':org_id' => $organizationId]);
            if (!$itemStmt->fetchColumn()) {
                throw new InvalidArgumentException("The linked inventory item does not exist or belongs to another organization.");
            }
        }

        $modelNumber = !empty($data['model_number']) ? trim($data['model_number']) : null;
        $manufacturer = !empty($data['manufacturer']) ? trim($data['manufacturer']) : null;
        $currentLocation = !empty($data['current_location']) ? trim($data['current_location']) : 'Main Storage';

        $purchaseDate = !empty($data['purchase_date']) ? trim($data['purchase_date']) : null;
        $warrantyExpiryDate = !empty($data['warranty_expiry_date']) ? trim($data['warranty_expiry_date']) : null;
        $purchaseCost = isset($data['purchase_cost']) && $data['purchase_cost'] !== '' ? max(0, (float)$data['purchase_cost']) : null;

        $conditionStatus = trim($data['condition_status'] ?? 'new');
        $allowedConditions = ['new', 'good', 'damaged', 'under_maintenance', 'lost', 'disposed'];
        if (!in_array($conditionStatus, $allowedConditions, true)) {
            $conditionStatus = 'new';
        }

        $status = trim($data['status'] ?? 'available');
        $allowedStatuses = ['available', 'assigned', 'maintenance', 'lost', 'disposed'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'available';
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO equipment (
                organization_id, inventory_item_id, asset_code, equipment_name,
                serial_number, model_number, manufacturer, purchase_date,
                purchase_cost, warranty_expiry_date, condition_status,
                current_location, status, created_at, updated_at
            ) VALUES (
                :org_id, :inv_item_id, :asset_code, :eq_name,
                :serial_number, :model_number, :manufacturer, :purchase_date,
                :purchase_cost, :warranty_expiry_date, :condition_status,
                :current_location, :status, NOW(), NOW()
            )
        ");

        $stmt->execute([
            ':org_id' => $organizationId,
            ':inv_item_id' => $inventoryItemId,
            ':asset_code' => $assetCode,
            ':eq_name' => $equipmentName,
            ':serial_number' => $serialNumber,
            ':model_number' => $modelNumber,
            ':manufacturer' => $manufacturer,
            ':purchase_date' => $purchaseDate,
            ':purchase_cost' => $purchaseCost,
            ':warranty_expiry_date' => $warrantyExpiryDate,
            ':condition_status' => $conditionStatus,
            ':current_location' => $currentLocation,
            ':status' => $status,
        ]);

        $newId = (int)$this->pdo->lastInsertId();

        $auditUser = $this->resolveAuditUserId($userId, $organizationId);
        if ($this->auditLogService && $auditUser) {
            try {
                $this->auditLogService->log(
                    $organizationId,
                    $auditUser,
                    'create',
                    'equipment',
                    $newId,
                    null,
                    [
                        'asset_code' => $assetCode,
                        'equipment_name' => $equipmentName,
                        'status' => $status,
                        'condition_status' => $conditionStatus,
                    ]
                );
            } catch (\Throwable $e) {
                // Ignore audit failure
            }
        }

        return $this->getEquipment($organizationId, $newId);
    }

    /**
     * Update an equipment item.
     */
    public function updateEquipment(int $organizationId, int $id, array $data, ?int $userId = null): bool
    {
        if (!$this->pdo) return false;

        $existing = $this->getEquipment($organizationId, $id);
        if (!$existing) {
            throw new InvalidArgumentException("Equipment not found or access denied.");
        }

        $equipmentName = isset($data['equipment_name']) ? trim($data['equipment_name']) : $existing['equipment_name'];
        if (empty($equipmentName)) {
            throw new InvalidArgumentException("Equipment name cannot be empty.");
        }

        $assetCode = isset($data['asset_code']) ? trim($data['asset_code']) : $existing['asset_code'];
        if ($assetCode !== $existing['asset_code']) {
            $checkStmt = $this->pdo->prepare("
                SELECT id FROM equipment 
                WHERE organization_id = :org_id AND asset_code = :code AND id != :id AND deleted_at IS NULL
                LIMIT 1
            ");
            $checkStmt->execute([':org_id' => $organizationId, ':code' => $assetCode, ':id' => $id]);
            if ($checkStmt->fetchColumn()) {
                throw new InvalidArgumentException("An equipment item with asset code '{$assetCode}' already exists.");
            }
        }

        $serialNumber = array_key_exists('serial_number', $data) 
            ? (!empty($data['serial_number']) ? trim($data['serial_number']) : null)
            : $existing['serial_number'];

        if ($serialNumber !== null && $serialNumber !== $existing['serial_number']) {
            $snStmt = $this->pdo->prepare("
                SELECT id FROM equipment 
                WHERE organization_id = :org_id AND serial_number = :sn AND id != :id AND deleted_at IS NULL
                LIMIT 1
            ");
            $snStmt->execute([':org_id' => $organizationId, ':sn' => $serialNumber, ':id' => $id]);
            if ($snStmt->fetchColumn()) {
                throw new InvalidArgumentException("An equipment item with serial number '{$serialNumber}' already exists.");
            }
        }

        $inventoryItemId = array_key_exists('inventory_item_id', $data)
            ? (!empty($data['inventory_item_id']) ? (int)$data['inventory_item_id'] : null)
            : $existing['inventory_item_id'];

        if ($inventoryItemId !== null && $inventoryItemId !== $existing['inventory_item_id']) {
            $itemStmt = $this->pdo->prepare("
                SELECT id FROM inventory_items 
                WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
                LIMIT 1
            ");
            $itemStmt->execute([':id' => $inventoryItemId, ':org_id' => $organizationId]);
            if (!$itemStmt->fetchColumn()) {
                throw new InvalidArgumentException("The linked inventory item does not exist or belongs to another organization.");
            }
        }

        $modelNumber = array_key_exists('model_number', $data)
            ? (!empty($data['model_number']) ? trim($data['model_number']) : null)
            : $existing['model_number'];

        $manufacturer = array_key_exists('manufacturer', $data)
            ? (!empty($data['manufacturer']) ? trim($data['manufacturer']) : null)
            : $existing['manufacturer'];

        $currentLocation = array_key_exists('current_location', $data)
            ? (!empty($data['current_location']) ? trim($data['current_location']) : 'Main Storage')
            : $existing['current_location'];

        $purchaseDate = array_key_exists('purchase_date', $data)
            ? (!empty($data['purchase_date']) ? trim($data['purchase_date']) : null)
            : $existing['purchase_date'];

        $warrantyExpiryDate = array_key_exists('warranty_expiry_date', $data)
            ? (!empty($data['warranty_expiry_date']) ? trim($data['warranty_expiry_date']) : null)
            : $existing['warranty_expiry_date'];

        $purchaseCost = array_key_exists('purchase_cost', $data)
            ? ($data['purchase_cost'] !== '' && $data['purchase_cost'] !== null ? max(0, (float)$data['purchase_cost']) : null)
            : $existing['purchase_cost'];

        $conditionStatus = isset($data['condition_status']) ? trim($data['condition_status']) : $existing['condition_status'];
        $allowedConditions = ['new', 'good', 'damaged', 'under_maintenance', 'lost', 'disposed'];
        if (!in_array($conditionStatus, $allowedConditions, true)) {
            $conditionStatus = $existing['condition_status'];
        }

        $status = isset($data['status']) ? trim($data['status']) : $existing['status'];
        $allowedStatuses = ['available', 'assigned', 'maintenance', 'lost', 'disposed'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = $existing['status'];
        }

        // If currently assigned, prevent manually reverting status to available without return flow
        if ($existing['status'] === 'assigned' && $status === 'available' && !empty($existing['active_assignment'])) {
            throw new InvalidArgumentException("Cannot mark assigned equipment as 'available'. Please process an Equipment Return instead.");
        }

        $stmt = $this->pdo->prepare("
            UPDATE equipment SET
                inventory_item_id = :inv_item_id,
                asset_code = :asset_code,
                equipment_name = :eq_name,
                serial_number = :serial_number,
                model_number = :model_number,
                manufacturer = :manufacturer,
                purchase_date = :purchase_date,
                purchase_cost = :purchase_cost,
                warranty_expiry_date = :warranty_expiry_date,
                condition_status = :condition_status,
                current_location = :current_location,
                status = :status,
                updated_at = NOW()
            WHERE id = :id AND organization_id = :org_id
        ");

        $ok = $stmt->execute([
            ':inv_item_id' => $inventoryItemId,
            ':asset_code' => $assetCode,
            ':eq_name' => $equipmentName,
            ':serial_number' => $serialNumber,
            ':model_number' => $modelNumber,
            ':manufacturer' => $manufacturer,
            ':purchase_date' => $purchaseDate,
            ':purchase_cost' => $purchaseCost,
            ':warranty_expiry_date' => $warrantyExpiryDate,
            ':condition_status' => $conditionStatus,
            ':current_location' => $currentLocation,
            ':status' => $status,
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
                    'equipment',
                    $id,
                    $existing,
                    ['status' => $status, 'condition_status' => $conditionStatus, 'equipment_name' => $equipmentName]
                );
            } catch (\Throwable $e) {}
        }

        return $ok;
    }

    /**
     * Soft delete an equipment item.
     */
    public function deleteEquipment(int $organizationId, int $id, ?int $userId = null): bool
    {
        if (!$this->pdo) return false;

        $existing = $this->getEquipment($organizationId, $id);
        if (!$existing) {
            throw new InvalidArgumentException("Equipment not found or access denied.");
        }

        if ($existing['status'] === 'assigned') {
            throw new InvalidArgumentException("Cannot delete equipment while it is actively assigned. Return it first.");
        }

        $stmt = $this->pdo->prepare("
            UPDATE equipment 
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
                    'equipment',
                    $id,
                    ['asset_code' => $existing['asset_code'], 'name' => $existing['equipment_name']],
                    null
                );
            } catch (\Throwable $e) {}
        }

        return $ok;
    }

    /**
     * Assign equipment to a supported entity (athlete, coach, employee, team, venue).
     */
    public function assignEquipment(int $organizationId, int $equipmentId, array $data, ?int $userId = null): array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $this->pdo->beginTransaction();
        try {
            // Lock equipment parent row
            $lockStmt = $this->pdo->prepare("
                SELECT * FROM equipment 
                WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
                FOR UPDATE
            ");
            $lockStmt->execute([':id' => $equipmentId, ':org_id' => $organizationId]);
            $equipment = $lockStmt->fetch(PDO::FETCH_ASSOC);

            if (!$equipment) {
                throw new InvalidArgumentException("Equipment not found or access denied.");
            }

            if ($equipment['status'] !== 'available') {
                throw new InvalidArgumentException("Equipment is currently '{$equipment['status']}' and cannot be assigned. It must be 'available'.");
            }

            $assigneeType = trim($data['assignee_type'] ?? '');
            $allowedAssigneeTypes = ['athlete', 'coach', 'employee', 'team', 'venue'];
            if (!in_array($assigneeType, $allowedAssigneeTypes, true)) {
                throw new InvalidArgumentException("Invalid assignee type. Allowed: athlete, coach, employee, team, venue.");
            }

            $athleteId = null;
            $coachId = null;
            $employeeId = null;
            $teamId = null;
            $venueId = null;
            $assigneeDisplayName = '';

            switch ($assigneeType) {
                case 'athlete':
                    $athleteId = !empty($data['athlete_id']) ? (int)$data['athlete_id'] : null;
                    if (!$athleteId) throw new InvalidArgumentException("Athlete ID is required for athlete assignment.");
                    $valStmt = $this->pdo->prepare("
                        SELECT CONCAT(first_name, ' ', last_name) FROM athletes 
                        WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
                    ");
                    $valStmt->execute([':id' => $athleteId, ':org_id' => $organizationId]);
                    $assigneeDisplayName = $valStmt->fetchColumn();
                    if (!$assigneeDisplayName) {
                        throw new InvalidArgumentException("Selected athlete does not exist or belongs to another organization.");
                    }
                    break;

                case 'coach':
                    $coachId = !empty($data['coach_id']) ? (int)$data['coach_id'] : null;
                    if (!$coachId) throw new InvalidArgumentException("Coach ID is required for coach assignment.");
                    $valStmt = $this->pdo->prepare("
                        SELECT CONCAT(e.first_name, ' ', e.last_name) 
                        FROM coach_profiles cp
                        JOIN employees e ON cp.employee_id = e.id
                        WHERE cp.id = :id AND cp.organization_id = :org_id AND cp.deleted_at IS NULL
                    ");
                    $valStmt->execute([':id' => $coachId, ':org_id' => $organizationId]);
                    $assigneeDisplayName = $valStmt->fetchColumn();
                    if (!$assigneeDisplayName) {
                        throw new InvalidArgumentException("Selected coach does not exist or belongs to another organization.");
                    }
                    break;

                case 'employee':
                    $employeeId = !empty($data['employee_id']) ? (int)$data['employee_id'] : null;
                    if (!$employeeId) throw new InvalidArgumentException("Employee ID is required for employee assignment.");
                    $valStmt = $this->pdo->prepare("
                        SELECT CONCAT(first_name, ' ', last_name) FROM employees 
                        WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
                    ");
                    $valStmt->execute([':id' => $employeeId, ':org_id' => $organizationId]);
                    $assigneeDisplayName = $valStmt->fetchColumn();
                    if (!$assigneeDisplayName) {
                        throw new InvalidArgumentException("Selected employee does not exist or belongs to another organization.");
                    }
                    break;

                case 'team':
                    $teamId = !empty($data['team_id']) ? (int)$data['team_id'] : null;
                    if (!$teamId) throw new InvalidArgumentException("Team ID is required for team assignment.");
                    $valStmt = $this->pdo->prepare("
                        SELECT name FROM teams 
                        WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
                    ");
                    $valStmt->execute([':id' => $teamId, ':org_id' => $organizationId]);
                    $assigneeDisplayName = $valStmt->fetchColumn();
                    if (!$assigneeDisplayName) {
                        throw new InvalidArgumentException("Selected team does not exist or belongs to another organization.");
                    }
                    break;

                case 'venue':
                    $venueId = !empty($data['venue_id']) ? (int)$data['venue_id'] : null;
                    if (!$venueId) throw new InvalidArgumentException("Venue ID is required for venue assignment.");
                    $valStmt = $this->pdo->prepare("
                        SELECT name FROM venues 
                        WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
                    ");
                    $valStmt->execute([':id' => $venueId, ':org_id' => $organizationId]);
                    $assigneeDisplayName = $valStmt->fetchColumn();
                    if (!$assigneeDisplayName) {
                        throw new InvalidArgumentException("Selected venue does not exist or belongs to another organization.");
                    }
                    break;
            }

            $assignedDate = !empty($data['assigned_date']) ? trim($data['assigned_date']) : date('Y-m-d');
            $expectedReturnDate = !empty($data['expected_return_date']) ? trim($data['expected_return_date']) : null;
            $conditionOnIssue = !empty($data['condition_on_issue']) ? trim($data['condition_on_issue']) : $equipment['condition_status'];
            $notes = !empty($data['notes']) ? trim($data['notes']) : null;

            $auditUser = $this->resolveAuditUserId($userId, $organizationId);

            // Insert assignment record
            $insStmt = $this->pdo->prepare("
                INSERT INTO equipment_assignments (
                    organization_id, equipment_id, assignee_type,
                    athlete_id, coach_id, employee_id, team_id, venue_id,
                    assigned_date, expected_return_date, returned_date,
                    condition_on_issue, condition_on_return, status,
                    issued_by, received_by, notes, created_at, updated_at
                ) VALUES (
                    :org_id, :eq_id, :assignee_type,
                    :athlete_id, :coach_id, :employee_id, :team_id, :venue_id,
                    :assigned_date, :expected_return_date, NULL,
                    :condition_on_issue, NULL, 'assigned',
                    :issued_by, NULL, :notes, NOW(), NOW()
                )
            ");

            $insStmt->execute([
                ':org_id' => $organizationId,
                ':eq_id' => $equipmentId,
                ':assignee_type' => $assigneeType,
                ':athlete_id' => $athleteId,
                ':coach_id' => $coachId,
                ':employee_id' => $employeeId,
                ':team_id' => $teamId,
                ':venue_id' => $venueId,
                ':assigned_date' => $assignedDate,
                ':expected_return_date' => $expectedReturnDate,
                ':condition_on_issue' => $conditionOnIssue,
                ':issued_by' => $auditUser,
                ':notes' => $notes,
            ]);

            $assignmentId = (int)$this->pdo->lastInsertId();

            // Update equipment status and location context
            $newLocation = "In Use: {$assigneeDisplayName} ({$assigneeType})";
            $updStmt = $this->pdo->prepare("
                UPDATE equipment SET 
                    status = 'assigned',
                    current_location = :loc,
                    updated_at = NOW()
                WHERE id = :id AND organization_id = :org_id
            ");
            $updStmt->execute([
                ':loc' => $newLocation,
                ':id' => $equipmentId,
                ':org_id' => $organizationId,
            ]);

            // Audit log
            if ($this->auditLogService && $auditUser) {
                try {
                    $this->auditLogService->log(
                        $organizationId,
                        $auditUser,
                        'assign',
                        'equipment',
                        $equipmentId,
                        ['status' => 'available'],
                        [
                            'status' => 'assigned',
                            'assignment_id' => $assignmentId,
                            'assignee_type' => $assigneeType,
                            'assignee' => $assigneeDisplayName
                        ]
                    );
                } catch (\Throwable $e) {}
            }

            $this->pdo->commit();

            return [
                'assignment_id' => $assignmentId,
                'equipment_id' => $equipmentId,
                'status' => 'assigned',
                'assignee_type' => $assigneeType,
                'assignee_name' => $assigneeDisplayName,
                'assigned_date' => $assignedDate,
                'expected_return_date' => $expectedReturnDate,
                'condition_on_issue' => $conditionOnIssue,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Return assigned equipment and update condition / status.
     */
    public function returnEquipment(int $organizationId, int $equipmentId, array $data, ?int $userId = null): array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $this->pdo->beginTransaction();
        try {
            // Lock equipment parent row
            $lockStmt = $this->pdo->prepare("
                SELECT * FROM equipment 
                WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
                FOR UPDATE
            ");
            $lockStmt->execute([':id' => $equipmentId, ':org_id' => $organizationId]);
            $equipment = $lockStmt->fetch(PDO::FETCH_ASSOC);

            if (!$equipment) {
                throw new InvalidArgumentException("Equipment not found or access denied.");
            }

            // Find active assignment
            $assignStmt = $this->pdo->prepare("
                SELECT * FROM equipment_assignments
                WHERE equipment_id = :eq_id AND organization_id = :org_id AND status = 'assigned'
                ORDER BY id DESC 
                LIMIT 1
                FOR UPDATE
            ");
            $assignStmt->execute([':eq_id' => $equipmentId, ':org_id' => $organizationId]);
            $assignment = $assignStmt->fetch(PDO::FETCH_ASSOC);

            if (!$assignment) {
                throw new InvalidArgumentException("Equipment is not currently assigned.");
            }

            $returnedDate = !empty($data['returned_date']) ? trim($data['returned_date']) : date('Y-m-d');
            $conditionOnReturn = !empty($data['condition_on_return']) ? trim($data['condition_on_return']) : 'good';
            $returnNotes = !empty($data['notes']) ? trim($data['notes']) : null;
            $returnLocation = !empty($data['return_location']) ? trim($data['return_location']) : 'Main Storage';

            $auditUser = $this->resolveAuditUserId($userId, $organizationId);

            // Determine assignment status and equipment status based on return condition / input
            $returnStatus = trim($data['status'] ?? '');
            if (empty($returnStatus)) {
                if (in_array(strtolower($conditionOnReturn), ['lost', 'missing'], true)) {
                    $returnStatus = 'lost';
                } elseif (in_array(strtolower($conditionOnReturn), ['damaged', 'broken', 'repair_needed'], true)) {
                    $returnStatus = 'damaged';
                } else {
                    $returnStatus = 'returned';
                }
            }

            $assignmentStatus = 'returned';
            $equipmentStatus = 'available';
            $equipmentCondition = $equipment['condition_status'];

            if ($returnStatus === 'lost') {
                $assignmentStatus = 'lost';
                $equipmentStatus = 'lost';
                $equipmentCondition = 'lost';
            } elseif ($returnStatus === 'damaged') {
                $assignmentStatus = 'damaged';
                $equipmentStatus = 'maintenance';
                $equipmentCondition = 'damaged';
            } else {
                $assignmentStatus = 'returned';
                $equipmentStatus = 'available';
                // Map condition string to enum if valid
                if (in_array($conditionOnReturn, ['new', 'good', 'damaged', 'under_maintenance', 'lost', 'disposed'], true)) {
                    $equipmentCondition = $conditionOnReturn;
                } else {
                    $equipmentCondition = 'good';
                }
            }

            // Append return notes to assignment notes
            $fullNotes = $assignment['notes'];
            if ($returnNotes) {
                $fullNotes = $fullNotes ? $fullNotes . "\n[Return Note]: " . $returnNotes : "[Return Note]: " . $returnNotes;
            }

            // Update assignment record
            $updAssignStmt = $this->pdo->prepare("
                UPDATE equipment_assignments SET
                    returned_date = :ret_date,
                    condition_on_return = :cond_ret,
                    status = :status,
                    received_by = :received_by,
                    notes = :notes,
                    updated_at = NOW()
                WHERE id = :id AND organization_id = :org_id
            ");
            $updAssignStmt->execute([
                ':ret_date' => $returnedDate,
                ':cond_ret' => $conditionOnReturn,
                ':status' => $assignmentStatus,
                ':received_by' => $auditUser,
                ':notes' => $fullNotes,
                ':id' => $assignment['id'],
                ':org_id' => $organizationId,
            ]);

            // Update equipment record
            $updEqStmt = $this->pdo->prepare("
                UPDATE equipment SET
                    status = :status,
                    condition_status = :condition_status,
                    current_location = :loc,
                    updated_at = NOW()
                WHERE id = :id AND organization_id = :org_id
            ");
            $updEqStmt->execute([
                ':status' => $equipmentStatus,
                ':condition_status' => $equipmentCondition,
                ':loc' => $returnLocation,
                ':id' => $equipmentId,
                ':org_id' => $organizationId,
            ]);

            // Audit log
            if ($this->auditLogService && $auditUser) {
                try {
                    $this->auditLogService->log(
                        $organizationId,
                        $auditUser,
                        'return',
                        'equipment',
                        $equipmentId,
                        ['status' => 'assigned'],
                        [
                            'status' => $equipmentStatus,
                            'condition_status' => $equipmentCondition,
                            'assignment_id' => $assignment['id'],
                            'assignment_status' => $assignmentStatus,
                            'condition_on_return' => $conditionOnReturn,
                        ]
                    );
                } catch (\Throwable $e) {}
            }

            $this->pdo->commit();

            return [
                'equipment_id' => $equipmentId,
                'assignment_id' => $assignment['id'],
                'equipment_status' => $equipmentStatus,
                'condition_status' => $equipmentCondition,
                'assignment_status' => $assignmentStatus,
                'returned_date' => $returnedDate,
                'condition_on_return' => $conditionOnReturn,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Get assignment history ledger for an equipment item.
     */
    public function getAssignmentHistory(int $organizationId, int $equipmentId): array
    {
        if (!$this->pdo) return [];

        $stmt = $this->pdo->prepare("
            SELECT 
                ea.*,
                CONCAT(u_iss.first_name, ' ', u_iss.last_name) as issued_by_name,
                CONCAT(u_rec.first_name, ' ', u_rec.last_name) as received_by_name,
                CASE 
                    WHEN ea.assignee_type = 'athlete' THEN CONCAT(ath.first_name, ' ', ath.last_name)
                    WHEN ea.assignee_type = 'coach' THEN (
                        SELECT CONCAT(emp_c.first_name, ' ', emp_c.last_name) 
                        FROM coach_profiles cp 
                        JOIN employees emp_c ON cp.employee_id = emp_c.id 
                        WHERE cp.id = ea.coach_id
                    )
                    WHEN ea.assignee_type = 'employee' THEN CONCAT(emp.first_name, ' ', emp.last_name)
                    WHEN ea.assignee_type = 'team' THEN tm.name
                    WHEN ea.assignee_type = 'venue' THEN vn.name
                    ELSE NULL
                END as assignee_name,
                CASE 
                    WHEN ea.assignee_type = 'athlete' THEN ath.athlete_code
                    WHEN ea.assignee_type = 'coach' THEN (
                        SELECT cp.coach_code 
                        FROM coach_profiles cp 
                        WHERE cp.id = ea.coach_id
                    )
                    WHEN ea.assignee_type = 'employee' THEN emp.employee_code
                    WHEN ea.assignee_type = 'team' THEN tm.team_code
                    WHEN ea.assignee_type = 'venue' THEN vn.venue_code
                    ELSE NULL
                END as assignee_code
            FROM equipment_assignments ea
            LEFT JOIN users u_iss ON ea.issued_by = u_iss.id
            LEFT JOIN users u_rec ON ea.received_by = u_rec.id
            LEFT JOIN athletes ath ON ea.athlete_id = ath.id
            LEFT JOIN employees emp ON ea.employee_id = emp.id
            LEFT JOIN teams tm ON ea.team_id = tm.id
            LEFT JOIN venues vn ON ea.venue_id = vn.id
            WHERE ea.equipment_id = :eq_id AND ea.organization_id = :org_id
            ORDER BY ea.id DESC
            LIMIT 50
        ");
        $stmt->execute([':eq_id' => $equipmentId, ':org_id' => $organizationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Generate unique asset code per organization: EQP-YYYYMMDD-XXXX
     */
    protected function generateAssetCode(int $organizationId): string
    {
        $prefix = 'EQP-' . date('Ymd') . '-';
        $attempts = 0;
        do {
            $code = $prefix . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $this->pdo->prepare("
                SELECT id FROM equipment 
                WHERE organization_id = :org_id AND asset_code = :code AND deleted_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([':org_id' => $organizationId, ':code' => $code]);
            $exists = $stmt->fetchColumn();
            $attempts++;
        } while ($exists && $attempts < 15);

        return $code;
    }

    /**
     * Check for overdue equipment assignments and trigger automated return alerts.
     */
    public function checkOverdueEquipmentAlerts(int $organizationId): array
    {
        $notifService = new \App\Services\Notification\NotificationService($this->pdo);
        return $notifService->checkAndTriggerOverdueEquipment($organizationId);
    }
}
