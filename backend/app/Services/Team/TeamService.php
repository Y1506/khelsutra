<?php

namespace App\Services\Team;

use App\Repositories\Contracts\TeamRepositoryInterface;
use App\Repositories\Eloquent\TeamRepository;

/**
 * Provides team management operations via repository pattern.
 */
class TeamService
{
    protected TeamRepositoryInterface $repository;

    /**
     * Create a new TeamService instance.
     *
     * @param TeamRepositoryInterface|null $repository Team repository instance
     */
    public function __construct(?TeamRepositoryInterface $repository = null)
    {
        $this->repository = $repository ?? new TeamRepository();
    }

    /**
     * Retrieve a paginated list of teams for an organization.
     *
     * @param int $organizationId Organization ID
     * @param int $page Page number
     * @param int $limit Number of teams per page
     * @return array Paginated team data
     */
    public function listTeams(int $organizationId, int $page = 1, int $limit = 15): array
    {
        return $this->repository->getPaginated($organizationId, $page, $limit);
    }

    /**
     * Retrieve a specific team by ID.
     *
     * @param int $organizationId Organization ID
     * @param int $id Team ID
     * @return array|null Team data or null if not found
     */
    public function getTeam(int $organizationId, int $id): ?array
    {
        return $this->repository->findById($organizationId, $id);
    }

    /**
     * Create a new team for an organization.
     *
     * @param int $organizationId Organization ID
     * @param array $data Team data
     * @return array Created team data
     */
    public function createTeam(int $organizationId, array $data): array
    {
        $data['organization_id'] = $organizationId;
        return $this->repository->create($data);
    }
}
