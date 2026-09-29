<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Helpers\ApiResponse;
use App\Services\Operations\HousekeepingScheduleService;
use App\Models\HousekeepingSchedule;

class HousekeepingScheduleController
{
    protected HousekeepingScheduleService $service;

    public function __construct()
    {
        $this->service = new HousekeepingScheduleService();
    }

    public function index(int $orgId, array $requestData): array
    {
        $query = HousekeepingSchedule::where('organization_id', $orgId);
        $schedules = $query->orderBy('created_at', 'desc')->get();
        return ApiResponse::success(['data' => $schedules->toArray()]);
    }

    public function store(int $orgId, array $requestData, int $performedBy): array
    {
        try {
            $schedule = $this->service->createSchedule($orgId, $requestData, $performedBy);
            return ApiResponse::success($schedule->toArray(), 'Housekeeping schedule created', 201);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }

    public function show(int $orgId, int $id): array
    {
        $schedule = HousekeepingSchedule::where('organization_id', $orgId)->findOrFail($id);
        return ApiResponse::success($schedule->toArray());
    }

    public function update(int $orgId, int $id, array $requestData, int $performedBy): array
    {
        try {
            $schedule = $this->service->updateSchedule($orgId, $id, $requestData, $performedBy);
            return ApiResponse::success($schedule->toArray(), 'Schedule updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }

    public function destroy(int $orgId, int $id, int $performedBy): array
    {
        try {
            $schedule = HousekeepingSchedule::where('organization_id', $orgId)->findOrFail($id);
            $schedule->delete();
            return ApiResponse::success(null, 'Schedule deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }

    public function generateTasks(int $orgId, array $requestData, int $performedBy): array
    {
        try {
            $tasks = $this->service->generateScheduledTasks($orgId);
            return ApiResponse::success(['tasks_generated' => count($tasks)], 'Scheduled tasks generated');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }
}
