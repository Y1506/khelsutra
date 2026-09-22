<?php

namespace App\Services\Operations;

use App\Models\Venue;
use App\Models\Facility;
use App\Models\VenueBooking;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class FacilityService
{
    /**
     * Create a new facility in a venue.
     *
     * @param int $orgId The organization ID
     * @param int $venueId The venue ID
     * @param array $data Facility data
     * @return Facility The created facility
     */
    public function create(int $orgId, int $venueId, array $data): Facility
    {
        return DB::transaction(function () use ($orgId, $venueId, $data) {
            $venue = Venue::where('organization_id', $orgId)->findOrFail($venueId);
            
            $data['organization_id'] = $orgId;
            $data['venue_id'] = $venue->id;
            if (empty($data['status'])) {
                $data['status'] = 'active';
            }

            return Facility::create($data);
        });
    }

    /**
     * Update a facility.
     *
     * @param int $orgId The organization ID
     * @param int $venueId The venue ID
     * @param int $facilityId The facility ID
     * @param array $data Updated facility data
     * @return Facility The updated facility
     */
    public function update(int $orgId, int $venueId, int $facilityId, array $data): Facility
    {
        return DB::transaction(function () use ($orgId, $venueId, $facilityId, $data) {
            $facility = Facility::where('organization_id', $orgId)
                ->where('venue_id', $venueId)
                ->findOrFail($facilityId);
                
            $facility->update($data);
            return $facility;
        });
    }

    /**
     * Delete a facility if it has no active bookings.
     *
     * @param int $orgId The organization ID
     * @param int $venueId The venue ID
     * @param int $facilityId The facility ID
     * @return void
     */
    public function delete(int $orgId, int $venueId, int $facilityId): void
    {
        DB::transaction(function () use ($orgId, $venueId, $facilityId) {
            $facility = Facility::where('organization_id', $orgId)
                ->where('venue_id', $venueId)
                ->findOrFail($facilityId);

            // Check for active bookings for this specific facility
            $hasActiveBookings = VenueBooking::where('organization_id', $orgId)
                ->where('facility_id', $facilityId)
                ->whereIn('status', ['pending', 'approved'])
                ->where('booking_date', '>=', date('Y-m-d'))
                ->exists();

            if ($hasActiveBookings) {
                throw new Exception("Cannot delete facility with future active bookings.", 409);
            }

            $facility->delete();
        });
    }
}
