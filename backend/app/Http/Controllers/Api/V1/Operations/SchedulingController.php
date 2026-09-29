<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\VenueBooking;
use Illuminate\Support\Facades\DB;
use App\Services\Operations\VenueAvailabilityService;

class SchedulingController extends Controller
{
    public function getClashes(Request $request): JsonResponse
    {
        $orgId = $request->attributes->get('organization_id');
        if (!$orgId) {
            return response()->json(['error' => 'Organization context required'], 403);
        }

        // We find any double-bookings (e.g. if created outside lock, or conflicting status)
        // A clash is when two bookings for the same venue/facility on the same date overlap in time
        // and both have blocking status

        $clashes = [];

        // Fetch all active bookings in the future or recent past
        $bookings = VenueBooking::where('organization_id', $orgId)
            ->whereIn('status', ['pending', 'approved', 'completed'])
            ->where('booking_date', '>=', date('Y-m-d', strtotime('-30 days')))
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get();

        // Group by venue and date
        $grouped = [];
        foreach ($bookings as $booking) {
            $key = $booking->venue_id . '_' . $booking->booking_date;
            $grouped[$key][] = $booking;
        }

        foreach ($grouped as $key => $dailyBookings) {
            $count = count($dailyBookings);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $b1 = $dailyBookings[$i];
                    $b2 = $dailyBookings[$j];

                    // Check facility conflict
                    // If either is whole-venue (facility_id = null) OR they have the same facility_id
                    $facilityConflict = is_null($b1->facility_id) || is_null($b2->facility_id) || ($b1->facility_id == $b2->facility_id);

                    if ($facilityConflict) {
                        // Check time overlap
                        if ($b1->start_time < $b2->end_time && $b1->end_time > $b2->start_time) {
                            $clashes[] = [
                                'venue_id' => $b1->venue_id,
                                'date' => $b1->booking_date,
                                'booking_1' => [
                                    'id' => $b1->id,
                                    'reference' => $b1->booking_reference,
                                    'facility_id' => $b1->facility_id,
                                    'time' => $b1->start_time . ' - ' . $b1->end_time,
                                ],
                                'booking_2' => [
                                    'id' => $b2->id,
                                    'reference' => $b2->booking_reference,
                                    'facility_id' => $b2->facility_id,
                                    'time' => $b2->start_time . ' - ' . $b2->end_time,
                                ]
                            ];
                        }
                    }
                }
            }
        }

        return response()->json(['data' => $clashes]);
    }
}
