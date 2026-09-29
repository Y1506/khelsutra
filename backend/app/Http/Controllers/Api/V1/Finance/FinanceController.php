<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\FinanceService;
use App\Helpers\ApiResponse;
use InvalidArgumentException;
use Exception;

/**
 * Dedicated REST API controller for Member 5 Financial Management:
 * Income transactions, Expense tracking & approvals, Budgets, Payments, and Summaries.
 */
class FinanceController extends Controller
{
    protected FinanceService $financeService;

    public function __construct(?FinanceService $financeService = null)
    {
        $this->financeService = $financeService ?? new FinanceService();
    }

    // =========================================================================
    // 1. INCOME TRANSACTIONS
    // =========================================================================

    public function indexIncome(int $orgId, array $requestData = []): array
    {
        $page = max(1, (int)($requestData['page'] ?? ($_GET['page'] ?? 1)));
        $limit = max(1, min(100, (int)($requestData['limit'] ?? ($_GET['limit'] ?? 15))));
        $search = trim($requestData['search'] ?? ($_GET['search'] ?? ''));
        $categoryId = !empty($requestData['category_id'] ?? ($_GET['category_id'] ?? null))
            ? (int)($requestData['category_id'] ?? $_GET['category_id'])
            : null;
        $dateFrom = $requestData['date_from'] ?? ($_GET['date_from'] ?? null);
        $dateTo = $requestData['date_to'] ?? ($_GET['date_to'] ?? null);

        $filters = [
            'search' => $search ?: null,
            'finance_category_id' => $categoryId,
            'start_date' => $dateFrom ?: null,
            'end_date' => $dateTo ?: null,
        ];

        $result = $this->financeService->listIncome(
            $orgId,
            $page,
            $limit,
            $filters
        );

        return ApiResponse::success($result, 'Income transactions retrieved successfully', 200);
    }

    public function showIncome(int $orgId, int $id): array
    {
        $income = $this->financeService->getIncome($orgId, $id);
        if (!$income) {
            return ApiResponse::error('Income transaction not found or access denied.', null, 404);
        }
        return ApiResponse::success($income, 'Income transaction retrieved successfully', 200);
    }

