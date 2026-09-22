<?php

namespace App\Http\Controllers\Api\V1\Teams;

use App\Http\Controllers\Controller;
use App\Services\Team\TeamService;
use App\Http\Requests\Teams\StoreTeamRequest;
use App\Helpers\ApiResponse;

/**
 * Manages team operations including listing, viewing, and creation.
 */
class TeamController extends Controller
{
    protected TeamService $teamService;

    /**
     * Create a new TeamController instance.
     *
     * @param TeamService|null $teamService Team service instance
     */
    public function __construct(?TeamService $teamService = null)
    {
        $this->teamService = $teamService ?? new TeamService();
    }

    /**
     * List all teams for an organization with pagination.
     *
     * @param int $organizationId Organization ID
     * @param int $page Page number
     * @param int $limit Number of teams per page
     * @return array API response with paginated list of teams
     */
    public function index(int $organizationId, int $page = 1, int $limit = 15): array
    {
        $teams = $this->teamService->listTeams($organizationId, $page, $limit);
        return ApiResponse::success($teams, 'Teams retrieved successfully', 200);
    }

    /**
     * Retrieve details of a specific team.
     *
     * @param int $organizationId Organization ID
     * @param int $id Team ID
     * @return array API response with team details or error
     */
    public function show(int $organizationId, int $id): array
    {
        $team = $this->teamService->getTeam($organizationId, $id);
        if (!$team) {
            return ApiResponse::error('Team not found', null, 404);
        }
        return ApiResponse::success($team, 'Team retrieved successfully', 200);
    }

    /**
     * Create a new team after validating request data.
     *
     * @param int $organizationId Organization ID
     * @param array $requestData Team data to be validated and stored
     * @return array API response with created team or validation errors
     */
    public function store(int $organizationId, array $requestData): array
    {
        $request = new StoreTeamRequest($requestData);
        $errors = $request->validate();
        if (!empty($errors)) {
            return ApiResponse::error('Validation failed', $errors, 422);
        }

        $created = $this->teamService->createTeam($organizationId, $requestData);
        return ApiResponse::success($created, 'Team created successfully', 201);
    }
}
