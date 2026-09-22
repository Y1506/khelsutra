<?php

namespace App\Services\Athlete;

use App\Repositories\Contracts\AthleteRepositoryInterface;
use App\Repositories\Eloquent\AthleteRepository;

class AthleteService
{
    protected AthleteRepositoryInterface $repository;

    public function __construct(?AthleteRepositoryInterface $repository = null)
    {
        $this->repository = $repository ?? new AthleteRepository();
    }

    /**
     * Get paginated list of athletes for an organization.
     *
     * @param int $organizationId Organization ID
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array Paginated athletes data
     */
    public function listAthletes(int $organizationId, int $page = 1, int $limit = 15): array
    {
        return $this->repository->getPaginated($organizationId, $page, $limit);
    }

    /**
     * Get a single athlete by ID.
     *
     * @param int $organizationId Organization ID
     * @param int $id Athlete ID
     * @return array|null Athlete data or null if not found
     */
    public function getAthlete(int $organizationId, int $id): ?array
    {
        return $this->repository->findById($organizationId, $id);
    }

    /**
     * Register a new athlete with auto-generated athlete code.
     *
     * @param int $organizationId Organization ID
     * @param array $data Athlete registration data
     * @return array Created athlete data
     */
    public function registerAthlete(int $organizationId, array $data): array
    {
        $data['organization_id'] = $organizationId;
        $data['athlete_code'] = 'ATH-' . strtoupper(substr(uniqid(), -6));
        return $this->repository->create($data);
    }

    /**
     * Update an existing athlete.
     *
     * @param int $organizationId Organization ID
     * @param int $id Athlete ID
     * @param array $data Updated athlete data
     * @return bool Whether update was successful
     */
    public function updateAthlete(int $organizationId, int $id, array $data): bool
    {
        return $this->repository->update($organizationId, $id, $data);
    }

    /**
     * Delete an athlete.
     *
     * @param int $organizationId Organization ID
     * @param int $id Athlete ID
     * @return bool Whether deletion was successful
     */
    public function deleteAthlete(int $organizationId, int $id): bool
    {
        return $this->repository->delete($organizationId, $id);
    }
}
