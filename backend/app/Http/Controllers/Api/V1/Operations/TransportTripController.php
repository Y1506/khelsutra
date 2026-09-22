<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Helpers\ApiResponse;
use App\Services\Operations\TransportTripService;
use App\Models\TransportTrip;
use Exception;

class TransportTripController
{
    protected TransportTripService $service;

    /**
     * Create a new TransportTripController instance.
     *
     * @param TransportTripService $service The transport trip service
     */
    public function __construct(TransportTripService $service)
    {
        $this->service = $service;
    }

    /**
     * Get all transport trips for an organization.
     *
     * @param int $orgId The organization ID
     * @param array $requestData Request parameters
     * @return array API response with trips data
     */
    public function index(int $orgId, array $requestData): array
    {
        $trips = TransportTrip::where('organization_id', $orgId)->get();
        return ApiResponse::success(['data' => $trips->toArray()]);
    }

    /**
     * Create a new transport trip.
     *
     * @param int $orgId The organization ID
     * @param array $requestData Trip data
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
     * Add a passenger to a transport trip.
     *
     * @param int $orgId The organization ID
     * @param int $id The trip ID
     * @param array $requestData Passenger data
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
     * Update a transport trip.
     *
     * @param int $orgId The organization ID
     * @param int $id The trip ID
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
