<?php

namespace App\Services\Athlete;

use App\Repositories\Contracts\AthleteRepositoryInterface;
use App\Repositories\Eloquent\AthleteRepository;

class AthleteService
{
    protected AthleteRepositoryInterface $repository;

    /**
     * Create a new athlete service instance.
     *
     * @param AthleteRepositoryInterface|null $repository Optional repository instance
     */
    public function __construct(?AthleteRepositoryInterface $repository = null)
    {
        $this->repository = $repository ?? new AthleteRepository();
    }

    /**
     * Get a paginated list of athletes for an organization.
     *
     * @param int $organizationId The organization ID
     * @param int $page The page number (default 1)
     * @param int $limit The number of records per page (default 15)
     * @return array Array of athlete records
     */
    public function listAthletes(int $organizationId, int $page = 1, int $limit = 15): array
    {
        return $this->repository->getPaginated($organizationId, $page, $limit);
    }

    /**
     * Get a single athlete by ID.
     *
     * @param int $organizationId The organization ID
     * @param int $id The athlete ID
     * @return array|null The athlete record or null if not found
     */
    public function getAthlete(int $organizationId, int $id): ?array
    {
        return $this->repository->findById($organizationId, $id);
    }

    /**
     * Register a new athlete in the system.
     *
     * @param int $organizationId The organization ID
     * @param array $data The athlete data including first_name, date_of_birth, gender, etc.
     * @return array The created athlete record
     */
    public function registerAthlete(int $organizationId, array $data): array
    {
        $data['organization_id'] = $organizationId;
        $data['athlete_code'] = 'ATH-' . strtoupper(substr(uniqid(), -6));
        return $this->repository->create($data);
    }

    /**
     * Update an existing athlete's information.
     *
     * @param int $organizationId The organization ID
     * @param int $id The athlete ID
     * @param array $data The fields to update
     * @return bool True if update succeeded, false otherwise
     */
    public function updateAthlete(int $organizationId, int $id, array $data): bool
    {
        return $this->repository->update($organizationId, $id, $data);
    }

    /**
     * Delete an athlete (soft delete).
     *
     * @param int $organizationId The organization ID
     * @param int $id The athlete ID
     * @return bool True if deletion succeeded, false otherwise
     */
    public function deleteAthlete(int $organizationId, int $id): bool
    {
        return $this->repository->delete($organizationId, $id);
    }
}
