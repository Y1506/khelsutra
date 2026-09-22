<?php

namespace App\Services\Leave;

use App\Services\BaseService;
use App\Services\Audit\AuditLogService;
use PDO;
use Exception;

class LeaveService extends BaseService
{
    protected AuditLogService $auditLog;

    /**
     * Create a new leave service instance.
     *
     * @param PDO|null $pdo Optional PDO instance
     * @param AuditLogService|null $auditLog Optional audit log service instance
     */
    public function __construct(?PDO $pdo = null, ?AuditLogService $auditLog = null)
    {
        parent::__construct($pdo);
        $this->auditLog = $auditLog ?? new AuditLogService($this->pdo);
    }

    /**
     * Get all active leave types for an organization.
     *
     * @param int $orgId The organization ID
     * @return array Array of leave type records
     */
    public function listLeaveTypes(int $orgId): array
    {
        if (!$this->pdo) return [];
        $stmt = $this->pdo->prepare("SELECT * FROM leave_types WHERE organization_id = :org_id AND status = 'active' ORDER BY name ASC");
        $stmt->execute([':org_id' => $orgId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Create a new leave type for an organization.
     *
     * @param int $orgId The organization ID
     * @param array $data The leave type data including name, description, max_days_per_year
     * @param int|null $performedBy The user ID performing this action (for audit log)
     * @return array|null The created leave type record or null on failure
     */
    public function createLeaveType(int $orgId, array $data, ?int $performedBy = null): ?array
    {
        if (!$this->pdo || empty($data['name'])) return null;

        $stmt = $this->pdo->prepare("
            INSERT INTO leave_types (organization_id, name, description, max_days_per_year, status, created_at, updated_at)
            VALUES (:org_id, :name, :desc, :max_days, :status, NOW(), NOW())
        ");
        $stmt->execute([
            ':org_id' => $orgId,
            ':name' => trim($data['name']),
            ':desc' => $data['description'] ?? null,
            ':max_days' => isset($data['max_days_per_year']) ? (float)$data['max_days_per_year'] : null,
            ':status' => $data['status'] ?? 'active',
        ]);
        $newId = (int)$this->pdo->lastInsertId();

        $this->auditLog->log($orgId, $performedBy, 'CREATE', 'LeaveType', 'leave_types', $newId, null, $data, "Created leave type {$data['name']}");

        $stmt = $this->pdo->prepare("SELECT * FROM leave_types WHERE id = :id AND organization_id = :org_id");
        $stmt->execute([':id' => $newId, ':org_id' => $orgId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get a paginated list of leave requests with optional status filter.
     *
     * @param int $orgId The organization ID
     * @param string|null $status Optional status filter (pending, approved, rejected, cancelled)
     * @param int $limit The number of records to return (default 50)
     * @param int $offset The offset for pagination (default 0)
     * @return array Array of leave request records with related data
     */
    public function listLeaveRequests(int $orgId, ?string $status = null, int $limit = 50, int $offset = 0): array
    {
        if (!$this->pdo) return [];

        $sql = "
            SELECT lr.*, lt.name as leave_type_name,
                   CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_code,
                   CONCAT(a.first_name, ' ', a.last_name) as athlete_name, a.athlete_code,
                   CONCAT(u.first_name, ' ', u.last_name) as approver_name
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            LEFT JOIN employees e ON lr.employee_id = e.id
            LEFT JOIN athletes a ON lr.athlete_id = a.id
            LEFT JOIN users u ON lr.approved_by = u.id
            WHERE lr.organization_id = :org_id
        ";
        $params = [':org_id' => $orgId];

        if ($status) {
            $sql .= " AND lr.status = :status ";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY lr.id DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get a single leave request by ID.
     *
     * @param int $orgId The organization ID
     * @param int $id The leave request ID
     * @return array|null The leave request record with related data or null if not found
     */
    public function getLeaveRequest(int $orgId, int $id): ?array
    {
        if (!$this->pdo) return null;
        $stmt = $this->pdo->prepare("
            SELECT lr.*, lt.name as leave_type_name,
                   CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_code,
                   CONCAT(a.first_name, ' ', a.last_name) as athlete_name, a.athlete_code,
                   CONCAT(u.first_name, ' ', u.last_name) as approver_name
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            LEFT JOIN employees e ON lr.employee_id = e.id
            LEFT JOIN athletes a ON lr.athlete_id = a.id
            LEFT JOIN users u ON lr.approved_by = u.id
            WHERE lr.id = :id AND lr.organization_id = :org_id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':org_id' => $orgId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get leave request details with validation that it exists.
     *
     * @param int $id The leave request ID
     * @param int $orgId The organization ID
     * @return array|null The leave request record
     * @throws \Exception If leave request not found
     */
    public function getLeaveRequestDetails(int $id, int $orgId): ?array
    {
        $res = $this->getLeaveRequest($orgId, $id);
        if (!$res) {
            throw new \Exception("Leave request #{$id} not found in organisation #{$orgId}.");
        }
        return $res;
    }

    /**
     * Submit a new leave application with validation.
     * Validates date ranges, checks for overlaps, and enforces business rules.
     *
     * @param int $orgId The organization ID
     * @param array $data The leave application data including start_date, end_date, leave_type_id, applicant details
     * @param int|null $performedBy The user ID performing this action (for audit log)
     * @return array|null The created leave request record or null on failure
     * @throws Exception If validation fails (invalid dates, overlapping requests, etc.)
     */
    public function applyLeave(int $orgId, array $data, ?int $performedBy = null): ?array
    {
        if (!$this->pdo) return null;

        $startDate = $data['start_date'] ?? null;
        $endDate   = $data['end_date'] ?? null;
        $typeId    = (int)($data['leave_type_id'] ?? 0);
        $applicantType = $data['applicant_type'] ?? 'employee'; // 'employee','athlete'

        if (!$startDate || !$endDate || !$typeId) {
            return null;
        }

        // Validation 1: End date cannot be before start date
        if (strtotime($endDate) < strtotime($startDate)) {
            throw new Exception("End date cannot be earlier than start date.");
        }

        // Calculate total days
        $daysDiff = (strtotime($endDate) - strtotime($startDate)) / 86400 + 1;
        $totalDays = (float)($data['total_days'] ?? $daysDiff);
        if ($totalDays <= 0) {
            throw new Exception("Total days must be greater than zero.");
        }

        // Participant check
        $employeeId = ($applicantType === 'employee' && !empty($data['employee_id'])) ? (int)$data['employee_id'] : null;
        $athleteId  = ($applicantType === 'athlete' && !empty($data['athlete_id'])) ? (int)$data['athlete_id'] : null;

        if (!$employeeId && !$athleteId) {
            throw new Exception("Applicant identifier is required.");
        }

        // Validation 2: Check for overlapping pending or approved leave requests
        $overlapSql = "
            SELECT id FROM leave_requests
            WHERE organization_id = :org_id
              AND status IN ('pending', 'approved')
              AND ((start_date <= :end_date AND end_date >= :start_date))
        ";
        $overlapParams = [
            ':org_id' => $orgId,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ];
        if ($employeeId) {
            $overlapSql .= " AND employee_id = :emp_id";
            $overlapParams[':emp_id'] = $employeeId;
        } else {
            $overlapSql .= " AND athlete_id = :ath_id";
            $overlapParams[':ath_id'] = $athleteId;
        }

        $overlapStmt = $this->pdo->prepare($overlapSql);
        $overlapStmt->execute($overlapParams);
        if ($overlapStmt->fetchColumn()) {
            throw new Exception("An overlapping leave request already exists for this period.");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO leave_requests (
                organization_id, applicant_type, employee_id, athlete_id, leave_type_id,
                start_date, end_date, total_days, reason, attachment_path, status,
                created_at, updated_at
            ) VALUES (
                :org_id, :app_type, :emp_id, :ath_id, :type_id,
                :start_date, :end_date, :total_days, :reason, :attach, 'pending',
                NOW(), NOW()
            )
        ");

        $stmt->execute([
            ':org_id' => $orgId,
            ':app_type' => $applicantType,
            ':emp_id' => $employeeId,
            ':ath_id' => $athleteId,
            ':type_id' => $typeId,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':total_days' => $totalDays,
            ':reason' => $data['reason'] ?? null,
            ':attach' => $data['attachment_path'] ?? null,
        ]);

        $newId = (int)$this->pdo->lastInsertId();

        $this->auditLog->log($orgId, $performedBy, 'LEAVE_APPLY', 'Leave', 'leave_requests', $newId, null, $data, "Applied leave for {$applicantType} ({$startDate} to {$endDate})");

        return $this->getLeaveRequest($orgId, $newId);
    }

    /**
     * Review and approve/reject a leave request.
     * Enforces separation of duties - applicant cannot approve their own request.
     *
     * @param int $orgId The organization ID
     * @param int $leaveId The leave request ID
     * @param string $status The new status (approved, rejected, or cancelled)
     * @param string|null $rejectionReason The reason for rejection (required if status is rejected)
     * @param int $approverUserId The user ID of the approver
     * @return bool True if review succeeded, false otherwise
     * @throws Exception If separation of duties is violated
     */
    public function reviewLeave(int $orgId, int $leaveId, string $status, ?string $rejectionReason, int $approverUserId): bool
    {
        if (!$this->pdo || !in_array($status, ['approved', 'rejected', 'cancelled'], true)) {
            return false;
        }

        $existing = $this->getLeaveRequest($orgId, $leaveId);
        if (!$existing || $existing['status'] !== 'pending') {
            return false;
        }

        // Validation 3: Applicant cannot approve their own request
        $applicantUserId = null;
        if (!empty($existing['employee_id'])) {
            $stmt = $this->pdo->prepare("SELECT user_id FROM employees WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $existing['employee_id']]);
            $applicantUserId = $stmt->fetchColumn();
        } elseif (!empty($existing['athlete_id'])) {
            $stmt = $this->pdo->prepare("SELECT user_id FROM athletes WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $existing['athlete_id']]);
            $applicantUserId = $stmt->fetchColumn();
        }

        if ($applicantUserId && (int)$applicantUserId === $approverUserId) {
            throw new Exception("Separation of duties: An applicant cannot approve or reject their own leave request.");
        }

        $stmt = $this->pdo->prepare("
            UPDATE leave_requests SET
                status = :status,
                approved_by = :approver,
                approved_at = NOW(),
                rejection_reason = :rejection_reason,
                updated_at = NOW()
            WHERE id = :id AND organization_id = :org_id
        ");

        $ok = $stmt->execute([
            ':status' => $status,
            ':approver' => $approverUserId,
            ':rejection_reason' => ($status === 'rejected' ? $rejectionReason : null),
            ':id' => $leaveId,
            ':org_id' => $orgId,
        ]);

        if ($ok) {
            $action = ($status === 'approved') ? 'LEAVE_APPROVE' : 'LEAVE_REJECT';
            $this->auditLog->log($orgId, $approverUserId, $action, 'Leave', 'leave_requests', $leaveId, ['status' => 'pending'], ['status' => $status], "Leave request #{$leaveId} marked as {$status}");
        }

        return $ok;
    }
}
