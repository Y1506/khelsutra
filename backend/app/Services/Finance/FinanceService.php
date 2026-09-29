<?php

namespace App\Services\Finance;

use App\Services\BaseService;
use App\Services\Audit\AuditLogService;
use PDO;
use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * Service handling all financial management operations:
 * 1. Finance Categories (CRUD, type filtering, activation/deactivation, tenant isolation)
 * 2. Income Transactions (Recording, categorizing, payment refs, status tracking)
 * 3. Expense Management (Recording, categorizing, approval workflow, settlement, Member 4 integration)
 * 4. Budget Management (Creation, item allocation, actual spend tracking, remaining calculations, limit validation)
 * 5. Finance Payments (Recording payments against expenses and vendor invoices, atomic state transitions)
 * 6. Financial Summary & KPI Analytics
 */
class FinanceService extends BaseService
{
    protected ?AuditLogService $auditLogService = null;

    public function __construct(?AuditLogService $auditLogService = null)
    {
        parent::__construct();
        $this->auditLogService = $auditLogService ?: new AuditLogService();
    }

    /**
     * Preserved from existing class to maintain backward compatibility.
     */
    public function calculateNetSalary(float $basic, float $allowances, float $overtime, float $bonus, float $deductions, float $tax, float $otherDeductions): float
    {
        return ($basic + $allowances + $overtime + $bonus) - ($deductions + $tax + $otherDeductions);
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

    // =========================================================================
    // SECTION 1: REFERENCE NUMBER GENERATORS
    // =========================================================================

    /**
     * Generate unique Expense reference: EXP-YYYYMMDD-XXXX
     */
    public function generateExpenseReference(int $organizationId): string
    {
        $datePrefix = date('Ymd');
        $prefix = "EXP-{$datePrefix}-";
        $attempts = 0;
        do {
            $code = $prefix . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $this->pdo->prepare("
                SELECT id FROM expenses 
                WHERE organization_id = :org_id AND expense_reference = :ref
                LIMIT 1
            ");
            $stmt->execute([':org_id' => $organizationId, ':ref' => $code]);
            $exists = $stmt->fetchColumn();
            $attempts++;
        } while ($exists && $attempts < 20);

        return $code;
    }

    /**
     * Generate unique Income reference: INC-YYYYMMDD-XXXX
     */
    public function generateIncomeReference(int $organizationId): string
    {
        $datePrefix = date('Ymd');
        $prefix = "INC-{$datePrefix}-";
        $attempts = 0;
        do {
            $code = $prefix . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $this->pdo->prepare("
                SELECT id FROM income_transactions 
                WHERE organization_id = :org_id AND income_reference = :ref
                LIMIT 1
            ");
            $stmt->execute([':org_id' => $organizationId, ':ref' => $code]);
            $exists = $stmt->fetchColumn();
            $attempts++;
        } while ($exists && $attempts < 20);

        return $code;
    }

    /**
     * Generate unique Payment reference: PAY-YYYYMMDD-XXXX
     */
    public function generatePaymentReference(int $organizationId): string
    {
        $datePrefix = date('Ymd');
        $prefix = "PAY-{$datePrefix}-";
        $attempts = 0;
        do {
            $code = $prefix . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $this->pdo->prepare("
                SELECT id FROM finance_payments 
                WHERE organization_id = :org_id AND payment_reference = :ref
                LIMIT 1
            ");
            $stmt->execute([':org_id' => $organizationId, ':ref' => $code]);
            $exists = $stmt->fetchColumn();
            $attempts++;
        } while ($exists && $attempts < 20);

        return $code;
    }

    // =========================================================================
    // SECTION 2: FINANCE CATEGORIES
    // =========================================================================

    /**
     * List finance categories for an organization.
     */
    public function listCategories(
        int $organizationId,
        ?string $type = null,
        ?string $status = null,
        ?string $search = null
    ): array {
        if (!$this->pdo) return [];

        $where = ["organization_id = :org_id"];
        $params = [':org_id' => $organizationId];

        if ($type && in_array($type, ['income', 'expense', 'both'], true)) {
            $where[] = "(category_type = :type OR category_type = 'both')";
            $params[':type'] = $type;
        }

        if ($status && in_array($status, ['active', 'inactive'], true)) {
            $where[] = "status = :status";
            $params[':status'] = $status;
        }

        if ($search) {
            $where[] = "(name LIKE :search OR description LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $whereClause = implode(" AND ", $where);
        $sql = "
            SELECT * FROM finance_categories 
            WHERE {$whereClause}
            ORDER BY name ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get a single category by ID.
     */
    public function getCategory(int $organizationId, int $categoryId): ?array
    {
        if (!$this->pdo) return null;

        $stmt = $this->pdo->prepare("
            SELECT * FROM finance_categories 
            WHERE id = :id AND organization_id = :org_id
            LIMIT 1
        ");
        $stmt->execute([':id' => $categoryId, ':org_id' => $organizationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Create a finance category.
     */
    public function createCategory(int $organizationId, array $data, ?int $userId = null): array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            throw new InvalidArgumentException("Category name is required.");
        }
        if (mb_strlen($name) > 100) {
            throw new InvalidArgumentException("Category name cannot exceed 100 characters.");
        }

        // Uniqueness per organization
        $chkStmt = $this->pdo->prepare("
            SELECT id FROM finance_categories 
            WHERE organization_id = :org_id AND name = :name 
            LIMIT 1
        ");
        $chkStmt->execute([':org_id' => $organizationId, ':name' => $name]);
        if ($chkStmt->fetchColumn()) {
            throw new InvalidArgumentException("A finance category named '{$name}' already exists in your organization.");
        }

        $categoryType = trim($data['category_type'] ?? 'both');
        if (!in_array($categoryType, ['income', 'expense', 'both'], true)) {
            $categoryType = 'both';
        }

        $status = trim($data['status'] ?? 'active');
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        $description = !empty($data['description']) ? trim($data['description']) : null;

        $stmt = $this->pdo->prepare("
            INSERT INTO finance_categories (
                organization_id, name, category_type, description, status, created_at, updated_at
            ) VALUES (
                :org_id, :name, :category_type, :description, :status, NOW(), NOW()
            )
        ");

        $stmt->execute([
            ':org_id' => $organizationId,
            ':name' => $name,
            ':category_type' => $categoryType,
            ':description' => $description,
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
                    'finance_category',
                    $newId,
                    null,
                    ['name' => $name, 'category_type' => $categoryType, 'status' => $status]
                );
            } catch (\Throwable $e) {}
        }

        return $this->getCategory($organizationId, $newId);
    }

    /**
     * Update an existing category.
     */
    public function updateCategory(int $organizationId, int $categoryId, array $data, ?int $userId = null): bool
    {
        if (!$this->pdo) return false;

        $existing = $this->getCategory($organizationId, $categoryId);
        if (!$existing) {
            throw new InvalidArgumentException("Category not found or access denied.");
        }

        $name = isset($data['name']) ? trim($data['name']) : $existing['name'];
        if (empty($name)) {
            throw new InvalidArgumentException("Category name cannot be empty.");
        }
        if (mb_strlen($name) > 100) {
            throw new InvalidArgumentException("Category name cannot exceed 100 characters.");
        }

        if ($name !== $existing['name']) {
            $chkStmt = $this->pdo->prepare("
                SELECT id FROM finance_categories 
                WHERE organization_id = :org_id AND name = :name AND id != :id 
                LIMIT 1
            ");
            $chkStmt->execute([':org_id' => $organizationId, ':name' => $name, ':id' => $categoryId]);
            if ($chkStmt->fetchColumn()) {
                throw new InvalidArgumentException("A finance category named '{$name}' already exists in your organization.");
            }
        }

        $categoryType = isset($data['category_type']) ? trim($data['category_type']) : $existing['category_type'];
        if (!in_array($categoryType, ['income', 'expense', 'both'], true)) {
            $categoryType = $existing['category_type'];
        }

        $status = isset($data['status']) ? trim($data['status']) : $existing['status'];
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = $existing['status'];
        }

        $description = array_key_exists('description', $data) ? (!empty($data['description']) ? trim($data['description']) : null) : $existing['description'];

        $stmt = $this->pdo->prepare("
            UPDATE finance_categories SET 
                name = :name,
                category_type = :category_type,
                description = :description,
                status = :status,
                updated_at = NOW()
            WHERE id = :id AND organization_id = :org_id
        ");

        $ok = $stmt->execute([
            ':name' => $name,
            ':category_type' => $categoryType,
            ':description' => $description,
            ':status' => $status,
            ':id' => $categoryId,
            ':org_id' => $organizationId,
        ]);

        $auditUser = $this->resolveAuditUserId($userId, $organizationId);
        if ($ok && $this->auditLogService && $auditUser) {
            try {
                $this->auditLogService->log(
                    $organizationId,
                    $auditUser,
                    'update',
                    'finance_category',
                    $categoryId,
                    $existing,
                    ['name' => $name, 'category_type' => $categoryType, 'status' => $status]
                );
            } catch (\Throwable $e) {}
        }

        return $ok;
    }

    /**
     * Activate or deactivate a category.
     */
    public function setCategoryStatus(int $organizationId, int $categoryId, string $status, ?int $userId = null): bool
    {
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException("Invalid status: {$status}");
        }

        return $this->updateCategory($organizationId, $categoryId, ['status' => $status], $userId);
    }

    // =========================================================================
    // SECTION 3: INCOME TRANSACTIONS
    // =========================================================================

    /**
     * List income transactions with filtering and pagination.
     */
    public function listIncome(
        int $organizationId,
        int $page = 1,
        int $perPage = 15,
        array $filters = []
    ): array {
        if (!$this->pdo) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'limit' => $perPage, 'total_pages' => 0, 'total_amount' => 0];
        }

        $where = ["it.organization_id = :org_id"];
        $params = [':org_id' => $organizationId];

        if (!empty($filters['finance_category_id'])) {
            $where[] = "it.finance_category_id = :cat_id";
            $params[':cat_id'] = (int)$filters['finance_category_id'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], ['pending', 'received', 'cancelled'], true)) {
            $where[] = "it.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['start_date'])) {
            $where[] = "it.income_date >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $where[] = "it.income_date <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(it.income_reference LIKE :search OR it.source_name LIKE :search OR it.description LIKE :search OR fc.name LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $whereClause = implode(" AND ", $where);

        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*), COALESCE(SUM(it.amount), 0)
            FROM income_transactions it
            JOIN finance_categories fc ON it.finance_category_id = fc.id
            WHERE {$whereClause}
        ");
        $countStmt->execute($params);
        [$total, $totalAmount] = $countStmt->fetch(PDO::FETCH_NUM);
        $total = (int)$total;
        $totalAmount = (float)$totalAmount;

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT 
                it.*,
                fc.name as category_name,
                fc.category_type,
                COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Staff') as creator_name
            FROM income_transactions it
            JOIN finance_categories fc ON it.finance_category_id = fc.id
            LEFT JOIN users u ON it.created_by = u.id
            WHERE {$whereClause}
            ORDER BY it.income_date DESC, it.id DESC
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
            'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Get a single income transaction.
     */
    public function getIncome(int $organizationId, int $incomeId): ?array
    {
        if (!$this->pdo) return null;

        $stmt = $this->pdo->prepare("
            SELECT 
                it.*,
                fc.name as category_name,
                fc.category_type,
                COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Staff') as creator_name
            FROM income_transactions it
            JOIN finance_categories fc ON it.finance_category_id = fc.id
            LEFT JOIN users u ON it.created_by = u.id
            WHERE it.id = :id AND it.organization_id = :org_id
            LIMIT 1
        ");
        $stmt->execute([':id' => $incomeId, ':org_id' => $organizationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Record an income transaction.
     */
    public function recordIncome(int $organizationId, array $data, ?int $userId = null): array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $sourceName = trim($data['source_name'] ?? ($data['source'] ?? ''));
        if (empty($sourceName)) {
            throw new InvalidArgumentException("Income source name is required.");
        }

        $amount = (float)($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException("Income amount must be greater than zero.");
        }

        $categoryId = (int)($data['finance_category_id'] ?? 0);
        $category = $this->getCategory($organizationId, $categoryId);
        if (!$category) {
            throw new InvalidArgumentException("Finance category not found or access denied.");
        }
        if ($category['status'] !== 'active') {
            throw new InvalidArgumentException("Cannot record income under an inactive finance category.");
        }
        if ($category['category_type'] === 'expense') {
            throw new InvalidArgumentException("Cannot record income under an expense-only category.");
        }

        $incomeDate = !empty($data['income_date']) ? trim($data['income_date']) : date('Y-m-d');
        $status = trim($data['status'] ?? 'received');
        if (!in_array($status, ['pending', 'received', 'cancelled'], true)) {
            $status = 'received';
        }

        $description = !empty($data['description']) ? trim($data['description']) : $sourceName;
        $paymentMethod = !empty($data['payment_method']) ? trim($data['payment_method']) : null;
        $paymentReference = !empty($data['payment_reference']) ? trim($data['payment_reference']) : null;
        $receiptPath = !empty($data['receipt_path']) ? trim($data['receipt_path']) : null;

        $ref = $this->generateIncomeReference($organizationId);
        $auditUser = $this->resolveAuditUserId($userId, $organizationId);

        $stmt = $this->pdo->prepare("
            INSERT INTO income_transactions (
                organization_id, income_reference, finance_category_id,
                income_date, source_name, description, amount,
                payment_method, payment_reference, receipt_path,
                status, created_by, created_at, updated_at
            ) VALUES (
                :org_id, :ref, :cat_id,
                :income_date, :source_name, :description, :amount,
                :payment_method, :payment_reference, :receipt_path,
                :status, :created_by, NOW(), NOW()
            )
        ");

        $stmt->execute([
            ':org_id' => $organizationId,
            ':ref' => $ref,
            ':cat_id' => $categoryId,
            ':income_date' => $incomeDate,
            ':source_name' => $sourceName,
            ':description' => $description,
            ':amount' => $amount,
            ':payment_method' => $paymentMethod,
            ':payment_reference' => $paymentReference,
            ':receipt_path' => $receiptPath,
            ':status' => $status,
            ':created_by' => $auditUser,
        ]);

        $newId = (int)$this->pdo->lastInsertId();

        if ($this->auditLogService && $auditUser) {
            try {
                $this->auditLogService->log(
                    $organizationId,
                    $auditUser,
                    'create',
                    'income_transaction',
                    $newId,
                    null,
                    ['income_reference' => $ref, 'source' => $sourceName, 'amount' => $amount, 'status' => $status]
                );
            } catch (\Throwable $e) {}
        }

        return $this->getIncome($organizationId, $newId);
    }

    /**
     * Update an income transaction (where supported).
     */
    public function updateIncome(int $organizationId, int $incomeId, array $data, ?int $userId = null): bool
    {
        if (!$this->pdo) return false;

        $existing = $this->getIncome($organizationId, $incomeId);
        if (!$existing) {
            throw new InvalidArgumentException("Income record not found or access denied.");
        }
        if ($existing['status'] === 'cancelled') {
            throw new InvalidArgumentException("Cannot update a cancelled income record.");
        }

        $sourceName = isset($data['source_name']) ? trim($data['source_name']) : (isset($data['source']) ? trim($data['source']) : $existing['source_name']);
        if (empty($sourceName)) {
            throw new InvalidArgumentException("Income source name cannot be empty.");
        }

        $amount = array_key_exists('amount', $data) ? (float)$data['amount'] : (float)$existing['amount'];
        if ($amount <= 0) {
            throw new InvalidArgumentException("Income amount must be greater than zero.");
        }

        $categoryId = array_key_exists('finance_category_id', $data) ? (int)$data['finance_category_id'] : (int)$existing['finance_category_id'];
        if ($categoryId !== (int)$existing['finance_category_id']) {
            $cat = $this->getCategory($organizationId, $categoryId);
            if (!$cat || $cat['category_type'] === 'expense') {
                throw new InvalidArgumentException("Invalid or expense-only finance category selected.");
            }
        }

        $incomeDate = array_key_exists('income_date', $data) ? trim($data['income_date']) : $existing['income_date'];
        $description = array_key_exists('description', $data) ? (!empty($data['description']) ? trim($data['description']) : $sourceName) : $existing['description'];
        $paymentMethod = array_key_exists('payment_method', $data) ? (!empty($data['payment_method']) ? trim($data['payment_method']) : null) : $existing['payment_method'];
        $paymentReference = array_key_exists('payment_reference', $data) ? (!empty($data['payment_reference']) ? trim($data['payment_reference']) : null) : $existing['payment_reference'];
        $status = isset($data['status']) && in_array($data['status'], ['pending', 'received', 'cancelled'], true) ? $data['status'] : $existing['status'];

        $stmt = $this->pdo->prepare("
            UPDATE income_transactions SET
                source_name = :source_name,
                finance_category_id = :cat_id,
                income_date = :income_date,
                description = :description,
                amount = :amount,
                payment_method = :payment_method,
                payment_reference = :payment_reference,
                status = :status,
                updated_at = NOW()
            WHERE id = :id AND organization_id = :org_id
        ");

        $ok = $stmt->execute([
            ':source_name' => $sourceName,
            ':cat_id' => $categoryId,
            ':income_date' => $incomeDate,
            ':description' => $description,
            ':amount' => $amount,
            ':payment_method' => $paymentMethod,
            ':payment_reference' => $paymentReference,
            ':status' => $status,
            ':id' => $incomeId,
            ':org_id' => $organizationId,
        ]);

        $auditUser = $this->resolveAuditUserId($userId, $organizationId);
        if ($ok && $this->auditLogService && $auditUser) {
            try {
                $this->auditLogService->log(
                    $organizationId,
                    $auditUser,
                    'update',
                    'income_transaction',
                    $incomeId,
                    ['amount' => $existing['amount'], 'status' => $existing['status']],
                    ['amount' => $amount, 'status' => $status]
                );
            } catch (\Throwable $e) {}
        }

        return $ok;
    }

    /**
     * Cancel an income transaction.
     */
    public function cancelIncome(int $organizationId, int $incomeId, ?int $userId = null): bool
    {
        return $this->updateIncome($organizationId, $incomeId, ['status' => 'cancelled'], $userId);
    }

    // =========================================================================
    // SECTION 4: EXPENSE MANAGEMENT
    // =========================================================================

    /**
     * List expenses with filters, search, and pagination.
     */
    public function listExpenses(
        int $organizationId,
        int $page = 1,
        int $perPage = 15,
        array $filters = []
    ): array {
        if (!$this->pdo) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'limit' => $perPage, 'total_pages' => 0, 'total_amount' => 0];
        }

        $where = ["e.organization_id = :org_id", "e.deleted_at IS NULL"];
        $params = [':org_id' => $organizationId];

        if (!empty($filters['finance_category_id'])) {
            $where[] = "e.finance_category_id = :cat_id";
            $params[':cat_id'] = (int)$filters['finance_category_id'];
        }

        if (!empty($filters['payment_status']) && in_array($filters['payment_status'], ['pending', 'approved', 'paid', 'rejected', 'cancelled'], true)) {
            $where[] = "e.payment_status = :status";
            $params[':status'] = $filters['payment_status'];
        }

        if (!empty($filters['vendor_id'])) {
            $where[] = "e.vendor_id = :vendor_id";
            $params[':vendor_id'] = (int)$filters['vendor_id'];
        }

        if (!empty($filters['department_id'])) {
            $where[] = "e.department_id = :dept_id";
            $params[':dept_id'] = (int)$filters['department_id'];
        }

        if (!empty($filters['start_date'])) {
            $where[] = "e.expense_date >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $where[] = "e.expense_date <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(e.expense_reference LIKE :search OR e.description LIKE :search OR v.company_name LIKE :search OR fc.name LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $whereClause = implode(" AND ", $where);

        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*), COALESCE(SUM(e.total_amount), 0)
            FROM expenses e
            LEFT JOIN finance_categories fc ON e.finance_category_id = fc.id
            LEFT JOIN vendors v ON e.vendor_id = v.id
            WHERE {$whereClause}
        ");
        $countStmt->execute($params);
        [$total, $totalAmount] = $countStmt->fetch(PDO::FETCH_NUM);
        $total = (int)$total;
        $totalAmount = (float)$totalAmount;

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT 
                e.*,
                fc.name as category_name,
                v.company_name as vendor_name,
                d.name as department_name,
                COALESCE(CONCAT(cu.first_name, ' ', cu.last_name), 'Staff') as creator_name,
                COALESCE(CONCAT(au.first_name, ' ', au.last_name), NULL) as approver_name
            FROM expenses e
            LEFT JOIN finance_categories fc ON e.finance_category_id = fc.id
            LEFT JOIN vendors v ON e.vendor_id = v.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN users cu ON e.created_by = cu.id
            LEFT JOIN users au ON e.approved_by = au.id
            WHERE {$whereClause}
            ORDER BY e.expense_date DESC, e.id DESC
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
            'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Get a single expense record with all linked relations and payments.
     */
    public function getExpense(int $organizationId, int $expenseId): ?array
    {
        if (!$this->pdo) return null;

        $stmt = $this->pdo->prepare("
            SELECT 
                e.*,
                fc.name as category_name,
                v.company_name as vendor_name,
                v.vendor_code,
                d.name as department_name,
                COALESCE(CONCAT(cu.first_name, ' ', cu.last_name), 'Staff') as creator_name,
                COALESCE(CONCAT(au.first_name, ' ', au.last_name), NULL) as approver_name
            FROM expenses e
            LEFT JOIN finance_categories fc ON e.finance_category_id = fc.id
            LEFT JOIN vendors v ON e.vendor_id = v.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN users cu ON e.created_by = cu.id
            LEFT JOIN users au ON e.approved_by = au.id
            WHERE e.id = :id AND e.organization_id = :org_id AND e.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $expenseId, ':org_id' => $organizationId]);
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$expense) return null;

        // Fetch linked payments
        $payStmt = $this->pdo->prepare("
            SELECT 
                fp.*,
                COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Staff') as creator_name
            FROM finance_payments fp
            LEFT JOIN users u ON fp.created_by = u.id
            WHERE fp.expense_id = :exp_id AND fp.organization_id = :org_id
            ORDER BY fp.payment_date DESC, fp.id DESC
        ");
        $payStmt->execute([':exp_id' => $expenseId, ':org_id' => $organizationId]);
        $expense['payments'] = $payStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $expense;
    }

    /**
     * Record an expense.
     * Integrates cleanly with Member 4's ExpenseRecorder format and direct manual creation.
     */
    public function recordExpense(int $organizationId, array $data, ?int $userId = null): array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $description = trim($data['description'] ?? '');
        if (empty($description)) {
            throw new InvalidArgumentException("Expense description is required.");
        }

        $amount = (float)($data['amount'] ?? 0);
        if ($amount < 0) {
            throw new InvalidArgumentException("Expense amount cannot be negative.");
        }

        $taxAmount = max(0, (float)($data['tax_amount'] ?? 0));
        $totalAmount = round($amount + $taxAmount, 2);

        // Optional category validation
        $categoryId = !empty($data['finance_category_id']) ? (int)$data['finance_category_id'] : null;
        if ($categoryId) {
            $cat = $this->getCategory($organizationId, $categoryId);
            if (!$cat) {
                throw new InvalidArgumentException("Finance category not found or access denied.");
            }
            if ($cat['category_type'] === 'income') {
                throw new InvalidArgumentException("Cannot record expense under an income-only category.");
            }
        }

        // Optional vendor validation
        $vendorId = !empty($data['vendor_id']) ? (int)$data['vendor_id'] : null;
        if ($vendorId) {
            $vStmt = $this->pdo->prepare("SELECT id FROM vendors WHERE id = :id AND organization_id = :org_id LIMIT 1");
            $vStmt->execute([':id' => $vendorId, ':org_id' => $organizationId]);
            if (!$vStmt->fetchColumn()) {
                throw new InvalidArgumentException("Vendor not found or access denied.");
            }
        }

        // Optional department validation
        $departmentId = !empty($data['department_id']) ? (int)$data['department_id'] : null;
        if ($departmentId) {
            $dStmt = $this->pdo->prepare("SELECT id FROM departments WHERE id = :id AND organization_id = :org_id LIMIT 1");
            $dStmt->execute([':id' => $departmentId, ':org_id' => $organizationId]);
            if (!$dStmt->fetchColumn()) {
                throw new InvalidArgumentException("Department not found or access denied.");
            }
        }

        $expenseDate = !empty($data['expense_date']) ? trim($data['expense_date']) : date('Y-m-d');
        $paymentStatus = trim($data['payment_status'] ?? 'pending');
        if (!in_array($paymentStatus, ['pending', 'approved', 'paid', 'rejected', 'cancelled'], true)) {
            $paymentStatus = 'pending';
        }

        $paymentMethod = !empty($data['payment_method']) ? trim($data['payment_method']) : null;
        $paymentReference = !empty($data['payment_reference']) ? trim($data['payment_reference']) : null;
        $notes = !empty($data['notes']) ? trim($data['notes']) : null;
        $receiptPath = !empty($data['receipt_path']) ? trim($data['receipt_path']) : null;
        $eventId = !empty($data['event_id']) ? (int)$data['event_id'] : null;
        $tournamentId = !empty($data['tournament_id']) ? (int)$data['tournament_id'] : null;

        $ref = !empty($data['expense_reference']) ? trim($data['expense_reference']) : $this->generateExpenseReference($organizationId);
        $auditUser = $this->resolveAuditUserId($userId, $organizationId);

        $approvedBy = null;
        $approvedAt = null;
        $paidAt = null;

        if ($paymentStatus === 'approved') {
            $approvedBy = $auditUser;
            $approvedAt = date('Y-m-d H:i:s');
        } elseif ($paymentStatus === 'paid') {
            $approvedBy = $auditUser;
            $approvedAt = date('Y-m-d H:i:s');
            $paidAt = date('Y-m-d H:i:s');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO expenses (
                organization_id, expense_reference, finance_category_id,
                department_id, vendor_id, event_id, tournament_id,
                expense_date, description, amount, tax_amount, total_amount,
                payment_status, payment_method, payment_reference,
                approved_by, approved_at, paid_at, receipt_path, notes,
                created_by, created_at, updated_at
            ) VALUES (
                :org_id, :ref, :cat_id,
                :dept_id, :vendor_id, :event_id, :tournament_id,
                :expense_date, :description, :amount, :tax_amount, :total_amount,
                :payment_status, :payment_method, :payment_reference,
                :approved_by, :approved_at, :paid_at, :receipt_path, :notes,
                :created_by, NOW(), NOW()
            )
        ");

        $stmt->execute([
            ':org_id' => $organizationId,
            ':ref' => $ref,
            ':cat_id' => $categoryId,
            ':dept_id' => $departmentId,
            ':vendor_id' => $vendorId,
            ':event_id' => $eventId,
            ':tournament_id' => $tournamentId,
            ':expense_date' => $expenseDate,
            ':description' => $description,
            ':amount' => $amount,
            ':tax_amount' => $taxAmount,
            ':total_amount' => $totalAmount,
            ':payment_status' => $paymentStatus,
            ':payment_method' => $paymentMethod,
            ':payment_reference' => $paymentReference,
            ':approved_by' => $approvedBy,
            ':approved_at' => $approvedAt,
            ':paid_at' => $paidAt,
            ':receipt_path' => $receiptPath,
            ':notes' => $notes,
            ':created_by' => $auditUser,
        ]);

        $newId = (int)$this->pdo->lastInsertId();

        if ($this->auditLogService && $auditUser) {
            try {
                $this->auditLogService->log(
                    $organizationId,
                    $auditUser,
                    'create',
                    'expense',
                    $newId,
                    null,
                    ['reference' => $ref, 'amount' => $totalAmount, 'status' => $paymentStatus]
                );
            } catch (\Throwable $e) {}
        }

        // Automated Budget Alert check for active budgets covering this expense
        try {
            $bStmt = $this->pdo->prepare("
                SELECT id FROM budgets 
                WHERE organization_id = :org_id 
                  AND status = 'active'
                  AND :exp_date BETWEEN start_date AND end_date
            ");
            $bStmt->execute([':org_id' => $organizationId, ':exp_date' => $expenseDate]);
            $activeBudgetIds = $bStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            if (!empty($activeBudgetIds)) {
                $notifService = new \App\Services\Notification\NotificationService($this->pdo);
                foreach ($activeBudgetIds as $bId) {
                    $notifService->checkAndTriggerBudgetThreshold($organizationId, (int)$bId);
                }
            }
        } catch (\Throwable $e) {}

        return $this->getExpense($organizationId, $newId);
    }

    /**
     * Update an expense record.
     */
    public function updateExpense(int $organizationId, int $expenseId, array $data, ?int $userId = null): bool
    {
        if (!$this->pdo) return false;

        $existing = $this->getExpense($organizationId, $expenseId);
        if (!$existing) {
            throw new InvalidArgumentException("Expense not found or access denied.");
        }
        if (in_array($existing['payment_status'], ['paid', 'cancelled'], true)) {
            throw new InvalidArgumentException("Cannot modify an expense that is already {$existing['payment_status']}.");
        }

        $description = isset($data['description']) ? trim($data['description']) : $existing['description'];
        if (empty($description)) {
            throw new InvalidArgumentException("Expense description cannot be empty.");
        }

        $amount = array_key_exists('amount', $data) ? (float)$data['amount'] : (float)$existing['amount'];
        $taxAmount = array_key_exists('tax_amount', $data) ? max(0, (float)$data['tax_amount']) : (float)$existing['tax_amount'];
        $totalAmount = round($amount + $taxAmount, 2);

        $categoryId = array_key_exists('finance_category_id', $data) ? (!empty($data['finance_category_id']) ? (int)$data['finance_category_id'] : null) : $existing['finance_category_id'];
        $vendorId = array_key_exists('vendor_id', $data) ? (!empty($data['vendor_id']) ? (int)$data['vendor_id'] : null) : $existing['vendor_id'];
        $departmentId = array_key_exists('department_id', $data) ? (!empty($data['department_id']) ? (int)$data['department_id'] : null) : $existing['department_id'];
        $expenseDate = array_key_exists('expense_date', $data) ? trim($data['expense_date']) : $existing['expense_date'];
        $notes = array_key_exists('notes', $data) ? (!empty($data['notes']) ? trim($data['notes']) : null) : $existing['notes'];

        $stmt = $this->pdo->prepare("
            UPDATE expenses SET
                description = :description,
                amount = :amount,
                tax_amount = :tax_amount,
                total_amount = :total_amount,
                finance_category_id = :cat_id,
                vendor_id = :vendor_id,
                department_id = :dept_id,
                expense_date = :expense_date,
                notes = :notes,
                updated_at = NOW()
            WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
        ");

        $ok = $stmt->execute([
            ':description' => $description,
            ':amount' => $amount,
            ':tax_amount' => $taxAmount,
            ':total_amount' => $totalAmount,
            ':cat_id' => $categoryId,
            ':vendor_id' => $vendorId,
            ':dept_id' => $departmentId,
            ':expense_date' => $expenseDate,
            ':notes' => $notes,
            ':id' => $expenseId,
            ':org_id' => $organizationId,
        ]);

        $auditUser = $this->resolveAuditUserId($userId, $organizationId);
        if ($ok && $this->auditLogService && $auditUser) {
            try {
                $this->auditLogService->log(
                    $organizationId,
                    $auditUser,
                    'update',
                    'expense',
                    $expenseId,
                    ['total_amount' => $existing['total_amount']],
                    ['total_amount' => $totalAmount]
                );
            } catch (\Throwable $e) {}
        }

        return $ok;
    }

    /**
     * Approve an expense.
     */
    public function approveExpense(int $organizationId, int $expenseId, ?int $approvedBy = null): bool
    {
        if (!$this->pdo) return false;

        $existing = $this->getExpense($organizationId, $expenseId);
        if (!$existing) {
            throw new InvalidArgumentException("Expense not found or access denied.");
        }
        if ($existing['payment_status'] !== 'pending') {
            throw new InvalidArgumentException("Only pending expenses can be approved. Current status: {$existing['payment_status']}.");
        }

        $auditUser = $this->resolveAuditUserId($approvedBy, $organizationId);

        $stmt = $this->pdo->prepare("
            UPDATE expenses SET
                payment_status = 'approved',
                approved_by = :approved_by,
                approved_at = NOW(),
                updated_at = NOW()
            WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
        ");

        $ok = $stmt->execute([
            ':approved_by' => $auditUser,
            ':id' => $expenseId,
            ':org_id' => $organizationId,
        ]);

        if ($ok && $this->auditLogService && $auditUser) {
            try {
                $this->auditLogService->log(
                    $organizationId,
                    $auditUser,
                    'approve',
                    'expense',
                    $expenseId,
                    ['payment_status' => 'pending'],
                    ['payment_status' => 'approved', 'approved_by' => $auditUser]
                );
            } catch (\Throwable $e) {}
        }

        // Automated Budget Alert check on expense approval
        try {
            $expDate = $existing['expense_date'] ?? date('Y-m-d');
            $bStmt = $this->pdo->prepare("
                SELECT id FROM budgets 
                WHERE organization_id = :org_id 
                  AND status = 'active'
                  AND :exp_date BETWEEN start_date AND end_date
            ");
            $bStmt->execute([':org_id' => $organizationId, ':exp_date' => $expDate]);
            $activeBudgetIds = $bStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            if (!empty($activeBudgetIds)) {
                $notifService = new \App\Services\Notification\NotificationService($this->pdo);
                foreach ($activeBudgetIds as $bId) {
                    $notifService->checkAndTriggerBudgetThreshold($organizationId, (int)$bId);
                }
            }
        } catch (\Throwable $e) {}

        return $ok;
    }

    /**
     * Reject an expense with an optional reason.
     */
    public function rejectExpense(int $organizationId, int $expenseId, ?string $reason = null, ?int $userId = null): bool
    {
        if (!$this->pdo) return false;

        $existing = $this->getExpense($organizationId, $expenseId);
        if (!$existing) {
            throw new InvalidArgumentException("Expense not found or access denied.");
        }
        if ($existing['payment_status'] !== 'pending') {
            throw new InvalidArgumentException("Only pending expenses can be rejected. Current status: {$existing['payment_status']}.");
        }

        $auditUser = $this->resolveAuditUserId($userId, $organizationId);
        $notes = $existing['notes'];
        if ($reason) {
            $notes = ($notes ? $notes . "\n" : '') . "Rejection reason: " . $reason;
        }

        $stmt = $this->pdo->prepare("
            UPDATE expenses SET
                payment_status = 'rejected',
                notes = :notes,
                updated_at = NOW()
            WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
        ");

        $ok = $stmt->execute([
            ':notes' => $notes,
            ':id' => $expenseId,
            ':org_id' => $organizationId,
        ]);

        if ($ok && $this->auditLogService && $auditUser) {
            try {
                $this->auditLogService->log(
                    $organizationId,
                    $auditUser,
                    'reject',
                    'expense',
                    $expenseId,
                    ['payment_status' => 'pending'],
                    ['payment_status' => 'rejected', 'reason' => $reason]
                );
            } catch (\Throwable $e) {}
        }

        return $ok;
    }

    /**
     * Cancel an expense.
     */
    public function cancelExpense(int $organizationId, int $expenseId, ?int $userId = null): bool
    {
        if (!$this->pdo) return false;

        $existing = $this->getExpense($organizationId, $expenseId);
        if (!$existing) {
            throw new InvalidArgumentException("Expense not found or access denied.");
        }
        if ($existing['payment_status'] === 'paid') {
            throw new InvalidArgumentException("Paid expenses cannot be cancelled. Record an adjustment instead.");
        }

        $auditUser = $this->resolveAuditUserId($userId, $organizationId);

        $stmt = $this->pdo->prepare("
            UPDATE expenses SET
                payment_status = 'cancelled',
                updated_at = NOW()
            WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
        ");

        $ok = $stmt->execute([
            ':id' => $expenseId,
            ':org_id' => $organizationId,
        ]);

        if ($ok && $this->auditLogService && $auditUser) {
            try {
                $this->auditLogService->log(
                    $organizationId,
                    $auditUser,
                    'cancel',
                    'expense',
                    $expenseId,
                    ['payment_status' => $existing['payment_status']],
                    ['payment_status' => 'cancelled']
                );
            } catch (\Throwable $e) {}
        }

        return $ok;
    }

    /**
     * Soft delete an expense.
     */
    public function deleteExpense(int $organizationId, int $expenseId, ?int $userId = null): bool
    {
        if (!$this->pdo) return false;

        $existing = $this->getExpense($organizationId, $expenseId);
        if (!$existing) {
            throw new InvalidArgumentException("Expense not found or access denied.");
        }
        if ($existing['payment_status'] === 'paid') {
            throw new InvalidArgumentException("Paid expenses cannot be deleted.");
        }

        $auditUser = $this->resolveAuditUserId($userId, $organizationId);

        $stmt = $this->pdo->prepare("
            UPDATE expenses SET
                deleted_at = NOW()
            WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
        ");

        $ok = $stmt->execute([
            ':id' => $expenseId,
            ':org_id' => $organizationId,
        ]);

        if ($ok && $this->auditLogService && $auditUser) {
            try {
                $this->auditLogService->log(
                    $organizationId,
                    $auditUser,
                    'delete',
                    'expense',
                    $expenseId,
                    ['reference' => $existing['expense_reference']],
                    null
                );
            } catch (\Throwable $e) {}
        }

        return $ok;
    }

    // =========================================================================
    // SECTION 5: BUDGET MANAGEMENT
    // =========================================================================

    /**
     * List budgets with overall allocated and spent calculations.
     */
    public function listBudgets(
        int $organizationId,
        int $page = 1,
        int $perPage = 15,
        ?string $status = null,
        ?string $financialYear = null
    ): array {
        if (!$this->pdo) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'limit' => $perPage, 'total_pages' => 0];
        }

        $where = ["b.organization_id = :org_id"];
        $params = [':org_id' => $organizationId];

        if ($status && in_array($status, ['draft', 'active', 'closed', 'cancelled'], true)) {
            $where[] = "b.status = :status";
            $params[':status'] = $status;
        }

        if ($financialYear) {
            $where[] = "b.financial_year = :fy";
            $params[':fy'] = $financialYear;
        }

        $whereClause = implode(" AND ", $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM budgets b WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT 
                b.*,
                COALESCE((SELECT SUM(bi.allocated_amount) FROM budget_items bi WHERE bi.budget_id = b.id), 0) as total_allocated_items,
                (
                    SELECT COALESCE(SUM(e.total_amount), 0)
                    FROM expenses e
                    WHERE e.organization_id = b.organization_id
                      AND e.expense_date BETWEEN b.start_date AND b.end_date
                      AND e.payment_status NOT IN ('cancelled', 'rejected')
                      AND e.deleted_at IS NULL
                      AND (
                          e.finance_category_id IN (SELECT bi.finance_category_id FROM budget_items bi WHERE bi.budget_id = b.id)
                          OR NOT EXISTS (SELECT 1 FROM budget_items bi WHERE bi.budget_id = b.id)
                      )
                ) as total_spent
            FROM budgets b
            WHERE {$whereClause}
            ORDER BY b.start_date DESC, b.id DESC
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
        foreach ($data as &$b) {
            $cap = (float)$b['total_budget'];
            $spent = (float)$b['total_spent'];
            $b['remaining_budget'] = max(0, round($cap - $spent, 2));
            $b['percentage_spent'] = $cap > 0 ? min(100, round(($spent / $cap) * 100, 1)) : 0;
        }

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $perPage,
            'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
        ];
    }

    /**
     * Get a single budget by ID with detailed line items and actual spend per item.
     */
    public function getBudget(int $organizationId, int $budgetId): ?array
    {
        if (!$this->pdo) return null;

        $stmt = $this->pdo->prepare("
            SELECT * FROM budgets 
            WHERE id = :id AND organization_id = :org_id
            LIMIT 1
        ");
        $stmt->execute([':id' => $budgetId, ':org_id' => $organizationId]);
        $budget = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$budget) return null;

        // Fetch line items
        $itemStmt = $this->pdo->prepare("
            SELECT 
                bi.*,
                fc.name as category_name,
                fc.category_type,
                d.name as department_name
            FROM budget_items bi
            JOIN finance_categories fc ON bi.finance_category_id = fc.id
            LEFT JOIN departments d ON bi.department_id = d.id
            WHERE bi.budget_id = :bid
            ORDER BY bi.id ASC
        ");
        $itemStmt->execute([':bid' => $budgetId]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $totalAllocatedItems = 0;
        $totalSpentItems = 0;

        foreach ($items as &$it) {
            $catId = (int)$it['finance_category_id'];
            $deptId = !empty($it['department_id']) ? (int)$it['department_id'] : null;

            // Query actual spend for this category (and department if specified) during budget period
            $spendSql = "
                SELECT COALESCE(SUM(e.total_amount), 0)
                FROM expenses e
                WHERE e.organization_id = :org_id
                  AND e.finance_category_id = :cat_id
                  AND e.expense_date BETWEEN :start_date AND :end_date
                  AND e.payment_status NOT IN ('cancelled', 'rejected')
                  AND e.deleted_at IS NULL
            ";
            $spendParams = [
                ':org_id' => $organizationId,
                ':cat_id' => $catId,
                ':start_date' => $budget['start_date'],
                ':end_date' => $budget['end_date'],
            ];

            if ($deptId) {
                $spendSql .= " AND e.department_id = :dept_id";
                $spendParams[':dept_id'] = $deptId;
            }

            $spendStmt = $this->pdo->prepare($spendSql);
            $spendStmt->execute($spendParams);
            $actualSpent = (float)$spendStmt->fetchColumn();

            $allocated = (float)$it['allocated_amount'];
            $it['actual_spent'] = $actualSpent;
            $it['remaining_amount'] = round($allocated - $actualSpent, 2);
            $it['percentage_spent'] = $allocated > 0 ? min(100, round(($actualSpent / $allocated) * 100, 1)) : 0;

            $totalAllocatedItems += $allocated;
            $totalSpentItems += $actualSpent;
        }

        $budget['items'] = $items;
        $budget['total_allocated_items'] = $totalAllocatedItems;
        $budget['total_spent'] = $totalSpentItems;
        $budget['remaining_budget'] = max(0, round((float)$budget['total_budget'] - $totalSpentItems, 2));
        $budget['percentage_spent'] = (float)$budget['total_budget'] > 0 ? min(100, round(($totalSpentItems / (float)$budget['total_budget']) * 100, 1)) : 0;

        return $budget;
    }

    /**
     * Create a budget with budget items.
     * Enforces limit validation: total allocated across items cannot exceed total_budget.
     */
    public function createBudget(int $organizationId, array $data, ?int $userId = null): array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $budgetName = trim($data['budget_name'] ?? '');
        if (empty($budgetName)) {
            throw new InvalidArgumentException("Budget name is required.");
        }

        $financialYear = trim($data['financial_year'] ?? '');
        if (empty($financialYear)) {
            throw new InvalidArgumentException("Financial year is required (e.g. '2026-2027').");
        }

        $startDate = trim($data['start_date'] ?? '');
        $endDate = trim($data['end_date'] ?? '');
        if (empty($startDate) || empty($endDate)) {
            throw new InvalidArgumentException("Start date and end date are required.");
        }
        if ($endDate < $startDate) {
            throw new InvalidArgumentException("End date cannot be prior to start date.");
        }

        $totalBudget = max(0, (float)($data['total_budget'] ?? 0));
        $status = trim($data['status'] ?? 'draft');
        if (!in_array($status, ['draft', 'active', 'closed', 'cancelled'], true)) {
            $status = 'draft';
        }

        // Validate items if provided
        $items = $data['items'] ?? [];
        $allocatedSum = 0;
        foreach ($items as $it) {
            $catId = (int)($it['finance_category_id'] ?? 0);
            $cat = $this->getCategory($organizationId, $catId);
            if (!$cat) {
                throw new InvalidArgumentException("Category ID {$catId} not found or access denied.");
            }
            $itemAllocated = max(0, (float)($it['allocated_amount'] ?? 0));
            $allocatedSum += $itemAllocated;
        }

        // Budget limit validation
        if ($allocatedSum > $totalBudget && $totalBudget > 0) {
            throw new InvalidArgumentException("Sum of allocated items (₹" . number_format($allocatedSum, 2) . ") exceeds the total budget limit of ₹" . number_format($totalBudget, 2) . ".");
        }

        if ($totalBudget == 0 && $allocatedSum > 0) {
            $totalBudget = $allocatedSum;
        }

        $this->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO budgets (
                    organization_id, budget_name, financial_year,
                    start_date, end_date, total_budget, status,
                    created_at, updated_at
                ) VALUES (
                    :org_id, :budget_name, :fy,
                    :start_date, :end_date, :total_budget, :status,
                    NOW(), NOW()
                )
            ");

            $stmt->execute([
                ':org_id' => $organizationId,
                ':budget_name' => $budgetName,
                ':fy' => $financialYear,
                ':start_date' => $startDate,
                ':end_date' => $endDate,
                ':total_budget' => $totalBudget,
                ':status' => $status,
            ]);

            $newBudgetId = (int)$this->pdo->lastInsertId();

            if (!empty($items)) {
                $itemStmt = $this->pdo->prepare("
                    INSERT INTO budget_items (
                        budget_id, finance_category_id, department_id, allocated_amount, description
                    ) VALUES (
                        :bid, :cat_id, :dept_id, :allocated, :description
                    )
                ");

                foreach ($items as $it) {
                    $itemStmt->execute([
                        ':bid' => $newBudgetId,
                        ':cat_id' => (int)$it['finance_category_id'],
                        ':dept_id' => !empty($it['department_id']) ? (int)$it['department_id'] : null,
                        ':allocated' => max(0, (float)($it['allocated_amount'] ?? 0)),
                        ':description' => !empty($it['description']) ? trim($it['description']) : null,
                    ]);
                }
            }

            $this->commit();

            $auditUser = $this->resolveAuditUserId($userId, $organizationId);
            if ($this->auditLogService && $auditUser) {
                try {
                    $this->auditLogService->log(
                        $organizationId,
                        $auditUser,
                        'create',
                        'budget',
                        $newBudgetId,
                        null,
                        ['budget_name' => $budgetName, 'total_budget' => $totalBudget, 'status' => $status]
                    );
                } catch (\Throwable $e) {}
            }

            return $this->getBudget($organizationId, $newBudgetId);
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    /**
     * Add a line item to an existing budget.
     */
    public function addBudgetItem(int $organizationId, int $budgetId, array $data): array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $budget = $this->getBudget($organizationId, $budgetId);
        if (!$budget) {
            throw new InvalidArgumentException("Budget not found or access denied.");
        }

        $categoryId = (int)($data['finance_category_id'] ?? 0);
        $cat = $this->getCategory($organizationId, $categoryId);
        if (!$cat) {
            throw new InvalidArgumentException("Finance category not found or access denied.");
        }

        $allocated = max(0, (float)($data['allocated_amount'] ?? 0));
        if ($allocated <= 0) {
            throw new InvalidArgumentException("Allocated amount must be greater than zero.");
        }

        $newTotalAllocated = $budget['total_allocated_items'] + $allocated;
        if ($newTotalAllocated > (float)$budget['total_budget'] && (float)$budget['total_budget'] > 0) {
            throw new InvalidArgumentException("Adding this item (₹" . number_format($allocated, 2) . ") exceeds total budget limit. Remaining allocatable: ₹" . number_format((float)$budget['total_budget'] - $budget['total_allocated_items'], 2));
        }

        $departmentId = !empty($data['department_id']) ? (int)$data['department_id'] : null;
        $description = !empty($data['description']) ? trim($data['description']) : null;

        $stmt = $this->pdo->prepare("
            INSERT INTO budget_items (
                budget_id, finance_category_id, department_id, allocated_amount, description
            ) VALUES (
                :bid, :cat_id, :dept_id, :allocated, :description
            )
        ");

        $stmt->execute([
            ':bid' => $budgetId,
            ':cat_id' => $categoryId,
            ':dept_id' => $departmentId,
            ':allocated' => $allocated,
            ':description' => $description,
        ]);

        return $this->getBudget($organizationId, $budgetId);
    }

    /**
     * Update budget status.
     */
    public function setBudgetStatus(int $organizationId, int $budgetId, string $status, ?int $userId = null): bool
    {
        if (!in_array($status, ['draft', 'active', 'closed', 'cancelled'], true)) {
            throw new InvalidArgumentException("Invalid budget status: {$status}");
        }

        $existing = $this->getBudget($organizationId, $budgetId);
        if (!$existing) {
            throw new InvalidArgumentException("Budget not found or access denied.");
        }

        $stmt = $this->pdo->prepare("
            UPDATE budgets SET
                status = :status,
                updated_at = NOW()
            WHERE id = :id AND organization_id = :org_id
        ");

        $ok = $stmt->execute([
            ':status' => $status,
            ':id' => $budgetId,
            ':org_id' => $organizationId,
        ]);

        $auditUser = $this->resolveAuditUserId($userId, $organizationId);
        if ($ok && $this->auditLogService && $auditUser) {
            try {
                $this->auditLogService->log(
                    $organizationId,
                    $auditUser,
                    'update_status',
                    'budget',
                    $budgetId,
                    ['status' => $existing['status']],
                    ['status' => $status]
                );
            } catch (\Throwable $e) {}
        }

        return $ok;
    }

    // =========================================================================
    // SECTION 6: PAYMENTS & INTEGRATION
    // =========================================================================

    /**
     * List recorded finance payments with filters and pagination.
     */
    public function listPayments(
        int $organizationId,
        int $page = 1,
        int $perPage = 15,
        array $filters = []
    ): array {
        if (!$this->pdo) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'limit' => $perPage, 'total_pages' => 0, 'total_amount' => 0];
        }

        $where = ["fp.organization_id = :org_id"];
        $params = [':org_id' => $organizationId];

        if (!empty($filters['payment_type']) && in_array($filters['payment_type'], ['expense', 'vendor_invoice', 'payroll', 'other'], true)) {
            $where[] = "fp.payment_type = :type";
            $params[':type'] = $filters['payment_type'];
        }

        if (!empty($filters['expense_id'])) {
            $where[] = "fp.expense_id = :exp_id";
            $params[':exp_id'] = (int)$filters['expense_id'];
        }

        if (!empty($filters['vendor_invoice_id'])) {
            $where[] = "fp.vendor_invoice_id = :inv_id";
            $params[':inv_id'] = (int)$filters['vendor_invoice_id'];
        }

        if (!empty($filters['start_date'])) {
            $where[] = "fp.payment_date >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $where[] = "fp.payment_date <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(fp.payment_reference LIKE :search OR fp.transaction_reference LIKE :search OR fp.notes LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $whereClause = implode(" AND ", $where);

        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*), COALESCE(SUM(fp.amount), 0)
            FROM finance_payments fp
            WHERE {$whereClause}
        ");
        $countStmt->execute($params);
        [$total, $totalAmount] = $countStmt->fetch(PDO::FETCH_NUM);
        $total = (int)$total;
        $totalAmount = (float)$totalAmount;

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT 
                fp.*,
                e.expense_reference,
                e.description as expense_description,
                vi.invoice_number,
                v.company_name as vendor_name,
                COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Staff') as creator_name
            FROM finance_payments fp
            LEFT JOIN expenses e ON fp.expense_id = e.id
            LEFT JOIN vendor_invoices vi ON fp.vendor_invoice_id = vi.id
            LEFT JOIN vendors v ON vi.vendor_id = v.id
            LEFT JOIN users u ON fp.created_by = u.id
            WHERE {$whereClause}
            ORDER BY fp.payment_date DESC, fp.id DESC
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
            'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Get a single payment by ID.
     */
    public function getPayment(int $organizationId, int $paymentId): ?array
    {
        if (!$this->pdo) return null;

        $stmt = $this->pdo->prepare("
            SELECT 
                fp.*,
                e.expense_reference,
                e.description as expense_description,
                vi.invoice_number,
                v.company_name as vendor_name,
                COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Staff') as creator_name
            FROM finance_payments fp
            LEFT JOIN expenses e ON fp.expense_id = e.id
            LEFT JOIN vendor_invoices vi ON fp.vendor_invoice_id = vi.id
            LEFT JOIN vendors v ON vi.vendor_id = v.id
            LEFT JOIN users u ON fp.created_by = u.id
            WHERE fp.id = :id AND fp.organization_id = :org_id
            LIMIT 1
        ");
        $stmt->execute([':id' => $paymentId, ':org_id' => $organizationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Record a payment and synchronize related entity status:
     * - If payment_type = 'expense': Marks expense as 'paid', records payment_method and reference.
     * - If payment_type = 'vendor_invoice': Calculates total payments against invoice and updates payment_status to 'partially_paid' or 'paid'.
     */
    public function recordPayment(int $organizationId, array $data, ?int $userId = null): array
    {
        if (!$this->pdo) {
            throw new RuntimeException("Database connection unavailable.");
        }

        $paymentType = trim($data['payment_type'] ?? '');
        if (!in_array($paymentType, ['expense', 'vendor_invoice', 'payroll', 'other'], true)) {
            throw new InvalidArgumentException("Valid payment type required (expense, vendor_invoice, payroll, other).");
        }

        $amount = (float)($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException("Payment amount must be greater than zero.");
        }

        $paymentMethod = trim($data['payment_method'] ?? 'Bank Transfer');
        if (empty($paymentMethod)) {
            $paymentMethod = 'Bank Transfer';
        }

        $paymentDate = !empty($data['payment_date']) ? trim($data['payment_date']) : date('Y-m-d');
        $transactionReference = !empty($data['transaction_reference']) ? trim($data['transaction_reference']) : null;
        $notes = !empty($data['notes']) ? trim($data['notes']) : null;
        $auditUser = $this->resolveAuditUserId($userId, $organizationId);

        $expenseId = !empty($data['expense_id']) ? (int)$data['expense_id'] : null;
        $vendorInvoiceId = !empty($data['vendor_invoice_id']) ? (int)$data['vendor_invoice_id'] : null;
        $payrollId = !empty($data['payroll_id']) ? (int)$data['payroll_id'] : null;

        $this->beginTransaction();
        try {
            // 1. Process Expense Payment
            if ($paymentType === 'expense') {
                if (!$expenseId) {
                    throw new InvalidArgumentException("Expense ID is required for expense payments.");
                }
                $expStmt = $this->pdo->prepare("
                    SELECT * FROM expenses 
                    WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
                    FOR UPDATE
                ");
                $expStmt->execute([':id' => $expenseId, ':org_id' => $organizationId]);
                $expense = $expStmt->fetch(PDO::FETCH_ASSOC);
                if (!$expense) {
                    throw new InvalidArgumentException("Expense not found or access denied.");
                }
                if ($expense['payment_status'] === 'cancelled' || $expense['payment_status'] === 'rejected') {
                    throw new InvalidArgumentException("Cannot pay a {$expense['payment_status']} expense.");
                }

                // Generate payment reference
                $paymentRef = $this->generatePaymentReference($organizationId);

                // Insert into finance_payments (Note: finance_payments has NO updated_at)
                $payStmt = $this->pdo->prepare("
                    INSERT INTO finance_payments (
                        organization_id, payment_reference, payment_type,
                        expense_id, vendor_invoice_id, payroll_id,
                        payment_date, amount, payment_method,
                        transaction_reference, notes, created_by, created_at
                    ) VALUES (
                        :org_id, :pref, :ptype,
                        :exp_id, NULL, NULL,
                        :pdate, :amount, :pmethod,
                        :tref, :notes, :cby, NOW()
                    )
                ");
                $payStmt->execute([
                    ':org_id' => $organizationId,
                    ':pref' => $paymentRef,
                    ':ptype' => 'expense',
                    ':exp_id' => $expenseId,
                    ':pdate' => $paymentDate,
                    ':amount' => $amount,
                    ':pmethod' => $paymentMethod,
                    ':tref' => $transactionReference,
                    ':notes' => $notes,
                    ':cby' => $auditUser,
                ]);
                $newPaymentId = (int)$this->pdo->lastInsertId();

                // Update expense status to 'paid'
                $updExp = $this->pdo->prepare("
                    UPDATE expenses SET
                        payment_status = 'paid',
                        payment_method = :pmethod,
                        payment_reference = :pref,
                        paid_at = NOW(),
                        updated_at = NOW()
                    WHERE id = :id AND organization_id = :org_id
                ");
                $updExp->execute([
                    ':pmethod' => $paymentMethod,
                    ':pref' => $transactionReference ?: $paymentRef,
                    ':id' => $expenseId,
                    ':org_id' => $organizationId,
                ]);
            }
            // 2. Process Vendor Invoice Payment
            elseif ($paymentType === 'vendor_invoice') {
                if (!$vendorInvoiceId) {
                    throw new InvalidArgumentException("Vendor Invoice ID is required for invoice payments.");
                }
                $invStmt = $this->pdo->prepare("
                    SELECT * FROM vendor_invoices 
                    WHERE id = :id AND organization_id = :org_id
                    FOR UPDATE
                ");
                $invStmt->execute([':id' => $vendorInvoiceId, ':org_id' => $organizationId]);
                $invoice = $invStmt->fetch(PDO::FETCH_ASSOC);
                if (!$invoice) {
                    throw new InvalidArgumentException("Vendor invoice not found or access denied.");
                }
                if ($invoice['payment_status'] === 'cancelled') {
                    throw new InvalidArgumentException("Cannot record payment for a cancelled vendor invoice.");
                }

                $paymentRef = $this->generatePaymentReference($organizationId);

                // Insert into finance_payments
                $payStmt = $this->pdo->prepare("
                    INSERT INTO finance_payments (
                        organization_id, payment_reference, payment_type,
                        expense_id, vendor_invoice_id, payroll_id,
                        payment_date, amount, payment_method,
                        transaction_reference, notes, created_by, created_at
                    ) VALUES (
                        :org_id, :pref, :ptype,
                        NULL, :inv_id, NULL,
                        :pdate, :amount, :pmethod,
                        :tref, :notes, :cby, NOW()
                    )
                ");
                $payStmt->execute([
                    ':org_id' => $organizationId,
                    ':pref' => $paymentRef,
                    ':ptype' => 'vendor_invoice',
                    ':inv_id' => $vendorInvoiceId,
                    ':pdate' => $paymentDate,
                    ':amount' => $amount,
                    ':pmethod' => $paymentMethod,
                    ':tref' => $transactionReference,
                    ':notes' => $notes,
                    ':cby' => $auditUser,
                ]);
                $newPaymentId = (int)$this->pdo->lastInsertId();

                // Compute total paid so far
                $sumStmt = $this->pdo->prepare("
                    SELECT COALESCE(SUM(amount), 0)
                    FROM finance_payments 
                    WHERE vendor_invoice_id = :inv_id AND organization_id = :org_id
                ");
                $sumStmt->execute([':inv_id' => $vendorInvoiceId, ':org_id' => $organizationId]);
                $totalPaid = (float)$sumStmt->fetchColumn();

                $invoiceTotal = (float)$invoice['total_amount'];
                $newStatus = ($totalPaid >= $invoiceTotal) ? 'paid' : ($totalPaid > 0 ? 'partially_paid' : 'unpaid');

                $updInv = $this->pdo->prepare("
                    UPDATE vendor_invoices SET
                        payment_status = :status,
                        updated_at = NOW()
                    WHERE id = :id AND organization_id = :org_id
                ");
                $updInv->execute([
                    ':status' => $newStatus,
                    ':id' => $vendorInvoiceId,
                    ':org_id' => $organizationId,
                ]);
            }
            // 3. Process Payroll or Other Payment
            else {
                $paymentRef = $this->generatePaymentReference($organizationId);

                $payStmt = $this->pdo->prepare("
                    INSERT INTO finance_payments (
                        organization_id, payment_reference, payment_type,
                        expense_id, vendor_invoice_id, payroll_id,
                        payment_date, amount, payment_method,
                        transaction_reference, notes, created_by, created_at
                    ) VALUES (
                        :org_id, :pref, :ptype,
                        NULL, NULL, :payroll_id,
                        :pdate, :amount, :pmethod,
                        :tref, :notes, :cby, NOW()
                    )
                ");
                $payStmt->execute([
                    ':org_id' => $organizationId,
                    ':pref' => $paymentRef,
                    ':ptype' => $paymentType,
                    ':payroll_id' => $payrollId,
                    ':pdate' => $paymentDate,
                    ':amount' => $amount,
                    ':pmethod' => $paymentMethod,
                    ':tref' => $transactionReference,
                    ':notes' => $notes,
                    ':cby' => $auditUser,
                ]);
                $newPaymentId = (int)$this->pdo->lastInsertId();
            }

            $this->commit();

            if ($this->auditLogService && $auditUser) {
                try {
                    $this->auditLogService->log(
                        $organizationId,
                        $auditUser,
                        'record_payment',
                        'finance_payment',
                        $newPaymentId,
                        null,
                        ['payment_type' => $paymentType, 'amount' => $amount, 'payment_reference' => $paymentRef]
                    );
                } catch (\Throwable $e) {}
            }

            return $this->getPayment($organizationId, $newPaymentId);
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // SECTION 7: FINANCIAL DASHBOARD & KPI ANALYTICS
    // =========================================================================

    /**
     * Get aggregate financial summary KPIs for the dashboard.
     */
    public function getFinancialSummary(int $organizationId): array
    {
        if (!$this->pdo) {
            return [
                'total_income' => 0,
                'total_expenses' => 0,
                'net_cashflow' => 0,
                'pending_expenses_count' => 0,
                'pending_expenses_amount' => 0,
                'unpaid_invoices_count' => 0,
                'unpaid_invoices_amount' => 0,
                'active_budgets_count' => 0,
                'total_budget_allocated' => 0,
                'total_budget_spent' => 0,
            ];
        }

        // 1. Total Income (received)
        $incStmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM income_transactions
            WHERE organization_id = :org_id AND status = 'received'
        ");
        $incStmt->execute([':org_id' => $organizationId]);
        $totalIncome = (float)$incStmt->fetchColumn();

        // 2. Total Expenses (paid)
        $expStmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(total_amount), 0)
            FROM expenses
            WHERE organization_id = :org_id AND payment_status = 'paid' AND deleted_at IS NULL
        ");
        $expStmt->execute([':org_id' => $organizationId]);
        $totalExpenses = (float)$expStmt->fetchColumn();

        // 3. Pending Expenses
        $pendExpStmt = $this->pdo->prepare("
            SELECT COUNT(*), COALESCE(SUM(total_amount), 0)
            FROM expenses
            WHERE organization_id = :org_id AND payment_status = 'pending' AND deleted_at IS NULL
        ");
        $pendExpStmt->execute([':org_id' => $organizationId]);
        [$pendingExpCount, $pendingExpAmount] = $pendExpStmt->fetch(PDO::FETCH_NUM);

        // 4. Unpaid Vendor Invoices
        $unpaidInvStmt = $this->pdo->prepare("
            SELECT COUNT(*), COALESCE(SUM(total_amount), 0)
            FROM vendor_invoices
            WHERE organization_id = :org_id AND payment_status IN ('unpaid', 'partially_paid')
        ");
        $unpaidInvStmt->execute([':org_id' => $organizationId]);
        [$unpaidInvCount, $unpaidInvAmount] = $unpaidInvStmt->fetch(PDO::FETCH_NUM);

        // 5. Active Budgets
        $budStmt = $this->pdo->prepare("
            SELECT 
                COUNT(*),
                COALESCE(SUM(total_budget), 0)
            FROM budgets
            WHERE organization_id = :org_id AND status = 'active'
        ");
        $budStmt->execute([':org_id' => $organizationId]);
        [$activeBudgetsCount, $totalBudgetAllocated] = $budStmt->fetch(PDO::FETCH_NUM);

        // 6. Total budget spent across active budgets
        $budSpentStmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(e.total_amount), 0)
            FROM expenses e
            JOIN budgets b ON e.organization_id = b.organization_id
            WHERE b.organization_id = :org_id 
              AND b.status = 'active'
              AND e.expense_date BETWEEN b.start_date AND b.end_date
              AND e.payment_status NOT IN ('cancelled', 'rejected')
              AND e.deleted_at IS NULL
        ");
        $budSpentStmt->execute([':org_id' => $organizationId]);
        $totalBudgetSpent = (float)$budSpentStmt->fetchColumn();

        return [
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'net_cashflow' => round($totalIncome - $totalExpenses, 2),
            'pending_expenses_count' => (int)$pendingExpCount,
            'pending_expenses_amount' => (float)$pendingExpAmount,
            'unpaid_invoices_count' => (int)$unpaidInvCount,
            'unpaid_invoices_amount' => (float)$unpaidInvAmount,
            'active_budgets_count' => (int)$activeBudgetsCount,
            'total_budget_allocated' => (float)$totalBudgetAllocated,
            'total_budget_spent' => $totalBudgetSpent,
        ];
    }

    /**
     * Alias for getFinancialSummary.
     */
    public function getFinanceSummary(int $organizationId, ?int $year = null, ?int $month = null): array
    {
        return $this->getFinancialSummary($organizationId);
    }

    /**
     * Get category-level financial aggregation summary.
     */
    public function getCategorySummary(int $organizationId, string $type = 'expense', ?string $dateFrom = null, ?string $dateTo = null): array
    {
        if (!$this->pdo) return [];

        if ($type === 'income') {
            $where = ["it.organization_id = :org"];
            $params = [':org' => $organizationId];
            if ($dateFrom) {
                $where[] = "it.income_date >= :dfrom";
                $params[':dfrom'] = $dateFrom;
            }
            if ($dateTo) {
                $where[] = "it.income_date <= :dto";
                $params[':dto'] = $dateTo;
            }
            $stmt = $this->pdo->prepare("
                SELECT fc.id as category_id, fc.name as category_name, COUNT(it.id) as count, COALESCE(SUM(it.amount), 0) as total_amount
                FROM income_transactions it
                LEFT JOIN finance_categories fc ON it.finance_category_id = fc.id
                WHERE " . implode(" AND ", $where) . "
                GROUP BY fc.id, fc.name
                ORDER BY total_amount DESC
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $where = ["e.organization_id = :org", "e.deleted_at IS NULL"];
            $params = [':org' => $organizationId];
            if ($dateFrom) {
                $where[] = "e.expense_date >= :dfrom";
                $params[':dfrom'] = $dateFrom;
            }
            if ($dateTo) {
                $where[] = "e.expense_date <= :dto";
                $params[':dto'] = $dateTo;
            }
            $stmt = $this->pdo->prepare("
                SELECT fc.id as category_id, fc.name as category_name, COUNT(e.id) as count, COALESCE(SUM(e.total_amount), 0) as total_amount
                FROM expenses e
                LEFT JOIN finance_categories fc ON e.finance_category_id = fc.id
                WHERE " . implode(" AND ", $where) . "
                GROUP BY fc.id, fc.name
                ORDER BY total_amount DESC
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }

    /**
     * Check budget threshold alerts for an organization.
     * Can check a single budget or all active budgets.
     */
    public function checkBudgetAlerts(int $organizationId, ?int $budgetId = null, float $threshold = 90.0): array
    {
        $notifService = new \App\Services\Notification\NotificationService($this->pdo);
        if ($budgetId) {
            return $notifService->checkAndTriggerBudgetThreshold($organizationId, $budgetId, $threshold);
        }

        $bStmt = $this->pdo->prepare("
            SELECT id FROM budgets 
            WHERE organization_id = :org_id AND status = 'active'
        ");
        $bStmt->execute([':org_id' => $organizationId]);
        $budgetIds = $bStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $results = [];
        foreach ($budgetIds as $bId) {
            $results[] = $notifService->checkAndTriggerBudgetThreshold($organizationId, (int)$bId, $threshold);
        }

        return [
            'checked_count' => count($budgetIds),
            'results' => $results,
        ];
    }
}
