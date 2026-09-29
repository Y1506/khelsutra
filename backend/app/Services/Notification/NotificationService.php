<?php

namespace App\Services\Notification;

use App\Services\BaseService;
use App\Services\Audit\AuditLogService;
use PDO;
use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * Service managing persistent database-backed notifications and automated alerts.
 * Strictly respects organization and tenant isolation.
 * Schema-aligned with the `notifications` table (no updated_at column).
 */
class NotificationService extends BaseService
{
    protected ?AuditLogService $auditLogService = null;

    public function __construct(?PDO $pdo = null, ?AuditLogService $auditLogService = null)
    {
        parent::__construct($pdo);
        $this->auditLogService = $auditLogService ?: new AuditLogService($this->pdo);
    }

    /**
     * Create and persist a database-backed notification.
     * Enforces organization and user ownership.
     */
    public function createNotification(
        int $organizationId,
        int $userId,
        string $title,
        string $message,
        ?string $type = null,
        ?string $refType = null,
        ?int $refId = null,
        string $channel = 'in_app'
    ): array {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $title = trim($title);
        $message = trim($message);

        if (empty($title)) {
            throw new InvalidArgumentException("Notification title cannot be empty.");
        }
        if (empty($message)) {
            throw new InvalidArgumentException("Notification message cannot be empty.");
        }
        if (mb_strlen($title) > 200) {
            $title = substr($title, 0, 200);
        }

        $allowedChannels = ['in_app', 'push', 'email'];
        if (!in_array($channel, $allowedChannels, true)) {
            $channel = 'in_app';
        }

        // Verify user belongs to this organization
        $userCheck = $this->pdo->prepare("
            SELECT ou.user_id 
            FROM organization_users ou 
            WHERE ou.organization_id = :org_id AND ou.user_id = :user_id AND ou.access_status = 'active'
            LIMIT 1
        ");
        $userCheck->execute([':org_id' => $organizationId, ':user_id' => $userId]);
        if (!$userCheck->fetchColumn()) {
            // Also check if user exists globally (e.g. super admin)
            $globalCheck = $this->pdo->prepare("SELECT id FROM users WHERE id = :user_id LIMIT 1");
            $globalCheck->execute([':user_id' => $userId]);
            if (!$globalCheck->fetchColumn()) {
                throw new InvalidArgumentException("Recipient user #{$userId} does not exist or does not belong to organization #{$organizationId}.");
            }
        }

        $sql = "
            INSERT INTO notifications (
                organization_id, user_id, title, message,
                notification_type, reference_type, reference_id,
                channel, is_read, read_at, sent_at, created_at
            ) VALUES (
                :org_id, :user_id, :title, :message,
                :notif_type, :ref_type, :ref_id,
                :channel, 0, NULL, NOW(), NOW()
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':org_id' => $organizationId,
            ':user_id' => $userId,
            ':title' => $title,
            ':message' => $message,
            ':notif_type' => $type,
            ':ref_type' => $refType,
            ':ref_id' => $refId,
            ':channel' => $channel,
        ]);

        $id = (int)$this->pdo->lastInsertId();

        return $this->getNotification($organizationId, $id, $userId) ?? [
            'id' => $id,
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'notification_type' => $type,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'channel' => $channel,
            'is_read' => 0,
            'read_at' => null,
            'sent_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Backward-compatible wrapper around createNotification.
     */
    public function sendNotification(
        int $organizationId,
        int $userId,
        string $title,
        string $message,
        ?string $type = null,
        ?string $refType = null,
        ?int $refId = null,
        string $channel = 'in_app'
    ): array {
        return $this->createNotification($organizationId, $userId, $title, $message, $type, $refType, $refId, $channel);
    }

    /**
     * Get a single notification by ID with strict tenant and user isolation.
     */
    public function getNotification(int $organizationId, int $notificationId, int $userId): ?array
    {
        if (!$this->pdo) return null;

        $stmt = $this->pdo->prepare("
            SELECT * FROM notifications 
            WHERE id = :id AND organization_id = :org_id AND user_id = :user_id
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => $notificationId,
            ':org_id' => $organizationId,
            ':user_id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Fetch user notifications with pagination and tenant isolation.
     */
    public function getUserNotifications(
        int $organizationId,
        int $userId,
        int $page = 1,
        int $limit = 20,
        bool $unreadOnly = false
    ): array {
        if (!$this->pdo) {
            return ['data' => [], 'total' => 0, 'unread_count' => 0, 'page' => $page, 'limit' => $limit, 'total_pages' => 0];
        }

        $conditions = ["organization_id = :org_id", "user_id = :user_id"];
        $params = [
            ':org_id' => $organizationId,
            ':user_id' => $userId,
        ];

        if ($unreadOnly) {
            $conditions[] = "is_read = 0";
        }

        $whereClause = implode(' AND ', $conditions);

        // Count total matching
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Count unread
        $unreadCount = $this->getUnreadCount($organizationId, $userId);

        $page = max(1, $page);
        $limit = max(1, $limit);
        $offset = ($page - 1) * $limit;

        $sql = "
            SELECT * FROM notifications 
            WHERE {$whereClause}
            ORDER BY created_at DESC, id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'data' => $data,
            'total' => $total,
            'unread_count' => $unreadCount,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $limit > 0 ? (int)ceil($total / $limit) : 1,
        ];
    }

    /**
     * Count unread notifications for a user within an organization.
     */
    public function getUnreadCount(int $organizationId, int $userId): int
    {
        if (!$this->pdo) return 0;

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE organization_id = :org_id AND user_id = :user_id AND is_read = 0
        ");
        $stmt->execute([
            ':org_id' => $organizationId,
            ':user_id' => $userId,
        ]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Mark a single notification as read.
     * Enforces strict organization and recipient ownership.
     */
    public function markAsRead(int $organizationId, int $notificationId, int $userId): bool
    {
        if (!$this->pdo) return false;

        $stmt = $this->pdo->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW() 
            WHERE id = :id AND organization_id = :org_id AND user_id = :user_id
        ");
        $stmt->execute([
            ':id' => $notificationId,
            ':org_id' => $organizationId,
            ':user_id' => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Mark all unread notifications as read for a user in an organization.
     */
    public function markAllAsRead(int $organizationId, int $userId): int
    {
        if (!$this->pdo) return 0;

        $stmt = $this->pdo->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW() 
            WHERE organization_id = :org_id AND user_id = :user_id AND is_read = 0
        ");
        $stmt->execute([
            ':org_id' => $organizationId,
            ':user_id' => $userId,
        ]);

        return $stmt->rowCount();
    }

    /**
     * Delete a notification with tenant and recipient isolation.
     */
    public function deleteNotification(int $organizationId, int $notificationId, int $userId): bool
    {
        if (!$this->pdo) return false;

        $stmt = $this->pdo->prepare("
            DELETE FROM notifications 
            WHERE id = :id AND organization_id = :org_id AND user_id = :user_id
        ");
        $stmt->execute([
            ':id' => $notificationId,
            ':org_id' => $organizationId,
            ':user_id' => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Get active user IDs in an organization with specific roles.
     * Falls back to Sports Administrators or active admins if no role-specific users are found.
     */
    public function getTargetUserIdsForRoles(int $organizationId, array $roleNames): array
    {
        if (!$this->pdo) return [];

        $placeholders = implode(',', array_fill(0, count($roleNames), '?'));
        $sql = "
            SELECT DISTINCT ou.user_id 
            FROM organization_users ou
            JOIN roles r ON r.id = ou.role_id
            WHERE ou.organization_id = ?
              AND ou.access_status = 'active'
              AND r.name IN ({$placeholders})
        ";

        $params = array_merge([$organizationId], $roleNames);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        // If no matching users found for specific roles, fallback to Sports Administrator or active admin
        if (empty($userIds)) {
            $fallbackStmt = $this->pdo->prepare("
                SELECT DISTINCT ou.user_id 
                FROM organization_users ou
                JOIN roles r ON r.id = ou.role_id
                WHERE ou.organization_id = :org_id 
                  AND ou.access_status = 'active'
                  AND (r.name LIKE '%Admin%' OR r.id IN (1, 2))
                LIMIT 5
            ");
            $fallbackStmt->execute([':org_id' => $organizationId]);
            $userIds = $fallbackStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }

        // Final fallback: any active user in this organization
        if (empty($userIds)) {
            $anyStmt = $this->pdo->prepare("
                SELECT ou.user_id 
                FROM organization_users ou 
                WHERE ou.organization_id = :org_id AND ou.access_status = 'active'
                ORDER BY ou.user_id ASC LIMIT 1
            ");
            $anyStmt->execute([':org_id' => $organizationId]);
            $val = $anyStmt->fetchColumn();
            if ($val) {
                $userIds = [(int)$val];
            }
        }

        return array_map('intval', $userIds);
    }

    // =========================================================================
    // AUTOMATED ALERT 1: LOW STOCK ALERT
    // =========================================================================

    /**
     * Check stock level against minimum stock level and trigger notification.
     * Prevents duplicate unread notifications.
     */
    public function checkAndTriggerLowStock(int $organizationId, int $itemId): array
    {
        if (!$this->pdo) {
            return ['triggered' => false, 'reason' => 'Database connection unavailable'];
        }

        // Fetch item details
        $itemStmt = $this->pdo->prepare("
            SELECT id, item_code, item_name, quantity, minimum_stock_level, reorder_level, unit
            FROM inventory_items 
            WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
            LIMIT 1
        ");
        $itemStmt->execute([':id' => $itemId, ':org_id' => $organizationId]);
        $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            return ['triggered' => false, 'reason' => 'Inventory item not found'];
        }

        $qty = (float)$item['quantity'];
        $minStock = (float)$item['minimum_stock_level'];
        $reorderLevel = (float)$item['reorder_level'];

        // Condition: quantity <= minimum_stock_level
        // If minimum_stock_level is not set (> 0), fallback to reorder_level
        $threshold = $minStock > 0 ? $minStock : $reorderLevel;
        $isLowStock = ($qty <= $threshold);

        if (!$isLowStock) {
            return [
                'triggered' => false,
                'reason' => "Stock quantity ({$qty}) is above threshold ({$threshold})",
                'quantity' => $qty,
                'threshold' => $threshold,
            ];
        }

        // De-duplication check: avoid duplicate unread low stock notification for this item
        $dupStmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM notifications
            WHERE organization_id = :org_id
              AND notification_type = 'low_stock'
              AND reference_type = 'inventory_item'
              AND reference_id = :item_id
              AND is_read = 0
        ");
        $dupStmt->execute([':org_id' => $organizationId, ':item_id' => $itemId]);
        if ((int)$dupStmt->fetchColumn() > 0) {
            return [
                'triggered' => false,
                'reason' => 'Active unread low stock notification already exists for this item',
                'item_id' => $itemId,
                'quantity' => $qty,
            ];
        }

        // Target users: Inventory Manager and Sports Administrator
        $targetUserIds = $this->getTargetUserIdsForRoles($organizationId, ['Inventory Manager', 'Sports Administrator']);
        if (empty($targetUserIds)) {
            return ['triggered' => false, 'reason' => 'No active recipient users found for organization'];
        }

        $title = "Low Stock Alert: {$item['item_name']}";
        $message = "Stock level for '{$item['item_name']}' ({$item['item_code']}) is {$qty} {$item['unit']}, which is at or below the minimum threshold of {$threshold} {$item['unit']}. Please replenish stock.";

        $created = [];
        foreach ($targetUserIds as $uId) {
            $created[] = $this->createNotification(
                $organizationId,
                $uId,
                $title,
                $message,
                'low_stock',
                'inventory_item',
                $itemId,
                'in_app'
            );
        }

        return [
            'triggered' => true,
            'notifications_created' => count($created),
            'item_id' => $itemId,
            'item_name' => $item['item_name'],
            'quantity' => $qty,
            'threshold' => $threshold,
            'recipients' => $targetUserIds,
        ];
    }

    // =========================================================================
    // AUTOMATED ALERT 2: EQUIPMENT RETURN ALERT (OVERDUE)
    // =========================================================================

    /**
     * Check for overdue equipment assignments and trigger notifications.
     * Prevents duplicate unread notifications.
     */
    public function checkAndTriggerOverdueEquipment(int $organizationId): array
    {
        if (!$this->pdo) {
            return ['triggered' => false, 'count' => 0, 'reason' => 'Database connection unavailable'];
        }

        // Find active assignments where expected_return_date < current date
        $sql = "
            SELECT 
                ea.id as assignment_id,
                ea.equipment_id,
                ea.expected_return_date,
                ea.assignee_type,
                ea.athlete_id,
                ea.coach_id,
                ea.employee_id,
                eq.equipment_name,
                eq.asset_code,
                CASE 
                    WHEN ea.assignee_type = 'athlete' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM athletes WHERE id = ea.athlete_id)
                    WHEN ea.assignee_type = 'coach' THEN (
                        SELECT CONCAT(emp.first_name, ' ', emp.last_name) 
                        FROM coach_profiles cp JOIN employees emp ON cp.employee_id = emp.id 
                        WHERE cp.id = ea.coach_id
                    )
                    WHEN ea.assignee_type = 'employee' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM employees WHERE id = ea.employee_id)
                    WHEN ea.assignee_type = 'team' THEN (SELECT name FROM teams WHERE id = ea.team_id)
                    WHEN ea.assignee_type = 'venue' THEN (SELECT name FROM venues WHERE id = ea.venue_id)
                    ELSE 'Assigned User'
                END as assignee_name
            FROM equipment_assignments ea
            JOIN equipment eq ON eq.id = ea.equipment_id
            WHERE ea.organization_id = :org_id
              AND ea.status = 'assigned'
              AND ea.expected_return_date IS NOT NULL
              AND ea.expected_return_date < CURDATE()
            ORDER BY ea.expected_return_date ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':org_id' => $organizationId]);
        $overdueAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($overdueAssignments)) {
            return [
                'triggered' => false,
                'count' => 0,
                'message' => 'No overdue equipment assignments found.',
            ];
        }

        // Target managers
        $managerUserIds = $this->getTargetUserIdsForRoles($organizationId, ['Inventory Manager', 'Sports Administrator']);

        $triggeredCount = 0;
        $details = [];

        foreach ($overdueAssignments as $oa) {
            $assignmentId = (int)$oa['assignment_id'];

            // De-duplication: check if unread notification already exists for this assignment
            $dupStmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM notifications
                WHERE organization_id = :org_id
                  AND notification_type = 'equipment_overdue'
                  AND reference_type = 'equipment_assignment'
                  AND reference_id = :ref_id
                  AND is_read = 0
            ");
            $dupStmt->execute([':org_id' => $organizationId, ':ref_id' => $assignmentId]);
            if ((int)$dupStmt->fetchColumn() > 0) {
                continue; // Skip duplicate
            }

            $assigneeName = $oa['assignee_name'] ?: 'Unknown Assignee';
            $title = "Overdue Equipment Return: {$oa['equipment_name']}";
            $message = "Equipment '{$oa['equipment_name']}' ({$oa['asset_code']}) issued to {$assigneeName} was expected back on {$oa['expected_return_date']} and is overdue for return.";

            $recipients = $managerUserIds;

            // If assignee is an employee or athlete with a user account, also notify them
            if ($oa['assignee_type'] === 'employee' && !empty($oa['employee_id'])) {
                $uStmt = $this->pdo->prepare("SELECT user_id FROM organization_users WHERE organization_id = :org_id AND employee_id = :emp_id LIMIT 1");
                $uStmt->execute([':org_id' => $organizationId, ':emp_id' => $oa['employee_id']]);
                $uId = $uStmt->fetchColumn();
                if ($uId && !in_array((int)$uId, $recipients, true)) {
                    $recipients[] = (int)$uId;
                }
            } elseif ($oa['assignee_type'] === 'athlete' && !empty($oa['athlete_id'])) {
                $uStmt = $this->pdo->prepare("SELECT user_id FROM organization_users WHERE organization_id = :org_id AND athlete_id = :ath_id LIMIT 1");
                $uStmt->execute([':org_id' => $organizationId, ':ath_id' => $oa['athlete_id']]);
                $uId = $uStmt->fetchColumn();
                if ($uId && !in_array((int)$uId, $recipients, true)) {
                    $recipients[] = (int)$uId;
                }
            }

            foreach ($recipients as $uId) {
                $this->createNotification(
                    $organizationId,
                    $uId,
                    $title,
                    $message,
                    'equipment_overdue',
                    'equipment_assignment',
                    $assignmentId,
                    'in_app'
                );
            }

            $triggeredCount++;
            $details[] = [
                'assignment_id' => $assignmentId,
                'equipment_name' => $oa['equipment_name'],
                'asset_code' => $oa['asset_code'],
                'expected_return_date' => $oa['expected_return_date'],
                'assignee' => $assigneeName,
            ];
        }

        return [
            'triggered' => $triggeredCount > 0,
            'count' => $triggeredCount,
            'details' => $details,
        ];
    }

    // =========================================================================
    // AUTOMATED ALERT 3: BUDGET ALERT
    // =========================================================================

    /**
     * Check budget utilization against threshold percentage and trigger notification.
     * Prevents duplicate unread notifications.
     */
    public function checkAndTriggerBudgetThreshold(
        int $organizationId,
        int $budgetId,
        float $thresholdPercent = 90.0
    ): array {
        if (!$this->pdo) {
            return ['triggered' => false, 'reason' => 'Database connection unavailable'];
        }

        // Fetch budget
        $bStmt = $this->pdo->prepare("
            SELECT id, budget_name, financial_year, start_date, end_date, total_budget, status
            FROM budgets 
            WHERE id = :id AND organization_id = :org_id
            LIMIT 1
        ");
        $bStmt->execute([':id' => $budgetId, ':org_id' => $organizationId]);
        $budget = $bStmt->fetch(PDO::FETCH_ASSOC);

        if (!$budget) {
            return ['triggered' => false, 'reason' => 'Budget not found or access denied'];
        }

        $totalBudget = (float)$budget['total_budget'];
        if ($totalBudget <= 0) {
            return ['triggered' => false, 'reason' => 'Budget total is zero or negative'];
        }

        // Calculate actual spend for budget period
        $spendStmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(total_amount), 0)
            FROM expenses
            WHERE organization_id = :org_id
              AND expense_date BETWEEN :start_date AND :end_date
              AND payment_status NOT IN ('cancelled', 'rejected')
              AND deleted_at IS NULL
        ");
        $spendStmt->execute([
            ':org_id' => $organizationId,
            ':start_date' => $budget['start_date'],
            ':end_date' => $budget['end_date'],
        ]);
        $actualSpent = (float)$spendStmt->fetchColumn();

        $percentageSpent = round(($actualSpent / $totalBudget) * 100, 1);

        if ($percentageSpent < $thresholdPercent) {
            return [
                'triggered' => false,
                'reason' => "Utilization ({$percentageSpent}%) is below threshold ({$thresholdPercent}%)",
                'percentage_spent' => $percentageSpent,
                'threshold_percent' => $thresholdPercent,
                'total_spent' => $actualSpent,
                'total_budget' => $totalBudget,
            ];
        }

        // De-duplication check: avoid duplicate unread budget alert for this budget
        $dupStmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM notifications
            WHERE organization_id = :org_id
              AND notification_type = 'budget_alert'
              AND reference_type = 'budget'
              AND reference_id = :budget_id
              AND is_read = 0
        ");
        $dupStmt->execute([':org_id' => $organizationId, ':budget_id' => $budgetId]);
        if ((int)$dupStmt->fetchColumn() > 0) {
            return [
                'triggered' => false,
                'reason' => 'Active unread budget alert notification already exists for this budget',
                'budget_id' => $budgetId,
                'percentage_spent' => $percentageSpent,
            ];
        }

        // Target users: HR & Finance and Sports Administrator
        $targetUserIds = $this->getTargetUserIdsForRoles($organizationId, ['HR & Finance', 'Sports Administrator']);
        if (empty($targetUserIds)) {
            return ['triggered' => false, 'reason' => 'No active recipient users found for organization'];
        }

        $formattedSpent = number_format($actualSpent, 2);
        $formattedBudget = number_format($totalBudget, 2);
        $title = "Budget Utilization Alert: {$budget['budget_name']}";
        $message = "Budget '{$budget['budget_name']}' ({$budget['financial_year']}) has reached {$percentageSpent}% utilization (Spent: ₹{$formattedSpent} of ₹{$formattedBudget}, Threshold: {$thresholdPercent}%).";

        $created = [];
        foreach ($targetUserIds as $uId) {
            $created[] = $this->createNotification(
                $organizationId,
                $uId,
                $title,
                $message,
                'budget_alert',
                'budget',
                $budgetId,
                'in_app'
            );
        }

        return [
            'triggered' => true,
            'notifications_created' => count($created),
            'budget_id' => $budgetId,
            'budget_name' => $budget['budget_name'],
            'percentage_spent' => $percentageSpent,
            'total_spent' => $actualSpent,
            'total_budget' => $totalBudget,
            'recipients' => $targetUserIds,
        ];
    }
}
