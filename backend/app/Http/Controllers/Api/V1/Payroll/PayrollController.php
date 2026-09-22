<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Http\Controllers\Controller;
use App\Services\Payroll\PayrollService;
use App\Helpers\ApiResponse;
use Exception;

/**
 * Manages payroll operations including salary structures, payroll periods, processing, and payment tracking.
 */
class PayrollController extends Controller
{
    protected PayrollService $payrollService;

    /**
     * Create a new PayrollController instance.
     *
     * @param PayrollService|null $payrollService Payroll service instance
     */
    public function __construct(?PayrollService $payrollService = null)
    {
        $this->payrollService = $payrollService ?? new PayrollService();
    }

    /**
     * Retrieve salary structure for an employee.
     *
     * @param int $orgId Organization ID
     * @param int $employeeId Employee ID
     * @return array API response with salary structure or error
     */
    public function getSalaryStructure(int $orgId, int $employeeId): array
    {
        $struct = $this->payrollService->getSalaryStructure($orgId, $employeeId);
        if (!$struct) {
            return ApiResponse::error('Salary structure not found for this employee.', null, 404);
        }
        return ApiResponse::success($struct, 'Salary structure retrieved', 200);
    }

    /**
     * Configure or update salary structure for an employee.
     *
     * @param int $orgId Organization ID
     * @param int $employeeId Employee ID
     * @param array $requestData Salary structure data
     * @param int|null $performedBy User ID performing this action
     * @return array API response with configured structure or error
     */
    public function setSalaryStructure(int $orgId, int $employeeId, array $requestData, ?int $performedBy = null): array
    {
        $struct = $this->payrollService->setSalaryStructure($orgId, $employeeId, $requestData, $performedBy);
        if (!$struct) {
            return ApiResponse::error('Failed to configure salary structure.', null, 422);
        }
        return ApiResponse::success($struct, 'Salary structure configured successfully', 200);
    }

    /**
     * List all payroll periods for an organization.
     *
     * @param int $orgId Organization ID
     * @return array API response with list of payroll periods
     */
    public function periods(int $orgId): array
    {
        $periods = $this->payrollService->listPeriods($orgId);
        return ApiResponse::success($periods, 'Payroll periods retrieved', 200);
    }

    /**
     * Create a new payroll period.
     *
     * @param int $orgId Organization ID
     * @param array $requestData Period data including period_name, start_date, and end_date
     * @param int|null $performedBy User ID performing this action
     * @return array API response with created period or error
     */
    public function storePeriod(int $orgId, array $requestData, ?int $performedBy = null): array
    {
        if (empty($requestData['period_name']) || empty($requestData['start_date']) || empty($requestData['end_date'])) {
            return ApiResponse::error('Period name, start date, and end date are required.', null, 422);
        }
        $period = $this->payrollService->createPeriod($orgId, $requestData, $performedBy);
        return ApiResponse::success($period, 'Payroll period created successfully', 201);
    }

    /**
     * Update the status of a payroll period.
     *
     * @param int $orgId Organization ID
     * @param int $periodId Payroll period ID
     * @param array $requestData Status data including new status
     * @param int|null $performedBy User ID performing this action
     * @return array API response confirming status update or error
     */
    public function updatePeriodStatus(int $orgId, int $periodId, array $requestData, ?int $performedBy = null): array
    {
        $status = $requestData['status'] ?? '';
        try {
            $ok = $this->payrollService->updatePeriodStatus($orgId, $periodId, $status, $performedBy);
            if (!$ok) {
                return ApiResponse::error('Failed to update period status.', null, 400);
            }
            return ApiResponse::success(null, "Payroll period status updated to {$status}", 200);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }
    }

    /**
     * Retrieve payroll records with optional filtering by period.
     *
     * @param int $orgId Organization ID
     * @param array $requestData Query parameters including optional period_id, limit, and offset
     * @return array API response with payroll records
     */
    public function records(int $orgId, array $requestData): array
    {
        $periodId = !empty($requestData['period_id']) ? (int)$requestData['period_id'] : null;
        $limit = (int)($requestData['limit'] ?? 50);
        $offset = (int)($requestData['offset'] ?? 0);
        $records = $this->payrollService->listPayrollRecords($orgId, $periodId, $limit, $offset);
        return ApiResponse::success($records, 'Payroll records retrieved', 200);
    }

    /**
     * Process payroll for a specific employee in a payroll period.
     *
     * @param int $orgId Organization ID
     * @param int $periodId Payroll period ID
     * @param int $employeeId Employee ID
     * @param array $requestData Processing parameters
     * @param int|null $performedBy User ID performing this action
     * @return array API response with processed payroll or error
     */
    public function process(int $orgId, int $periodId, int $employeeId, array $requestData, ?int $performedBy = null): array
    {
        try {
            $res = $this->payrollService->processEmployeePayroll($orgId, $periodId, $employeeId, $requestData, $performedBy);
            return ApiResponse::success($res, 'Employee payroll processed successfully', 200);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }
    }

    /**
     * Update payment status and details for a payroll record.
     *
     * @param int $orgId Organization ID
     * @param int $payrollId Payroll record ID
     * @param array $requestData Payment data including payment_status, payment_date, payment_reference, and remarks
     * @param int|null $performedBy User ID performing this action
     * @return array API response confirming payment update or error
     */
    public function updatePayment(int $orgId, int $payrollId, array $requestData, ?int $performedBy = null): array
    {
        $status = $requestData['payment_status'] ?? 'paid';
        $date = $requestData['payment_date'] ?? null;
        $ref = $requestData['payment_reference'] ?? null;
        $remarks = $requestData['remarks'] ?? null;

        $ok = $this->payrollService->updatePaymentStatus($orgId, $payrollId, $status, $date, $ref, $remarks, $performedBy);
        if (!$ok) {
            return ApiResponse::error('Failed to update payment status.', null, 400);
        }
        return ApiResponse::success(null, "Payment status updated to {$status}", 200);
    }
}
