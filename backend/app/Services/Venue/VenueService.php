<?php

namespace App\Services\Venue;

use App\Repositories\Contracts\VenueRepositoryInterface;
use App\Repositories\Eloquent\VenueRepository;

class VenueService
{
    protected VenueRepositoryInterface $repository;

    /**
     * Create a new venue service instance.
     *
     * @param VenueRepositoryInterface|null $repository Optional repository instance
     */
    public function __construct(?VenueRepositoryInterface $repository = null)
    {
        $this->repository = $repository ?? new VenueRepository();
    }

    /**
     * Get a paginated list of venues for an organization.
     *
     * @param int $organizationId The organization ID
     * @param int $page The page number (default 1)
     * @param int $limit The number of records per page (default 15)
     * @return array Array of venue records
     */
    public function listVenues(int $organizationId, int $page = 1, int $limit = 15): array
    {
        return $this->repository->getPaginated($organizationId, $page, $limit);
    }

    /**
     * Get a single venue by ID.
     *
     * @param int $organizationId The organization ID
     * @param int $id The venue ID
     * @return array|null The venue record or null if not found
     */
    public function getVenue(int $organizationId, int $id): ?array
    {
        return $this->repository->findById($organizationId, $id);
    }

    /**
     * Create a new venue.
     *
     * @param int $organizationId The organization ID
     * @param array $data The venue data including name, location, capacity, etc.
     * @return array The created venue record
     */
    public function createVenue(int $organizationId, array $data): array
    {
        $data['organization_id'] = $organizationId;
        return $this->repository->create($data);
    }
}
