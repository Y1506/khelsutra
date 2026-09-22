<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Helpers\ApiResponse;
use App\Services\Operations\VehicleService;
use App\Models\Vehicle;
use Exception;

class VehicleController
{
    protected VehicleService $service;

    /**
     * Create a new VehicleController instance.
     *
     * @param VehicleService $service The vehicle service
     */
    public function __construct(VehicleService $service)
    {
        $this->service = $service;
    }

    /**
     * Get all vehicles for an organization.
     *
     * @param int $orgId The organization ID
     * @param array $requestData Request parameters
     * @return array API response with vehicles data
     */
    public function index(int $orgId, array $requestData): array
    {
        $vehicles = Vehicle::where('organization_id', $orgId)->get();
        return ApiResponse::success(['data' => $vehicles->toArray()]);
    }

    /**
     * Create a new vehicle.
     *
     * @param int $orgId The organization ID
     * @param array $requestData Vehicle data
     * @return array API response with created vehicle or error
     */
    public function store(int $orgId, array $requestData): array
    {
        try {
            $vehicle = $this->service->createVehicle($orgId, $requestData);
            return ApiResponse::success($vehicle->toArray(), 'Vehicle created', 201);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }
}
