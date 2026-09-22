<?php

namespace App\Services\Tournament;

use App\Repositories\Contracts\TournamentRepositoryInterface;
use App\Repositories\Eloquent\TournamentRepository;

/**
 * Provides tournament management operations via repository pattern.
 */
class TournamentService
{
    protected TournamentRepositoryInterface $repository;

    /**
     * Create a new TournamentService instance.
     *
     * @param TournamentRepositoryInterface|null $repository Tournament repository instance
     */
    public function __construct(?TournamentRepositoryInterface $repository = null)
    {
        $this->repository = $repository ?? new TournamentRepository();
    }

    /**
     * Retrieve a paginated list of tournaments for an organization.
     *
     * @param int $organizationId Organization ID
     * @param int $page Page number
     * @param int $limit Number of tournaments per page
     * @return array Paginated tournament data
     */
    public function listTournaments(int $organizationId, int $page = 1, int $limit = 15): array
    {
        return $this->repository->getPaginated($organizationId, $page, $limit);
    }

    /**
     * Retrieve a specific tournament by ID.
     *
     * @param int $organizationId Organization ID
     * @param int $id Tournament ID
     * @return array|null Tournament data or null if not found
     */
    public function getTournament(int $organizationId, int $id): ?array
    {
        return $this->repository->findById($organizationId, $id);
    }

    /**
     * Create a new tournament for an organization.
     *
     * @param int $organizationId Organization ID
     * @param array $data Tournament data
     * @return array Created tournament data
     */
    public function createTournament(int $organizationId, array $data): array
    {
        $data['organization_id'] = $organizationId;
        return $this->repository->create($data);
    }
}
