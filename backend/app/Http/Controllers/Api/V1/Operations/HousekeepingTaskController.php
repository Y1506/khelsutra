<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Helpers\ApiResponse;
use App\Services\Operations\HousekeepingTaskService;
use App\Models\HousekeepingTask;

class HousekeepingTaskController
{
    protected HousekeepingTaskService $service;

    public function __construct()
    {
        $this->service = new HousekeepingTaskService();
    }

    public function index(int $orgId, array $requestData): array
    {
        $query = HousekeepingTask::where('organization_id', $orgId);
        $tasks = $query->orderBy('created_at', 'desc')->get();
        return ApiResponse::success(['data' => $tasks->toArray()]);
    }

    public function store(int $orgId, array $requestData, int $performedBy): array
    {
        try {
            $task = $this->service->createManualTask($orgId, $requestData, $performedBy);
            return ApiResponse::success($task->toArray(), 'Housekeeping task created', 201);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }

    public function show(int $orgId, int $id): array
    {
        $task = HousekeepingTask::where('organization_id', $orgId)->findOrFail($id);
        return ApiResponse::success($task->toArray());
    }

    public function assign(int $orgId, int $id, array $requestData, int $performedBy): array
    {
        try {
            $task = $this->service->assignTask($orgId, $id, $requestData['assigned_employee_id'], $performedBy);
            return ApiResponse::success($task->toArray(), 'Task assigned successfully');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }

    public function updateStatus(int $orgId, int $id, string $status, array $requestData, int $performedBy): array
    {
        try {
            $task = $this->service->updateStatus($orgId, $id, $status, $performedBy, $requestData);
            return ApiResponse::success($task->toArray(), "Task status updated to {$status}");
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }

    public function destroy(int $orgId, int $id, int $performedBy): array
    {
        try {
            $task = HousekeepingTask::where('organization_id', $orgId)->findOrFail($id);
            $task->delete();
            return ApiResponse::success(null, 'Task deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }
}
