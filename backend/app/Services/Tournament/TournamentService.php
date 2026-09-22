<?php

namespace App\Services\Tournament;

use App\Repositories\Contracts\TournamentRepositoryInterface;
use App\Repositories\Eloquent\TournamentRepository;

class TournamentService
{
    protected TournamentRepositoryInterface $repository;

    /**
     * Create a new tournament service instance.
     *
     * @param TournamentRepositoryInterface|null $repository Optional repository instance
     */
    public function __construct(?TournamentRepositoryInterface $repository = null)
    {
        $this->repository = $repository ?? new TournamentRepository();
    }

    /**
     * Get a paginated list of tournaments for an organization.
     *
     * @param int $organizationId The organization ID
     * @param int $page The page number (default 1)
     * @param int $limit The number of records per page (default 15)
     * @return array Array of tournament records
     */
    public function listTournaments(int $organizationId, int $page = 1, int $limit = 15): array
    {
        return $this->repository->getPaginated($organizationId, $page, $limit);
    }

    /**
     * Get a single tournament by ID.
     *
     * @param int $organizationId The organization ID
     * @param int $id The tournament ID
     * @return array|null The tournament record or null if not found
     */
    public function getTournament(int $organizationId, int $id): ?array
    {
        return $this->repository->findById($organizationId, $id);
    }

    /**
     * Create a new tournament.
     *
     * @param int $organizationId The organization ID
     * @param array $data The tournament data including name, sport_id, start_date, etc.
     * @return array The created tournament record
     */
    public function createTournament(int $organizationId, array $data): array
    {
        $data['organization_id'] = $organizationId;
        return $this->repository->create($data);
    }
}
