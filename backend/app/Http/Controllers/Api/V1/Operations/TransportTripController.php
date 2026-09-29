<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Helpers\ApiResponse;
use App\Services\Operations\TransportTripService;
use App\Models\TransportTrip;
use Exception;

class TransportTripController
{
    protected TransportTripService $service;

    public function __construct(TransportTripService $service)
    {
        $this->service = $service;
    }

    public function index(int $orgId, array $requestData): array
    {
        $trips = TransportTrip::where('organization_id', $orgId)->get();
        return ApiResponse::success(['data' => $trips->toArray()]);
    }

    public function store(int $orgId, array $requestData): array
    {
        try {
            $tripOrTrips = $this->service->createTrip($orgId, $requestData);
            if (is_array($tripOrTrips)) {
                $response = array_map(fn($t) => $t->toArray(), $tripOrTrips);
                return ApiResponse::success(['data' => $response], 'Trips created (auto-assigned)', 201);
            }
            return ApiResponse::success($tripOrTrips->toArray(), 'Trip created', 201);
        } catch (Exception $e) {
            $status = $e->getCode() == 409 ? 409 : 400;
            return ApiResponse::error($e->getMessage(), null, $status);
        }
    }

    public function autoPlan(int $orgId, array $requestData): array
    {
        try {
            $assignmentService = new \App\Services\Operations\TransportAssignmentService();
            $plan = $assignmentService->planAssignment($orgId, $requestData);
            if (!$plan['success']) {
                return ApiResponse::error($plan['message'], ['shortfall' => $plan['shortfall']], 409);
            }
            return ApiResponse::success($plan, 'Auto-assignment plan generated');
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }

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
