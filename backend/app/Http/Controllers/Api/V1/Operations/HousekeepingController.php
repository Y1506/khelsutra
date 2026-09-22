<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Helpers\ApiResponse;
use App\Services\Operations\HousekeepingService;
use App\Models\HousekeepingTask;

class HousekeepingController
{
    protected HousekeepingService $service;

    /**
     * Create a new HousekeepingController instance.
     */
    public function __construct()
    {
        $this->service = new HousekeepingService();
    }

    /**
     * Get all housekeeping tasks for an organization.
     *
     * @param int $orgId The organization ID
     * @param array $requestData Request parameters
     * @return array API response with housekeeping tasks data
     */
    public function index(int $orgId, array $requestData): array
    {
        $query = HousekeepingTask::where('organization_id', $orgId);
        $tasks = $query->orderBy('created_at', 'desc')->get();
        return ApiResponse::success(['data' => $tasks->toArray()]);
    }

    /**
     * Create a new housekeeping task.
     *
     * @param int $orgId The organization ID
     * @param array $requestData Task data
     * @return array API response with created task or error
     */
    public function store(int $orgId, array $requestData): array
    {
        try {
            $task = $this->service->createTask($orgId, $requestData);
            return ApiResponse::success($task->toArray(), 'Housekeeping task created', 201);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }

    /**
     * Update a housekeeping task.
     *
     * @param int $orgId The organization ID
     * @param int $id The task ID
     * @param array $requestData Updated task data
     * @return array API response with updated task or error
     */
    public function update(int $orgId, int $id, array $requestData): array
    {
        try {
            $task = $this->service->updateTask($orgId, $id, $requestData);
            return ApiResponse::success($task->toArray(), 'Housekeeping task updated');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }
}
