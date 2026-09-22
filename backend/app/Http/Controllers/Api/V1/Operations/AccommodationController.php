<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Helpers\ApiResponse;
use App\Services\Operations\AccommodationService;
use App\Models\Accommodation;
use App\Models\AccommodationRoom;
use Exception;

class AccommodationController
{
    protected AccommodationService $service;

    /**
     * Create a new AccommodationController instance.
     *
     * @param AccommodationService $service The accommodation service
     */
    public function __construct(AccommodationService $service)
    {
        $this->service = $service;
    }

    /**
     * Get all accommodations for an organization.
     *
     * @param int $orgId The organization ID
     * @param array $requestData Request parameters
     * @return array API response with accommodations data
     */
    public function index(int $orgId, array $requestData): array
    {
        $acc = Accommodation::where('organization_id', $orgId)->get();
        return ApiResponse::success(['data' => $acc->toArray()]);
    }

    /**
     * Create a new accommodation.
     *
     * @param int $orgId The organization ID
     * @param array $requestData Accommodation data
     * @return array API response with created accommodation or error
     */
    public function store(int $orgId, array $requestData): array
    {
        try {
            $acc = $this->service->createAccommodation($orgId, $requestData);
            return ApiResponse::success($acc->toArray(), 'Accommodation created', 201);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }

    /**
     * Create a new room in an accommodation.
     *
     * @param int $orgId The organization ID
     * @param int $id The accommodation ID
     * @param array $requestData Room data
     * @return array API response with created room or error
     */
    public function storeRoom(int $orgId, int $id, array $requestData): array
    {
        try {
            $room = $this->service->createRoom($orgId, $id, $requestData);
            return ApiResponse::success($room->toArray(), 'Room created', 201);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }
}
