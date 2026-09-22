<?php

namespace App\Http\Controllers\Api\V1\Audit;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogService;
use App\Helpers\ApiResponse;

class AuditLogController extends Controller
{
    protected AuditLogService $auditService;

    public function __construct(?AuditLogService $auditService = null)
    {
        $this->auditService = $auditService ?? new AuditLogService();
    }

    /**
     * Get audit log entries.
     *
     * @param int|null $orgId Organization ID (null for all organizations)
     * @param array $requestData Request parameters (limit, offset)
     * @return array API response with audit logs
     */
    public function index(?int $orgId, array $requestData): array
    {
        $limit = (int)($requestData['limit'] ?? 50);
        $offset = (int)($requestData['offset'] ?? 0);
        $logs = $this->auditService->getLogs($orgId, $limit, $offset);
        return ApiResponse::success($logs, 'Audit trail logs retrieved', 200);
    }
}
