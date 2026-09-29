<?php

namespace App\Listeners\Operations;

use App\Events\Operations\BookingCompleted;
use App\Models\HousekeepingTask;
use App\Services\Operations\ReferenceGenerator;

class ScheduleHousekeepingTask
{
    public function handle(BookingCompleted $event)
    {
        $booking = $event->booking;
        
        HousekeepingTask::create([
            'organization_id' => $booking->organization_id,
            'task_reference' => ReferenceGenerator::generate('HK', 'housekeeping_tasks', 'task_reference', $booking->organization_id),
            'task_type' => 'cleaning',
            'venue_id' => $booking->venue_id,
            'facility_id' => $booking->facility_id,
            'scheduled_date' => date('Y-m-d', strtotime('+1 day', strtotime($booking->booking_date))),
            'status' => 'pending',
            'priority' => 'medium'
        ]);
    }
}
