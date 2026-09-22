<?php

namespace App\Http\Controllers\Api\V1\Athletes;

use App\Http\Controllers\Controller;
use App\Services\Athlete\AthleteService;
use App\Http\Requests\Athletes\StoreAthleteRequest;
use App\Helpers\ApiResponse;

class AthleteController extends Controller
{
    protected AthleteService $athleteService;

    public function __construct(?AthleteService $athleteService = null)
    {
        $this->athleteService = $athleteService ?? new AthleteService();
    }

    /**
     * List athletes with pagination.
     *
     * @param int $organizationId Organization ID
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array API response with athletes list
     */
    public function index(int $organizationId, int $page = 1, int $limit = 15): array
    {
        $athletes = $this->athleteService->listAthletes($organizationId, $page, $limit);
        return ApiResponse::success($athletes, 'Athletes retrieved successfully', 200);
    }

    /**
     * Get a single athlete by ID.
     *
     * @param int $organizationId Organization ID
     * @param int $id Athlete ID
     * @return array API response with athlete data or error
     */
    public function show(int $organizationId, int $id): array
    {
        $athlete = $this->athleteService->getAthlete($organizationId, $id);
        if (!$athlete) {
            return ApiResponse::error('Athlete not found', null, 404);
        }
        return ApiResponse::success($athlete, 'Athlete retrieved successfully', 200);
    }

    /**
     * Register a new athlete.
     *
     * @param int $organizationId Organization ID
     * @param array $requestData Athlete registration data
     * @return array API response with created athlete or validation errors
     */
    public function store(int $organizationId, array $requestData): array
    {
        $request = new StoreAthleteRequest($requestData);
        $errors = $request->validate();
        if (!empty($errors)) {
            return ApiResponse::error('Validation failed', $errors, 422);
        }

        $created = $this->athleteService->registerAthlete($organizationId, $requestData);
        return ApiResponse::success($created, 'Athlete registered successfully', 201);
    }

    /**
     * Update an existing athlete.
     *
     * @param int $organizationId Organization ID
     * @param int $id Athlete ID
     * @param array $requestData Updated athlete data
     * @return array API response with success or error
     */
    public function update(int $organizationId, int $id, array $requestData): array
    {
        $updated = $this->athleteService->updateAthlete($organizationId, $id, $requestData);
        if (!$updated) {
            return ApiResponse::error('Failed to update athlete or record not found', null, 400);
        }
        return ApiResponse::success(null, 'Athlete updated successfully', 200);
    }

    /**
     * Delete an athlete.
     *
     * @param int $organizationId Organization ID
     * @param int $id Athlete ID
     * @return array API response with success or error
     */
    public function destroy(int $organizationId, int $id): array
    {
        $deleted = $this->athleteService->deleteAthlete($organizationId, $id);
        if (!$deleted) {
            return ApiResponse::error('Failed to delete athlete or record not found', null, 400);
        }
        return ApiResponse::success(null, 'Athlete deleted successfully', 200);
    }
}
