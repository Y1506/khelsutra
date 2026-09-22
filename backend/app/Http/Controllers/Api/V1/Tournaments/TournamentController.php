<?php

namespace App\Http\Controllers\Api\V1\Tournaments;

use App\Http\Controllers\Controller;
use App\Services\Tournament\TournamentService;
use App\Http\Requests\Tournaments\StoreTournamentRequest;
use App\Helpers\ApiResponse;

/**
 * Manages tournament operations including listing, viewing, and creation.
 */
class TournamentController extends Controller
{
    protected TournamentService $tournamentService;

    /**
     * Create a new TournamentController instance.
     *
     * @param TournamentService|null $tournamentService Tournament service instance
     */
    public function __construct(?TournamentService $tournamentService = null)
    {
        $this->tournamentService = $tournamentService ?? new TournamentService();
    }

    /**
     * List all tournaments for an organization with pagination.
     *
     * @param int $organizationId Organization ID
     * @param int $page Page number
     * @param int $limit Number of tournaments per page
     * @return array API response with paginated list of tournaments
     */
    public function index(int $organizationId, int $page = 1, int $limit = 15): array
    {
        $tournaments = $this->tournamentService->listTournaments($organizationId, $page, $limit);
        return ApiResponse::success($tournaments, 'Tournaments retrieved successfully', 200);
    }

    /**
     * Retrieve details of a specific tournament.
     *
     * @param int $organizationId Organization ID
     * @param int $id Tournament ID
     * @return array API response with tournament details or error
     */
    public function show(int $organizationId, int $id): array
    {
        $tournament = $this->tournamentService->getTournament($organizationId, $id);
        if (!$tournament) {
            return ApiResponse::error('Tournament not found', null, 404);
        }
        return ApiResponse::success($tournament, 'Tournament retrieved successfully', 200);
    }

    /**
     * Create a new tournament after validating request data.
     *
     * @param int $organizationId Organization ID
     * @param array $requestData Tournament data to be validated and stored
     * @return array API response with created tournament or validation errors
     */
    public function store(int $organizationId, array $requestData): array
    {
        $request = new StoreTournamentRequest($requestData);
        $errors = $request->validate();
        if (!empty($errors)) {
            return ApiResponse::error('Validation failed', $errors, 422);
        }

        $created = $this->tournamentService->createTournament($organizationId, $requestData);
        return ApiResponse::success($created, 'Tournament created successfully', 201);
    }
}
