<?php

namespace App\Http\Controllers\Api\V1\Athletes;

use App\Http\Controllers\Controller;
use App\Services\Athlete\AthleteService;
use App\Http\Requests\Athletes\StoreAthleteRequest;
use App\Helpers\ApiResponse;

/**
 * Manages athlete operations including registration, listing, updating, and deletion.
 */
class AthleteController extends Controller
{
    protected AthleteService $athleteService;

    /**
     * Create a new AthleteController instance.
     *
     * @param AthleteService|null $athleteService Athlete service instance
     */
    public function __construct(?AthleteService $athleteService = null)
    {
        $this->athleteService = $athleteService ?? new AthleteService();
    }

    /**
     * List all athletes for an organization with pagination.
     *
     * @param int $organizationId Organization ID
     * @param int $page Page number
     * @param int $limit Number of athletes per page
     * @return array API response with paginated list of athletes
     */
    public function index(int $organizationId, int $page = 1, int $limit = 15): array
    {
        $athletes = $this->athleteService->listAthletes($organizationId, $page, $limit);
        return ApiResponse::success($athletes, 'Athletes retrieved successfully', 200);
    }

    /**
     * Retrieve details of a specific athlete.
     *
     * @param int $organizationId Organization ID
     * @param int $id Athlete ID
     * @return array API response with athlete details or error
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
     * Register a new athlete after validating request data.
     *
     * @param int $organizationId Organization ID
     * @param array $requestData Athlete data to be validated and stored
     * @return array API response with registered athlete or validation errors
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
     * Update an existing athlete's information.
     *
     * @param int $organizationId Organization ID
     * @param int $id Athlete ID
     * @param array $requestData Updated athlete data
     * @return array API response confirming update or error
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
     * Delete an athlete record.
     *
     * @param int $organizationId Organization ID
     * @param int $id Athlete ID
     * @return array API response confirming deletion or error
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