    public function storeIncome(int $orgId, array $requestData, ?int $performedBy = null): array
    {
        if (empty($requestData['amount']) || (float)$requestData['amount'] <= 0) {
            return ApiResponse::error('Amount is required and must be greater than zero.', ['amount' => ['Valid amount required.']], 422);
        }

        try {
            $created = $this->financeService->recordIncome($orgId, $requestData, $performedBy);
            return ApiResponse::success($created, 'Income transaction recorded successfully', 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to record income: ' . $e->getMessage(), null, 500);
        }
    }

    // =========================================================================
    // 2. EXPENSES
    // =========================================================================

    public function indexExpenses(int $orgId, array $requestData = []): array
    {
        $page = max(1, (int)($requestData['page'] ?? ($_GET['page'] ?? 1)));
        $limit = max(1, min(100, (int)($requestData['limit'] ?? ($_GET['limit'] ?? 15))));
        $search = trim($requestData['search'] ?? ($_GET['search'] ?? ''));
        $categoryId = !empty($requestData['category_id'] ?? ($_GET['category_id'] ?? null))
            ? (int)($requestData['category_id'] ?? $_GET['category_id'])
            : null;
        $vendorId = !empty($requestData['vendor_id'] ?? ($_GET['vendor_id'] ?? null))
            ? (int)($requestData['vendor_id'] ?? $_GET['vendor_id'])
            : null;
        $status = trim($requestData['payment_status'] ?? ($requestData['status'] ?? ($_GET['status'] ?? '')));
        $dateFrom = $requestData['date_from'] ?? ($_GET['date_from'] ?? null);
        $dateTo = $requestData['date_to'] ?? ($_GET['date_to'] ?? null);

        $filters = [
            'search' => $search ?: null,
            'finance_category_id' => $categoryId,
            'vendor_id' => $vendorId,
            'payment_status' => $status ?: null,
            'start_date' => $dateFrom ?: null,
            'end_date' => $dateTo ?: null,
        ];

        $result = $this->financeService->listExpenses(
            $orgId,
            $page,
            $limit,
            $filters
        );

        return ApiResponse::success($result, 'Expenses retrieved successfully', 200);
    }

    public function showExpense(int $orgId, int $id): array
    {
        $expense = $this->financeService->getExpense($orgId, $id);
        if (!$expense) {
            return ApiResponse::error('Expense not found or access denied.', null, 404);
        }
        return ApiResponse::success($expense, 'Expense retrieved successfully', 200);
    }

    public function storeExpense(int $orgId, array $requestData, ?int $performedBy = null): array
    {
        if (empty($requestData['amount']) || (float)$requestData['amount'] <= 0) {
            return ApiResponse::error('Amount is required and must be greater than zero.', ['amount' => ['Valid amount required.']], 422);
        }

        try {
            $created = $this->financeService->recordExpense($orgId, $requestData, $performedBy);
            return ApiResponse::success($created, 'Expense recorded successfully', 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to record expense: ' . $e->getMessage(), null, 500);
        }
    }

    public function approveExpense(int $orgId, int $id, ?int $performedBy = null): array
    {
        try {
            $ok = $this->financeService->approveExpense($orgId, $id, $performedBy);
            if (!$ok) {
                return ApiResponse::error('Expense not found or approval failed.', null, 404);
            }
            $updated = $this->financeService->getExpense($orgId, $id);
            return ApiResponse::success($updated, 'Expense approved successfully', 200);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to approve expense: ' . $e->getMessage(), null, 500);
        }
    }

    public function rejectExpense(int $orgId, int $id, array $requestData = [], ?int $performedBy = null): array
    {
        $reason = trim($requestData['rejection_reason'] ?? ($requestData['reason'] ?? ''));
        try {
            $ok = $this->financeService->rejectExpense($orgId, $id, $reason ?: null, $performedBy);
            if (!$ok) {
                return ApiResponse::error('Expense not found or rejection failed.', null, 404);
            }
            $updated = $this->financeService->getExpense($orgId, $id);
            return ApiResponse::success($updated, 'Expense rejected successfully', 200);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to reject expense: ' . $e->getMessage(), null, 500);
        }
    }

    // =========================================================================
    // 3. BUDGETS
    // =========================================================================

    public function indexBudgets(int $orgId, array $requestData = []): array
    {
        $page = max(1, (int)($requestData['page'] ?? ($_GET['page'] ?? 1)));
        $limit = max(1, min(100, (int)($requestData['limit'] ?? ($_GET['limit'] ?? 15))));
        $status = $requestData['status'] ?? ($_GET['status'] ?? null);
        $fiscalYear = $requestData['financial_year'] ?? ($requestData['fiscal_year'] ?? ($_GET['fiscal_year'] ?? null));

        $budgets = $this->financeService->listBudgets($orgId, $page, $limit, $status, $fiscalYear);
        return ApiResponse::success($budgets, 'Budgets retrieved successfully', 200);
    }

    public function showBudget(int $orgId, int $id): array
    {
        $budget = $this->financeService->getBudget($orgId, $id);
        if (!$budget) {
            return ApiResponse::error('Budget not found or access denied.', null, 404);
        }
        return ApiResponse::success($budget, 'Budget retrieved successfully', 200);
    }

    public function storeBudget(int $orgId, array $requestData, ?int $performedBy = null): array
    {
        if (empty($requestData['budget_name'])) {
            return ApiResponse::error('Budget name is required.', ['budget_name' => ['Budget name is required.']], 422);
        }

        try {
            $created = $this->financeService->createBudget($orgId, $requestData, $performedBy);
            return ApiResponse::success($created, 'Budget created successfully', 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to create budget: ' . $e->getMessage(), null, 500);
        }
    }

    public function updateBudget(int $orgId, int $id, array $requestData, ?int $performedBy = null): array
    {
        try {
            $ok = $this->financeService->updateBudget($orgId, $id, $requestData, $performedBy);
            if (!$ok) {
                return ApiResponse::error('Budget not found or update failed.', null, 404);
            }
            $updated = $this->financeService->getBudget($orgId, $id);
            return ApiResponse::success($updated, 'Budget updated successfully', 200);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to update budget: ' . $e->getMessage(), null, 500);
        }
    }

    // =========================================================================
    // 4. PAYMENTS
    // =========================================================================

    public function indexPayments(int $orgId, array $requestData = []): array
    {
        $page = max(1, (int)($requestData['page'] ?? ($_GET['page'] ?? 1)));
        $limit = max(1, min(100, (int)($requestData['limit'] ?? ($_GET['limit'] ?? 15))));
        $paymentType = $requestData['payment_type'] ?? ($_GET['payment_type'] ?? null);

        $filters = [];
        if ($paymentType) {
            $filters['payment_type'] = $paymentType;
        }

        $payments = $this->financeService->listPayments($orgId, $page, $limit, $filters);
        return ApiResponse::success($payments, 'Finance payments retrieved successfully', 200);
    }

    public function storePayment(int $orgId, array $requestData, ?int $performedBy = null): array
    {
        if (empty($requestData['amount']) || (float)$requestData['amount'] <= 0) {
            return ApiResponse::error('Payment amount must be greater than zero.', null, 422);
        }

        try {
            $created = $this->financeService->recordPayment($orgId, $requestData, $performedBy);
            return ApiResponse::success($created, 'Payment recorded successfully', 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to record payment: ' . $e->getMessage(), null, 500);
        }
    }

    // =========================================================================
    // 5. FINANCE CATEGORIES & SUMMARIES
    // =========================================================================

    public function indexCategories(int $orgId, array $requestData = []): array
    {
        $type = $requestData['category_type'] ?? ($requestData['type'] ?? ($_GET['type'] ?? null));
        $status = $requestData['status'] ?? ($_GET['status'] ?? 'active');

        $categories = $this->financeService->listCategories($orgId, $type, $status);
        return ApiResponse::success($categories, 'Finance categories retrieved successfully', 200);
    }

    public function showCategory(int $orgId, int $id): array
    {
        $cat = $this->financeService->getCategory($orgId, $id);
        if (!$cat) {
            return ApiResponse::error('Category not found or access denied.', null, 404);
        }
        return ApiResponse::success($cat, 'Category retrieved successfully', 200);
    }

    public function storeCategory(int $orgId, array $requestData, ?int $performedBy = null): array
    {
        if (empty($requestData['name'])) {
            return ApiResponse::error('Category name is required.', ['name' => ['Name field is required.']], 422);
        }

        try {
            $created = $this->financeService->createCategory($orgId, $requestData, $performedBy);
            return ApiResponse::success($created, 'Finance category created successfully', 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to create category: ' . $e->getMessage(), null, 500);
        }
    }

    public function summary(int $orgId, array $requestData = []): array
    {
        $year = !empty($requestData['year'] ?? ($_GET['year'] ?? null)) ? (int)($requestData['year'] ?? $_GET['year']) : null;
        $month = !empty($requestData['month'] ?? ($_GET['month'] ?? null)) ? (int)($requestData['month'] ?? $_GET['month']) : null;

        $summary = $this->financeService->getFinanceSummary($orgId, $year, $month);
        return ApiResponse::success($summary, 'Finance summary retrieved successfully', 200);
    }

    public function categorySummary(int $orgId, array $requestData = []): array
    {
        $type = $requestData['type'] ?? ($_GET['type'] ?? 'expense');
        $dateFrom = $requestData['date_from'] ?? ($_GET['date_from'] ?? null);
        $dateTo = $requestData['date_to'] ?? ($_GET['date_to'] ?? null);

        $summary = $this->financeService->getCategorySummary($orgId, $type, $dateFrom, $dateTo);
        return ApiResponse::success($summary, 'Category financial summary retrieved successfully', 200);
    }
}
