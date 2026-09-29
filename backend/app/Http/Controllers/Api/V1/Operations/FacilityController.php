<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Helpers\ApiResponse;
use App\Services\Operations\FacilityService;
use App\Http\Requests\Venues\FacilityRequest;
use App\Models\Facility;
use App\Models\Venue;

class FacilityController
{
    protected FacilityService $facilityService;

    public function __construct()
    {
        $this->facilityService = new FacilityService();
    }

    public function index(int $orgId, int $venueId, array $requestData): array
    {
        $venue = Venue::where('organization_id', $orgId)->find($venueId);
        if (!$venue) return ApiResponse::error('Venue not found', null, 404);

        $query = Facility::where('organization_id', $orgId)->where('venue_id', $venueId);
        
        if (!empty($requestData['sport_id'])) {
            $query->forSport((int)$requestData['sport_id']);
        }

        $facilities = $query->get();
        return ApiResponse::success(['data' => $facilities->toArray()]);
    }

    public function store(int $orgId, int $venueId, array $requestData): array
    {
        $request = new FacilityRequest($requestData);
        $errors = $request->validate();
        
        if (!empty($errors)) {
            return ApiResponse::error('Validation failed', $errors, 422);
        }

        try {
            $facility = $this->facilityService->create($orgId, $venueId, $request->data);
            return ApiResponse::success($facility->toArray(), 'Facility created successfully', 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Venue not found', null, 404);
        }
    }

    public function show(int $orgId, int $venueId, int $id): array
    {
        $facility = Facility::where('organization_id', $orgId)
            ->where('venue_id', $venueId)
            ->find($id);
            
        if (!$facility) {
            return ApiResponse::error('Facility not found', null, 404);
        }
        return ApiResponse::success($facility->toArray());
    }

    public function update(int $orgId, int $venueId, int $id, array $requestData): array
    {
        $request = new FacilityRequest($requestData);
        $errors = $request->validate();
        
        if (!empty($errors)) {
            return ApiResponse::error('Validation failed', $errors, 422);
        }

        try {
            $facility = $this->facilityService->update($orgId, $venueId, $id, $request->data);
            return ApiResponse::success($facility->toArray(), 'Facility updated successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Facility not found', null, 404);
        }
    }

    public function destroy(int $orgId, int $venueId, int $id): array
    {
        try {
            $this->facilityService->delete($orgId, $venueId, $id);
            return ApiResponse::success(null, 'Facility deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Facility not found', null, 404);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), null, $e->getCode() === 409 ? 409 : 400);
        }
    }
}
