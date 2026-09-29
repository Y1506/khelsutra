<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Services\Report\ReportService;
use App\Helpers\ApiResponse;

/**
 * Dedicated REST API controller for Reports:
 * Inventory, Purchases, Vendors, Equipment, Finance, and Operational intelligence.
 */
class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(?ReportService $reportService = null)
    {
        $this->reportService = $reportService ?? new ReportService();
    }

    /**
     * Operational reports.
     */
    public function operational(int $orgId): array
    {
        $data = $this->reportService->getOperationalReports($orgId);
        return ApiResponse::success($data, 'Operational reports retrieved successfully', 200);
    }

    /**
     * Inventory reports (summary, valuation, low stock, stock movements).
     */
    public function inventory(int $orgId, array $requestData = []): array
    {
        $filters = [
            'category_id' => !empty($requestData['category_id'] ?? ($_GET['category_id'] ?? null)) ? (int)($requestData['category_id'] ?? $_GET['category_id']) : null,
            'status' => $requestData['status'] ?? ($_GET['status'] ?? null),
            'date_from' => $requestData['date_from'] ?? ($_GET['date_from'] ?? null),
            'date_to' => $requestData['date_to'] ?? ($_GET['date_to'] ?? null),
            'search' => $requestData['search'] ?? ($_GET['search'] ?? null),
        ];

        $summary = $this->reportService->getInventorySummary($orgId, $filters);
        $valuation = $this->reportService->getInventoryValuation($orgId, $filters);
        $lowStock = $this->reportService->getLowStockReport($orgId, $filters);
        $movements = $this->reportService->getStockMovementReport($orgId, array_merge($filters, ['limit' => 25]));

        return ApiResponse::success([
            'summary' => $summary,
            'valuation' => $valuation,
            'low_stock' => $lowStock,
            'recent_movements' => $movements,
        ], 'Inventory reports retrieved successfully', 200);
    }

    /**
     * Purchase and procurement reports.
     */
    public function purchases(int $orgId, array $requestData = []): array
    {
        $filters = [
            'date_from' => $requestData['date_from'] ?? ($_GET['date_from'] ?? null),
            'date_to' => $requestData['date_to'] ?? ($_GET['date_to'] ?? null),
            'status' => $requestData['status'] ?? ($_GET['status'] ?? null),
            'vendor_id' => !empty($requestData['vendor_id'] ?? ($_GET['vendor_id'] ?? null)) ? (int)($requestData['vendor_id'] ?? $_GET['vendor_id']) : null,
        ];

        $summary = $this->reportService->getPurchaseSummary($orgId, $filters);
        $orders = $this->reportService->getPurchaseRequestOrderStatusReport($orgId, $filters);
        $receipts = $this->reportService->getGoodsReceivedReport($orgId, $filters);
        $vendorSpend = $this->reportService->getProcurementVendorSpendReport($orgId, $filters);

        return ApiResponse::success([
            'summary' => $summary,
            'orders' => $orders,
            'goods_receipts' => $receipts,
            'vendor_spend' => $vendorSpend,
        ], 'Purchase reports retrieved successfully', 200);
    }

    /**
     * Vendor reports.
     */
    public function vendors(int $orgId, array $requestData = []): array
    {
        $filters = [
            'date_from' => $requestData['date_from'] ?? ($_GET['date_from'] ?? null),
            'date_to' => $requestData['date_to'] ?? ($_GET['date_to'] ?? null),
            'vendor_id' => !empty($requestData['vendor_id'] ?? ($_GET['vendor_id'] ?? null)) ? (int)($requestData['vendor_id'] ?? $_GET['vendor_id']) : null,
            'payment_status' => $requestData['payment_status'] ?? ($requestData['status'] ?? ($_GET['status'] ?? null)),
        ];

        $vendorSpend = $this->reportService->getVendorPurchaseSpendReport($orgId, $filters);
        $invoices = $this->reportService->getVendorInvoicePaymentReport($orgId, $filters);

        return ApiResponse::success([
            'spend_summary' => $vendorSpend,
            'invoices_summary' => $invoices,
        ], 'Vendor reports retrieved successfully', 200);
    }

    /**
     * Equipment reports.
     */
    public function equipment(int $orgId, array $requestData = []): array
    {
        $filters = [
            'assignee_type' => $requestData['assignee_type'] ?? ($_GET['assignee_type'] ?? null),
            'date_from' => $requestData['date_from'] ?? ($_GET['date_from'] ?? null),
            'date_to' => $requestData['date_to'] ?? ($_GET['date_to'] ?? null),
        ];

        $summary = $this->reportService->getEquipmentInventorySummary($orgId, $filters);
        $assigned = $this->reportService->getAssignedEquipmentReport($orgId, $filters);
        $returned = $this->reportService->getReturnedEquipmentReport($orgId, $filters);
        $overdue = $this->reportService->getOverdueEquipmentReport($orgId, $filters);
        $condition = $this->reportService->getEquipmentConditionSummary($orgId, $filters);

        return ApiResponse::success([
            'summary' => $summary,
            'assigned' => $assigned,
            'returned' => $returned,
            'overdue' => $overdue,
            'condition_summary' => $condition,
        ], 'Equipment reports retrieved successfully', 200);
    }

    /**
     * Financial reports.
     */
    public function finance(int $orgId, array $requestData = []): array
    {
        $filters = [
            'category_id' => !empty($requestData['category_id'] ?? ($_GET['category_id'] ?? null)) ? (int)($requestData['category_id'] ?? $_GET['category_id']) : null,
            'date_from' => $requestData['date_from'] ?? ($_GET['date_from'] ?? null),
            'date_to' => $requestData['date_to'] ?? ($_GET['date_to'] ?? null),
            'status' => $requestData['status'] ?? ($_GET['status'] ?? null),
        ];

        $summary = $this->reportService->getFinanceSummary($orgId, $filters);
        $income = $this->reportService->getIncomeSummary($orgId, $filters);
        $expense = $this->reportService->getExpenseSummary($orgId, $filters);
        $incomeVsExpense = $this->reportService->getIncomeVsExpenseReport($orgId, $filters);
        $budgetVsActual = $this->reportService->getBudgetVsActualReport($orgId, $filters);

        return ApiResponse::success([
            'summary' => $summary,
            'income' => $income,
            'expenses' => $expense,
            'income_vs_expense' => $incomeVsExpense,
            'budget_vs_actual' => $budgetVsActual,
        ], 'Financial reports retrieved successfully', 200);
    }
}
