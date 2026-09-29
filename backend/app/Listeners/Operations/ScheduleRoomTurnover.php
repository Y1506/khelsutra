<?php

namespace App\Listeners\Operations;

use App\Events\Operations\AllocationEnded;
use App\Models\HousekeepingTask;
use App\Services\Operations\ReferenceGenerator;
use App\Models\AccommodationRoom;

class ScheduleRoomTurnover
{
    public function handle(AllocationEnded $event)
    {
        $allocation = $event->allocation;
        $room = AccommodationRoom::find($allocation->room_id);
        
        if ($room) {
            HousekeepingTask::create([
                'organization_id' => $allocation->organization_id,
                'task_reference' => ReferenceGenerator::generate('HK', 'housekeeping_tasks', 'task_reference', $allocation->organization_id),
                'task_type' => 'cleaning',
                'venue_id' => null, // Or link accommodation to a venue? Schema says housekeeping tasks relate to venues usually. If room cleaning, maybe store room_id or accommodation_id if available. 
                // Let's add description
                'description' => 'Room turnover cleaning for room ' . $room->room_number,
                'scheduled_date' => $allocation->check_out_date ? date('Y-m-d', strtotime($allocation->check_out_date)) : date('Y-m-d'),
                'status' => 'pending',
                'priority' => 'high'
            ]);
        }
    }
}
