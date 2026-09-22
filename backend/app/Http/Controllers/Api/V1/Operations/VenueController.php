<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Helpers\ApiResponse;
use App\Services\Operations\VenueService;
use App\Http\Requests\Venues\VenueRequest;
use App\Models\Venue;

/**
 * Manages venue CRUD operations with validation and pagination.
 */
class VenueController
{
    protected VenueService $venueService;

    /**
     * Create a new VenueController instance.
     */
    public function __construct()
    {
        $this->venueService = new VenueService();
    }

    /**
     * List all venues for an organization with pagination.
     *
     * @param int $orgId Organization ID
     * @param array $requestData Request parameters including optional page and limit
     * @return array API response with paginated list of venues
     */
    public function index(int $orgId, array $requestData): array
    {
        $limit = (int)($requestData['limit'] ?? 15);
        $page = (int)($requestData['page'] ?? 1);
        $offset = ($page - 1) * $limit;

        $query = Venue::where('organization_id', $orgId);
        $total = $query->count();
        $venues = $query->offset($offset)->limit($limit)->get();

        return ApiResponse::success([
            'data' => $venues->toArray(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total
            ]
        ]);
    }

    /**
     * Create a new venue after validating request data.
     *
     * @param int $orgId Organization ID
     * @param array $requestData Venue data to be validated
     * @return array API response with created venue or validation errors
     */
    public function store(int $orgId, array $requestData): array
    {
        $request = new VenueRequest($requestData);
        $errors = $request->validate();
        
        if (!empty($errors)) {
            return ApiResponse::error('Validation failed', $errors, 422);
        }

        $venue = $this->venueService->create($orgId, $request->data);
        return ApiResponse::success($venue->toArray(), 'Venue created successfully', 201);
    }

    /**
     * Retrieve details of a specific venue.
     *
     * @param int $orgId Organization ID
     * @param int $id Venue ID
     * @return array API response with venue details or error
     */
    public function show(int $orgId, int $id): array
    {
        $venue = Venue::where('organization_id', $orgId)->find($id);
        if (!$venue) {
            return ApiResponse::error('Venue not found', null, 404);
        }
        return ApiResponse::success($venue->toArray());
    }

    /**
     * Update an existing venue after validating request data.
     *
     * @param int $orgId Organization ID
     * @param int $id Venue ID
     * @param array $requestData Updated venue data
     * @return array API response with updated venue or error
     */
    public function update(int $orgId, int $id, array $requestData): array
    {
        $request = new VenueRequest($requestData);
        $errors = $request->validate();
        
        if (!empty($errors)) {
            return ApiResponse::error('Validation failed', $errors, 422);
        }

        try {
            $venue = $this->venueService->update($orgId, $id, $request->data);
            return ApiResponse::success($venue->toArray(), 'Venue updated successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Venue not found', null, 404);
        }
    }

    /**
     * Delete a venue.
     *
     * @param int $orgId Organization ID
     * @param int $id Venue ID
     * @return array API response confirming deletion or error
     */
    public function destroy(int $orgId, int $id): array
    {
        try {
            $this->venueService->delete($orgId, $id);
            return ApiResponse::success(null, 'Venue deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Venue not found', null, 404);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), null, $e->getCode() === 409 ? 409 : 400);
        }
    }
}
