<?php
namespace App\Services\Operations;

use App\Models\VenueBooking;
use App\Models\TransportTrip;
use App\Models\AccommodationAllocation;
use Illuminate\Support\Facades\DB;

class AvailabilityService
{
    public function isVenueFree($venueId, $facilityId, $start, $end)
    {
        // Ignore soft-deleted rows, check overlap
        $q = DB::table('venue_bookings')
            ->where('venue_id', $venueId)
            ->whereNull('deleted_at')
            ->whereIn('status', ['pending', 'approved'])
            ->where(function($q) use($start, $end) {
                $q->where('booking_date', date('Y-m-d', strtotime($start))) // simplify: assume same day or use start_time/end_time
                  ->where('start_time', '<', date('H:i:s', strtotime($end)))
                  ->where('end_time', '>', date('H:i:s', strtotime($start)));
            });
            
        if ($facilityId) {
            $q->where(function($q2) use ($facilityId) {
                $q2->whereNull('facility_id')->orWhere('facility_id', $facilityId);
            });
        }
        
        // Check maintenance
        $m = DB::table('venue_maintenance')
            ->where('venue_id', $venueId)
            ->whereIn('status', ['reported', 'assigned', 'in_progress'])
            ->where(function($q) use($start, $end) {
                $q->where('scheduled_date', date('Y-m-d', strtotime($start))); // simplification
            });
        
        return !$q->exists() && !$m->exists();
    }

    public function isVehicleFree($vehicleId, $start, $end)
    {
        $buffer = config('logistics.vehicle_buffer_minutes', 60);
        $s = date('H:i:s', strtotime("-{$buffer} minutes", strtotime($start)));
        $e = date('H:i:s', strtotime("+{$buffer} minutes", strtotime($end)));
        
        return !DB::table('transport_trips')
            ->where('vehicle_id', $vehicleId)
            ->whereNull('deleted_at')
            ->whereIn('status', ['planned', 'in_progress'])
            ->where('trip_date', date('Y-m-d', strtotime($start)))
            ->where('departure_time', '<', $e)
            ->where('return_time', '>', $s)
            ->exists();
    }

    public function isRoomFree($roomId, $start, $end)
    {
        return !DB::table('accommodation_allocations')
            ->where('room_id', $roomId)
            ->whereNull('deleted_at')
            ->whereIn('status', ['reserved', 'checked_in'])
            ->where('check_in_date', '<', date('Y-m-d', strtotime($end)))
            ->where(function($q) use ($start) {
                $q->whereNull('check_out_date')->orWhere('check_out_date', '>', date('Y-m-d', strtotime($start)));
            })
            ->exists();
    }
}
