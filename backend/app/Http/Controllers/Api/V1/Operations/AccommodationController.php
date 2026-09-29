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

    public function __construct(AccommodationService $service)
    {
        $this->service = $service;
    }

    public function index(int $orgId, array $requestData): array
    {
        $acc = Accommodation::where('organization_id', $orgId)->get();
        return ApiResponse::success(['data' => $acc->toArray()]);
    }

    public function store(int $orgId, array $requestData): array
    {
        try {
            $acc = $this->service->createAccommodation($orgId, $requestData);
            return ApiResponse::success($acc->toArray(), 'Accommodation created', 201);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }

    public function storeRoom(int $orgId, int $id, array $requestData): array
    {
        try {
            $room = $this->service->createRoom($orgId, $id, $requestData);
            return ApiResponse::success($room->toArray(), 'Room created', 201);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }

    public function getRooms(int $orgId, int $id): array
    {
        try {
            $result = $this->service->listRooms($orgId, $id);
            return \App\Helpers\ApiResponse::success($result);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 500;
            if ($code < 100 || $code > 599) $code = 500;
            return \App\Helpers\ApiResponse::error($e->getMessage(), null, $code);
        }
    }
}

