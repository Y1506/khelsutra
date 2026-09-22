<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Helpers\ApiResponse;
use App\Services\Operations\EventService;
use App\Models\SchoolActivity;
use Exception;

class SchoolActivityController
{
    protected EventService $service;

    /**
     * Create a new SchoolActivityController instance.
     *
     * @param EventService $service The event service
     */
    public function __construct(EventService $service)
    {
        $this->service = $service;
    }

    /**
     * Get all school activities for an organization.
     *
     * @param int $orgId The organization ID
     * @param array $requestData Request parameters
     * @return array API response with school activities data
     */
    public function index(int $orgId, array $requestData): array
    {
        $activities = SchoolActivity::where('organization_id', $orgId)->get();
        return ApiResponse::success(['data' => $activities->toArray()]);
    }

    /**
     * Create a new school activity.
     *
     * @param int $orgId The organization ID
     * @param array $requestData Activity data
     * @return array API response with created activity or error
     */
    public function store(int $orgId, array $requestData): array
    {
        try {
            $activity = $this->service->createSchoolActivity($orgId, $requestData);
            return ApiResponse::success($activity->toArray(), 'School activity created', 201);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }
}
