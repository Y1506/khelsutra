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

    /**
     * Create a new FacilityController instance.
     */
    public function __construct()
    {
        $this->facilityService = new FacilityService();
    }

    /**
     * Get all facilities for a venue.
     *
     * @param int $orgId The organization ID
     * @param int $venueId The venue ID
     * @param array $requestData Request parameters
     * @return array API response with facilities data or error
     */
    public function index(int $orgId, int $venueId, array $requestData): array
    {
        $venue = Venue::where('organization_id', $orgId)->find($venueId);
        if (!$venue) return ApiResponse::error('Venue not found', null, 404);

        $facilities = Facility::where('organization_id', $orgId)->where('venue_id', $venueId)->get();
        return ApiResponse::success(['data' => $facilities->toArray()]);
    }

    /**
     * Create a new facility in a venue.
     *
     * @param int $orgId The organization ID
     * @param int $venueId The venue ID
     * @param array $requestData Facility data
     * @return array API response with created facility or error
     */
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

    /**
     * Get a specific facility.
     *
     * @param int $orgId The organization ID
     * @param int $venueId The venue ID
     * @param int $id The facility ID
     * @return array API response with facility data or error
     */
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

    /**
     * Update a facility.
     *
     * @param int $orgId The organization ID
     * @param int $venueId The venue ID
     * @param int $id The facility ID
     * @param array $requestData Updated facility data
     * @return array API response with updated facility or error
     */
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

    /**
     * Delete a facility.
     *
     * @param int $orgId The organization ID
     * @param int $venueId The venue ID
     * @param int $id The facility ID
     * @return array API response confirming deletion or error
     */
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
