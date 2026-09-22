<?php

namespace App\Services\Operations;

use App\Models\AccommodationRoom;
use App\Models\AccommodationAllocation;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class RoomAllocationService
{
    /**
     * Allocate a room to a person with availability and capacity validation.
     *
     * @param int $orgId Organization ID
     * @param array $data Allocation data (room_id, accommodation_id, check_in_date, check_out_date, athlete_id/employee_id/coach_id)
     * @return AccommodationAllocation Created allocation
     * @throws Exception If room unavailable, capacity exceeded, or person already allocated
     */
    public function allocateRoom(int $orgId, array $data): AccommodationAllocation
    {
        return DB::transaction(function () use ($orgId, $data) {
            $roomId = $data['room_id'];
            $accommodationId = $data['accommodation_id'];
            $checkIn = $data['check_in_date'];
            $checkOut = $data['check_out_date'] ?? null;

            if ($checkOut && $checkOut <= $checkIn) {
                throw new Exception("Check-out date must be after check-in date.", 400);
            }

            // Lock parent room
            $room = AccommodationRoom::where('organization_id', $orgId)
                ->with('accommodation')
                ->lockForUpdate()
                ->findOrFail($roomId);

            if ($room->accommodation_id != $accommodationId) {
                throw new Exception("Room does not belong to the specified accommodation.", 400);
            }

            if (in_array($room->status, ['maintenance', 'inactive'])) {
                throw new Exception("Room is not available (status: {$room->status}).", 409);
            }

            if ($room->accommodation->status !== 'active') {
                throw new Exception("Accommodation is not active.", 409);
            }

            // Check overlapping allocations for capacity
            $query = AccommodationAllocation::where('organization_id', $orgId)
                ->where('room_id', $roomId)
                ->whereIn('status', ['reserved', 'checked_in'])
                ->where('check_in_date', '<', $checkOut ?? '2099-12-31 23:59:59')
                ->where(function($q) use ($checkIn) {
                    $q->whereNull('check_out_date')
                      ->orWhere('check_out_date', '>', $checkIn);
                });

            $overlappingCount = $query->count();

            if ($overlappingCount >= $room->capacity) {
                throw new Exception("Room capacity exceeded for the given dates.", 409);
            }

            // Check if the exact person is already allocated somewhere else overlapping
            // Just checking same room or same accommodation for same person
            if (isset($data['athlete_id']) || isset($data['employee_id']) || isset($data['coach_id'])) {
                $personQuery = AccommodationAllocation::where('organization_id', $orgId)
                    ->whereIn('status', ['reserved', 'checked_in'])
                    ->where('check_in_date', '<', $checkOut ?? '2099-12-31 23:59:59')
                    ->where(function($q) use ($checkIn) {
                        $q->whereNull('check_out_date')
                          ->orWhere('check_out_date', '>', $checkIn);
                    });
                
                if (isset($data['athlete_id'])) $personQuery->where('athlete_id', $data['athlete_id']);
                if (isset($data['employee_id'])) $personQuery->where('employee_id', $data['employee_id']);
                if (isset($data['coach_id'])) $personQuery->where('coach_id', $data['coach_id']);

                if ($personQuery->exists()) {
                    throw new Exception("Person is already holding an overlapping active allocation.", 409);
                }
            }

            $data['organization_id'] = $orgId;
            $data['status'] = 'reserved';

            return AccommodationAllocation::create($data);
        });
    }
}
