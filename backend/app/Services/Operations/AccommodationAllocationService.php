<?php

namespace App\Services\Operations;

use App\Models\AccommodationRoom;
use App\Models\AccommodationAllocation;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class AccommodationAllocationService
{
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

    public function autoAllocate(int $orgId, string $source, int $accommodationId, array $options = []): array
    {
        return DB::transaction(function () use ($orgId, $source, $accommodationId, $options) {
            // $options should contain 'participants' => [['id' => 1, 'type' => 'athlete', 'gender' => 'M', 'team_id' => 1, 'role' => 'player']]
            $participants = $options['participants'] ?? [];
            if (empty($participants)) {
                return [];
            }

            $checkIn = $options['check_in_date'] ?? date('Y-m-d H:i:s');
            $checkOut = $options['check_out_date'] ?? null;
            $eventId = $options['event_id'] ?? null;
            $trainingCampId = $options['training_camp_id'] ?? null;
            $tournamentId = $options['tournament_id'] ?? null;

            // Group participants by gender and role/team
            // Keep coaches and athletes separate, genders separate, teams separate
            $groups = [];
            foreach ($participants as $p) {
                $key = ($p['gender'] ?? 'U') . '_' . ($p['team_id'] ?? 'none') . '_' . ($p['role'] ?? 'athlete');
                $groups[$key][] = $p;
            }

            $rooms = AccommodationRoom::where('organization_id', $orgId)
                ->where('accommodation_id', $accommodationId)
                ->where('status', 'available')
                ->lockForUpdate()
                ->get();

            $allocations = [];
            
            foreach ($groups as $groupKey => $members) {
                // Find rooms for this group
                foreach ($members as $member) {
                    $allocated = false;
                    
                    // Try to find a room with space
                    foreach ($rooms as $room) {
                        // Check current active allocations in this room
                        $overlappingCount = AccommodationAllocation::where('organization_id', $orgId)
                            ->where('room_id', $room->id)
                            ->whereIn('status', ['reserved', 'checked_in'])
                            ->where('check_in_date', '<', $checkOut ?? '2099-12-31 23:59:59')
                            ->where(function($q) use ($checkIn) {
                                $q->whereNull('check_out_date')
                                  ->orWhere('check_out_date', '>', $checkIn);
                            })->count();
                        
                        // We also need to add our current session allocations to the count
                        $currentSessionCount = collect($allocations)->where('room_id', $room->id)->count();

                        if (($overlappingCount + $currentSessionCount) < $room->capacity) {
                            // Can we put this person in this room?
                            // Need to ensure room isn't mixed gender or mixed team (unless allowed)
                            // A simple approach: track group assigned to room in this transaction
                            if (!isset($room->assigned_group) || $room->assigned_group === $groupKey) {
                                $room->assigned_group = $groupKey;
                                
                                $data = [
                                    'organization_id' => $orgId,
                                    'room_id' => $room->id,
                                    'accommodation_id' => $accommodationId,
                                    'check_in_date' => $checkIn,
                                    'check_out_date' => $checkOut,
                                    'status' => 'reserved',
                                    'event_id' => $eventId,
                                    'training_camp_id' => $trainingCampId,
                                    'tournament_id' => $tournamentId,
                                ];
                                
                                $type = $member['type'] ?? 'athlete';
                                if ($type === 'athlete') $data['athlete_id'] = $member['id'];
                                elseif ($type === 'employee') $data['employee_id'] = $member['id'];
                                elseif ($type === 'coach') $data['coach_id'] = $member['id'];

                                $alloc = AccommodationAllocation::create($data);
                                $allocations[] = $alloc;
                                $allocated = true;
                                break;
                            }
                        }
                    }

                    if (!$allocated) {
                        throw new Exception("Not enough suitable rooms available for group {$groupKey}.", 409);
                    }
                }
            }

            return $allocations;
        });
    }

    public function updateStatus(int $orgId, int $allocationId, string $status): AccommodationAllocation
    {
        return DB::transaction(function () use ($orgId, $allocationId, $status) {
            $allocation = AccommodationAllocation::where('organization_id', $orgId)
                ->lockForUpdate()
                ->findOrFail($allocationId);

            $allocation->status = $status;
            
            if ($status === 'checked_out') {
                $allocation->check_out_date = date('Y-m-d H:i:s');
            }

            $allocation->save();

            if (in_array($status, ['checked_out', 'cancelled'])) {
                event(new \App\Events\Operations\AllocationEnded($allocation));
            }

            return $allocation;
        });
    }
}

