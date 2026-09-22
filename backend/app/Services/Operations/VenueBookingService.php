<?php

namespace App\Services\Operations;

use App\Models\Venue;
use App\Models\VenueBooking;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class VenueBookingService
{
    protected VenueAvailabilityService $availability;

    public function __construct()
    {
        $this->availability = new VenueAvailabilityService();
    }

    /**
     * Create a new venue booking with availability validation.
     *
     * @param int $orgId Organization ID
     * @param array $data Booking data (venue_id, facility_id, booking_date, start_time, end_time, purpose)
     * @param bool $hasManagePerm Whether user has manage permission (auto-approve)
     * @return VenueBooking Created booking
     * @throws Exception If venue unavailable or conflict exists
     */
    public function createBooking(int $orgId, array $data, bool $hasManagePerm): VenueBooking
    {
        return DB::transaction(function () use ($orgId, $data, $hasManagePerm) {
            // Lock venue parent row to prevent gap race conditions
            $venue = Venue::where('organization_id', $orgId)->lockForUpdate()->findOrFail($data['venue_id']);

            $check = $this->availability->checkAvailability(
                $orgId,
                $venue->id,
                $data['facility_id'] ?? null,
                $data['booking_date'],
                $data['start_time'],
                $data['end_time']
            );

            if (!$check['available']) {
                throw new Exception($check['conflict'], 409);
            }

            $data['organization_id'] = $orgId;
            $data['booking_reference'] = ReferenceGenerator::generate('BK', 'venue_bookings', 'booking_reference', $orgId);
            $data['status'] = $hasManagePerm ? 'approved' : 'pending';
            if (!isset($data['purpose'])) {
                $data['purpose'] = 'N/A';
            }

            return VenueBooking::create($data);
        });
    }

    /**
     * Cancel an existing venue booking.
     *
     * @param int $orgId Organization ID
     * @param int $bookingId Booking ID
     * @param int $userId User ID performing the cancellation
     * @param string $reason Cancellation reason
     * @return VenueBooking Updated booking
     * @throws Exception If booking cannot be cancelled
     */
    public function cancelBooking(int $orgId, int $bookingId, int $userId, string $reason = ''): VenueBooking
    {
        return DB::transaction(function () use ($orgId, $bookingId, $userId, $reason) {
            $booking = VenueBooking::where('organization_id', $orgId)->lockForUpdate()->findOrFail($bookingId);
            
            if (in_array($booking->status, ['cancelled', 'completed'])) {
                throw new Exception("Booking cannot be cancelled in its current status.", 400);
            }

            $booking->status = 'cancelled';
            $booking->cancelled_at = date('Y-m-d H:i:s');
            $booking->cancelled_by = $userId;
            $booking->save();

            return $booking;
        });
    }

    /**
     * Reschedule an existing venue booking to a new date/time.
     *
     * @param int $orgId Organization ID
     * @param int $bookingId Booking ID
     * @param array $data Updated booking data (booking_date, start_time, end_time, facility_id)
     * @return VenueBooking Updated booking
     * @throws Exception If rescheduling fails or new slot unavailable
     */
    public function rescheduleBooking(int $orgId, int $bookingId, array $data): VenueBooking
    {
        return DB::transaction(function () use ($orgId, $bookingId, $data) {
            $booking = VenueBooking::where('organization_id', $orgId)->lockForUpdate()->findOrFail($bookingId);
            
            // Lock venue to serialize
            Venue::where('organization_id', $orgId)->lockForUpdate()->findOrFail($booking->venue_id);

            if (in_array($booking->status, ['cancelled', 'completed'])) {
                throw new Exception("Cannot reschedule cancelled or completed bookings.", 400);
            }

            $check = $this->availability->checkAvailability(
                $orgId,
                $booking->venue_id,
                $data['facility_id'] ?? $booking->facility_id,
                $data['booking_date'] ?? $booking->booking_date,
                $data['start_time'] ?? $booking->start_time,
                $data['end_time'] ?? $booking->end_time,
                $booking->id
            );

            if (!$check['available']) {
                throw new Exception($check['conflict'], 409);
            }

            $booking->update($data);
            return $booking;
        });
    }
}
