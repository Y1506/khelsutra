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
     * @param int $orgId Organization ID
     * @param int $venueId Venue ID
     * @param array $data Facility data
     * @return Facility Created facility
     * @throws Exception If venue not found
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
     * @param int $orgId Organization ID
     * @param int $venueId Venue ID
     * @param int $facilityId Facility ID to update
     * @param array $data Updated facility data
     * @return Facility Updated facility
     * @throws Exception If facility not found
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
     * Delete a facility (soft delete if no active bookings).
     *
     * @param int $orgId Organization ID
     * @param int $venueId Venue ID
     * @param int $facilityId Facility ID to delete
     * @return void
     * @throws Exception If facility has future active bookings or not found
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
