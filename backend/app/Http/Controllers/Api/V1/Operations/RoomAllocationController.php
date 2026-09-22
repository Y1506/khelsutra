<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Helpers\ApiResponse;
use App\Services\Operations\RoomAllocationService;
use App\Models\AccommodationAllocation;
use Exception;

class RoomAllocationController
{
    protected RoomAllocationService $service;

    /**
     * Create a new RoomAllocationController instance.
     *
     * @param RoomAllocationService $service The room allocation service
     */
    public function __construct(RoomAllocationService $service)
    {
        $this->service = $service;
    }

    /**
     * Get all room allocations for an organization.
     *
     * @param int $orgId The organization ID
     * @param array $requestData Request parameters
     * @return array API response with room allocations data
     */
    public function index(int $orgId, array $requestData): array
    {
        $alloc = AccommodationAllocation::where('organization_id', $orgId)->get();
        return ApiResponse::success(['data' => $alloc->toArray()]);
    }

    /**
     * Allocate a room to a person.
     *
     * @param int $orgId The organization ID
     * @param array $requestData Allocation data
     * @return array API response with created allocation or error
     */
    public function store(int $orgId, array $requestData): array
    {
        try {
            $alloc = $this->service->allocateRoom($orgId, $requestData);
            return ApiResponse::success($alloc->toArray(), 'Room allocated', 201);
        } catch (Exception $e) {
            $status = $e->getCode() == 409 ? 409 : 400;
            return ApiResponse::error($e->getMessage(), null, $status);
        }
    }
}
