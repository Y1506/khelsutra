<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Helpers\ApiResponse;
use App\Services\Operations\TransportTripService;
use App\Models\TransportTrip;
use Exception;

/**
 * Manages transport trip operations including creation, updates, and passenger management.
 */
class TransportTripController
{
    protected TransportTripService $service;

    /**
     * Create a new TransportTripController instance.
     *
     * @param TransportTripService $service Transport trip service
     */
    public function __construct(TransportTripService $service)
    {
        $this->service = $service;
    }

    /**
     * List all transport trips for an organization.
     *
     * @param int $orgId Organization ID
     * @param array $requestData Request parameters
     * @return array API response with list of transport trips
     */
    public function index(int $orgId, array $requestData): array
    {
        $trips = TransportTrip::where('organization_id', $orgId)->get();
        return ApiResponse::success(['data' => $trips->toArray()]);
    }

    /**
     * Create a new transport trip with vehicle and driver validation.
     *
     * @param int $orgId Organization ID
     * @param array $requestData Trip data including vehicle_id, driver_employee_id, and trip_date
     * @return array API response with created trip or error
     */
    public function store(int $orgId, array $requestData): array
    {
        try {
            $trip = $this->service->createTrip($orgId, $requestData);
            return ApiResponse::success($trip->toArray(), 'Trip created', 201);
        } catch (Exception $e) {
            $status = $e->getCode() == 409 ? 409 : 400;
            return ApiResponse::error($e->getMessage(), null, $status);
        }
    }

    /**
     * Add a passenger to a transport trip with capacity validation.
     *
     * @param int $orgId Organization ID
     * @param int $id Trip ID
     * @param array $requestData Passenger data including athlete_id, employee_id, or coach_id
     * @return array API response with added passenger or error
     */
    public function addPassenger(int $orgId, int $id, array $requestData): array
    {
        try {
            $passenger = $this->service->addPassenger($orgId, $id, $requestData);
            return ApiResponse::success($passenger->toArray(), 'Passenger added', 201);
        } catch (Exception $e) {
            $status = $e->getCode() == 409 ? 409 : 400;
            return ApiResponse::error($e->getMessage(), null, $status);
        }
    }

    /**
     * Update a transport trip and record expenses if completed.
     *
     * @param int $orgId Organization ID
     * @param int $id Trip ID
     * @param array $requestData Updated trip data
     * @return array API response with updated trip or error
     */
    public function update(int $orgId, int $id, array $requestData): array
    {
        try {
            $trip = $this->service->updateTrip($orgId, $id, $requestData);
            return ApiResponse::success($trip->toArray(), 'Trip updated');
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }
}
