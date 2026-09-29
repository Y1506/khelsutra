<?php

namespace App\Services\Operations;

use App\Models\VenueBooking;

class WaitlistService
{
    /**
     * Join the waitlist for a specific time slot (creates a pending booking).
     */
    public function joinWaitlist(array $bookingData): VenueBooking
    {
        $bookingData['status'] = 'pending';
        return VenueBooking::create($bookingData);
    }

    /**
     * List the waitlist (pending bookings) for a given venue, facility, and time window.
     */
    public function listWaitlist(int $orgId, int $venueId, ?int $facilityId, string $date, string $startTime, string $endTime)
    {
        $query = VenueBooking::where('organization_id', $orgId)
            ->where('venue_id', $venueId)
            ->where('booking_date', $date)
            ->where('status', 'pending')
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);

        if ($facilityId) {
            $query->where(function($q) use ($facilityId) {
                $q->whereNull('facility_id')
                  ->orWhere('facility_id', $facilityId);
            });
        }

        return $query->orderBy('created_at', 'asc')->get();
    }

    /**
     * Leave the waitlist by cancelling a pending booking.
     */
    public function leaveWaitlist(int $bookingId, int $orgId): bool
    {
        $booking = VenueBooking::where('id', $bookingId)
            ->where('organization_id', $orgId)
            ->where('status', 'pending')
            ->first();

        if ($booking) {
            $booking->status = 'cancelled';
            $booking->cancelled_at = now();
            // $booking->cancelled_by = auth()->id(); // optional
            return $booking->save();
        }

        return false;
    }
}
