<?php

namespace App\Services\Operations;

use App\Models\Vehicle;
use App\Models\TransportTrip;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class TransportAssignmentService
{
    public function planAssignment(int $orgId, array $params): array
    {
        $travellers = $params['travellers'] ?? 0;
        $seatBuffer = $params['seat_buffer'] ?? 0;
        $tripDate = $params['trip_date'];
        
                $N = $travellers + $seatBuffer;
        
        // --- PHASE 5: CARGO/EQUIPMENT LOAD LOGIC ---
        $totalCargoWeight = 0;
        $totalCargoVolume = 0;
        if (isset($params['sport_equipment_profile_ids']) && is_array($params['sport_equipment_profile_ids'])) {
            // Assume model SportEquipmentProfile exists
            if (class_exists(\App\Models\SportEquipmentProfile::class)) {
                $profiles = \App\Models\SportEquipmentProfile::whereIn('id', $params['sport_equipment_profile_ids'])->get();
                foreach ($profiles as $profile) {
                    $totalCargoWeight += $profile->weight ?? 0;
                    $totalCargoVolume += $profile->volume ?? 0;
                }
            }
        }
        // -------------------------------------------
        
        if ($N <= 0 && $totalCargoWeight <= 0 && $totalCargoVolume <= 0) {
        
        if ($N <= 0 && $totalCargoWeight <= 0 && $totalCargoVolume <= 0) {
            throw new Exception("Number of passengers must be greater than zero.", 400);
        }

        // Get available vehicles
        // A vehicle is available if it's 'available' or 'assigned', has a capacity, and doesn't have overlapping trips
        // (For simplicity, we check if there are no overlapping trips on that exact day)
        $availableVehicles = Vehicle::where('organization_id', $orgId)
            ->whereIn('status', ['available', 'assigned'])
            ->whereNotNull('capacity')
            ->where(function ($query) use ($tripDate) {
                $query->whereNull('registration_expiry_date')
                      ->orWhere('registration_expiry_date', '>=', $tripDate);
            })
            ->where(function ($query) use ($tripDate) {
                $query->whereNull('insurance_expiry_date')
                      ->orWhere('insurance_expiry_date', '>=', $tripDate);
            })
            ->whereNotIn('id', function($query) use ($orgId, $tripDate) {
                $query->select('vehicle_id')
                      ->from('transport_trips')
                      ->where('organization_id', $orgId)
                      ->where('trip_date', $tripDate)
                      ->whereIn('status', ['planned', 'in_progress'])
                      ->whereNotNull('vehicle_id');
            })
            // We use lockForUpdate in create, but for planning we just select
            ->orderBy('capacity', 'asc')
            ->get();

        if ($availableVehicles->isEmpty()) {
            return [
                'success' => false,
                'shortfall' => $N,
                'assigned_vehicles' => [],
                'message' => 'No vehicles available.'
            ];
        }

        $vehicles = $availableVehicles->toArray();
        
        // Strategy 1: Find a single vehicle that fits
        foreach ($vehicles as $vehicle) {
            if ($vehicle['capacity'] >= $N) {
                return [
                    'success' => true,
                    'shortfall' => 0,
                    'assigned_vehicles' => [$vehicle],
                    'message' => 'Single vehicle fits perfectly.'
                ];
            }
        }

        // Strategy 2: DP or greedy search for combination
        // To minimize vehicles, and then excess seats, we can sort by capacity desc
        usort($vehicles, function($a, $b) {
            return $b['capacity'] <=> $a['capacity'];
        });

        $assigned = [];
        $remaining = $N;
        
                // DP approach for exact match or smallest excess is better, 
        // but greedy is simpler: take largest vehicle until remaining is small enough to fit a single smaller vehicle
        $remainingWeight = $totalCargoWeight;
        $remainingVolume = $totalCargoVolume;
        
        foreach ($vehicles as $vehicle) {
            if ($remaining <= 0 && $remainingWeight <= 0 && $remainingVolume <= 0) break;
            
            // If remaining can fit in one of the smaller available vehicles perfectly, pick that one
            // We find the smallest vehicle that can fit remaining
            $bestSmall = null;
            foreach (array_reverse($vehicles) as $v) {
                $vWeightCap = $v['cargo_capacity_weight'] ?? 0;
                $vVolumeCap = $v['cargo_capacity_volume'] ?? 0;
                
                if (!in_array($v, $assigned) && $v['capacity'] >= $remaining && $vWeightCap >= $remainingWeight && $vVolumeCap >= $remainingVolume) {
                    $bestSmall = $v;
                    break;
                }
            }
            if ($bestSmall) {
                $assigned[] = $bestSmall;
                $remaining -= $bestSmall['capacity'];
                $remainingWeight -= ($bestSmall['cargo_capacity_weight'] ?? 0);
                $remainingVolume -= ($bestSmall['cargo_capacity_volume'] ?? 0);
                break;
            }
            
            // Otherwise, take the largest
            if (!in_array($vehicle, $assigned)) {
                $assigned[] = $vehicle;
                $remaining -= $vehicle['capacity'];
                $remainingWeight -= ($vehicle['cargo_capacity_weight'] ?? 0);
                $remainingVolume -= ($vehicle['cargo_capacity_volume'] ?? 0);
            }
        }
        
        if ($remaining > 0 || $remainingWeight > 0 || $remainingVolume > 0) {
            return [
                'success' => false,
                'shortfall' => max(0, $remaining),
                'shortfall_weight' => max(0, $remainingWeight),
                'shortfall_volume' => max(0, $remainingVolume),
                'assigned_vehicles' => $assigned,
                'message' => 'Not enough total capacity (seats or cargo).'
            ];
        }
        }

        return [
            'success' => true,
            'shortfall' => 0,
            'assigned_vehicles' => $assigned,
            'message' => 'Combination of vehicles found.'
        ];
    }
}
