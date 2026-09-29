<?php

namespace App\Services\Operations;

use App\Models\TransportTrip;
use Carbon\Carbon;

class DriverDutyService
{
    /**
     * Calculate total duty hours for a driver on a specific date.
     */
    public function getDutyHours(int $orgId, int $driverId, string $date): float
    {
        $trips = TransportTrip::where('organization_id', $orgId)
            ->where('driver_employee_id', $driverId)
            ->where('trip_date', $date)
            ->whereNotIn('status', ['cancelled'])
            ->get();

        $totalMinutes = 0;

        foreach ($trips as $trip) {
            $start = $trip->departure_time ? Carbon::parse($date . ' ' . $trip->departure_time) : Carbon::parse($date . ' 00:00:00');
            $end = $trip->return_time ? Carbon::parse($date . ' ' . $trip->return_time) : Carbon::parse($date . ' 23:59:59');

            if ($end->lt($start)) {
                // handles overnight trip spanning next day conceptually, or just absolute diff
                $end->addDay(); 
            }
            $totalMinutes += $start->diffInMinutes($end);
        }

        return round($totalMinutes / 60, 2);
    }
}
