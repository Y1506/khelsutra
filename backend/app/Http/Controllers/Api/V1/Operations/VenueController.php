<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Helpers\ApiResponse;
use App\Services\Operations\VenueService;
use App\Http\Requests\Venues\VenueRequest;
use App\Models\Venue;

class VenueController
{
    protected VenueService $venueService;

    /**
     * VenueController constructor.
     */
    public function __construct()
    {
        $this->venueService = new VenueService();
    }

    /**
     * List all venues with pagination.
     *
     * @param int $orgId Organization ID
     * @param array $requestData Request data with optional pagination params
     * @return array API response with venues list
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
     * Create a new venue.
     *
     * @param int $orgId Organization ID
     * @param array $requestData Venue data
     * @return array API response with created venue
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
     * Get a specific venue by ID.
     *
     * @param int $orgId Organization ID
     * @param int $id Venue ID
     * @return array API response with venue data
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
     * Update a venue.
     *
     * @param int $orgId Organization ID
     * @param int $id Venue ID
     * @param array $requestData Updated venue data
     * @return array API response with updated venue
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
     * @return array API response confirming deletion
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
