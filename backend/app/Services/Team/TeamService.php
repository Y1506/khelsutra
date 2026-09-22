<?php

namespace App\Services\Team;

use App\Repositories\Contracts\TeamRepositoryInterface;
use App\Repositories\Eloquent\TeamRepository;

class TeamService
{
    protected TeamRepositoryInterface $repository;

    /**
     * Create a new team service instance.
     *
     * @param TeamRepositoryInterface|null $repository Optional repository instance
     */
    public function __construct(?TeamRepositoryInterface $repository = null)
    {
        $this->repository = $repository ?? new TeamRepository();
    }

    /**
     * Get a paginated list of teams for an organization.
     *
     * @param int $organizationId The organization ID
     * @param int $page The page number (default 1)
     * @param int $limit The number of records per page (default 15)
     * @return array Array of team records
     */
    public function listTeams(int $organizationId, int $page = 1, int $limit = 15): array
    {
        return $this->repository->getPaginated($organizationId, $page, $limit);
    }

    /**
     * Get a single team by ID.
     *
     * @param int $organizationId The organization ID
     * @param int $id The team ID
     * @return array|null The team record or null if not found
     */
    public function getTeam(int $organizationId, int $id): ?array
    {
        return $this->repository->findById($organizationId, $id);
    }

    /**
     * Create a new team.
     *
     * @param int $organizationId The organization ID
     * @param array $data The team data including name, sport_id, etc.
     * @return array The created team record
     */
    public function createTeam(int $organizationId, array $data): array
    {
        $data['organization_id'] = $organizationId;
        return $this->repository->create($data);
    }
}
