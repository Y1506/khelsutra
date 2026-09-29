<?php

namespace App\Services\Operations;

use App\Models\Venue;
use App\Models\VenueBooking;

class VenueAvailabilityService
{
    /**
     * Check if a requested slot is available.
     * Returns true if available, or throws/returns false.
     *
     * @param int $orgId
     * @param int $venueId
     * @param int|null $facilityId
     * @param string $date (Y-m-d)
     * @param string $startTime (H:i:s)
     * @param string $endTime (H:i:s)
     * @param int|null $excludeBookingId
     * @return array ['available' => bool, 'conflict' => string|null]
     */
    public function checkAvailability(
        int $orgId,
        int $venueId,
        ?int $facilityId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeBookingId = null
    ): array {
        if ($endTime <= $startTime) {
            return ['available' => false, 'conflict' => 'End time must be after start time.'];
        }
        
        if ($date < date('Y-m-d')) {
            return ['available' => false, 'conflict' => 'Booking date cannot be in the past.'];
        }

        $venue = Venue::where('organization_id', $orgId)->find($venueId);
        if (!$venue || $venue->status !== 'active') {
            return ['available' => false, 'conflict' => 'Venue is inactive or does not exist.'];
        }

        if ($venue->opening_time && $startTime < $venue->opening_time) {
            return ['available' => false, 'conflict' => "Starts before venue opens at {$venue->opening_time}."];
        }
        if ($venue->closing_time && $endTime > $venue->closing_time) {
            return ['available' => false, 'conflict' => "Ends after venue closes at {$venue->closing_time}."];
        }

        // Check facility
        if ($facilityId) {
            $facility = \App\Models\Facility::where('organization_id', $orgId)
                ->where('venue_id', $venueId)
                ->where('id', $facilityId)
                ->first();
            if (!$facility) {
                return ['available' => false, 'conflict' => 'Facility not found in this venue.'];
            }
            if ($facility->status !== 'active') {
                return ['available' => false, 'conflict' => 'Facility is inactive.'];
            }
        }

                // Check Maintenance block
        $maintenance = \App\Models\VenueMaintenance::where('organization_id', $orgId)
            ->where('venue_id', $venueId)
            ->where(function($q) use ($facilityId) {
                if ($facilityId) {
                    $q->whereNull('facility_id')->orWhere('facility_id', $facilityId);
                }
            })
            ->whereNotIn('status', ['completed', 'cancelled']) // assume these are the terminal states
            ->where('scheduled_date', $date) // simple assumption for maintenance blocking day
            ->first();

        if ($maintenance) {
            return ['available' => false, 'conflict' => 'Venue/Facility is under maintenance on this date.'];
        }

        // Build overlap query
        $query = VenueBooking::where('organization_id', $orgId)
            ->where('venue_id', $venueId)
            ->where('booking_date', $date)
            ->whereIn('status', ['pending', 'approved', 'completed'])
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        if ($facilityId) {
            // New booking is on a facility:
            // Conflicts with whole-venue (NULL) OR same facility
            $query->where(function($q) use ($facilityId) {
                $q->whereNull('facility_id')
                  ->orWhere('facility_id', $facilityId);
            });
        } else {
            // New booking is whole-venue:
            // Conflicts with any booking in this venue (no extra filter needed)
        }

        $conflict = $query->first();

        if ($conflict) {
            return [
                'available' => false, 
                'conflict' => "Conflicts with existing booking: {$conflict->booking_reference} ({$conflict->start_time} - {$conflict->end_time})"
            ];
        }

        return ['available' => true, 'conflict' => null];
    }
}
