<?php

namespace App\Services\Operations;

use App\Models\TransportTrip;
use App\Models\TransportPassenger;
use App\Models\Vehicle;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class TransportTripService
{
    public function createTrip(int $orgId, array $data): array|TransportTrip
    {
        return DB::transaction(function () use ($orgId, $data) {
            $tripDate = $data['trip_date'];

            if (!empty($data['auto_assign'])) {
                $assignmentService = new TransportAssignmentService();
                $plan = $assignmentService->planAssignment($orgId, [
                    'travellers' => $data['travellers'] ?? 0,
                    'seat_buffer' => $data['seat_buffer'] ?? 0,
                    'trip_date' => $tripDate
                ]);

                if (!$plan['success']) {
                    throw new Exception("Cannot auto-assign vehicles: " . $plan['message'] . " Shortfall: " . $plan['shortfall'], 409);
                }

                $tripGroupId = uniqid('TG-');
                $trips = [];

                foreach ($plan['assigned_vehicles'] as $vehicleData) {
                    $vehicleId = $vehicleData['id'];
                    $vehicle = Vehicle::where('organization_id', $orgId)->lockForUpdate()->findOrFail($vehicleId);

                    if (!in_array($vehicle->status, ['available', 'assigned'])) {
                        throw new Exception("Vehicle {$vehicleId} is no longer available.", 409);
                    }

                    $overlappingTrips = TransportTrip::where('organization_id', $orgId)
                        ->where('trip_date', $tripDate)
                        ->where('vehicle_id', $vehicleId)
                        ->whereIn('status', ['planned', 'in_progress'])
                        ->exists();

                    if ($overlappingTrips) {
                        throw new Exception("Vehicle {$vehicleId} already booked for this date.", 409);
                    }

                    $tripData = $data;
                    $tripData['organization_id'] = $orgId;
                    $tripData['vehicle_id'] = $vehicleId;
                    $tripData['trip_reference'] = ReferenceGenerator::generate('TR', 'transport_trips', 'trip_reference', $orgId);
                    $tripData['status'] = 'planned';
                    $tripData['assignment_mode'] = 'auto';
                    $tripData['trip_group_id'] = $tripGroupId;
                    $tripData['passenger_count'] = $vehicle->capacity; // Or dynamically distribute passengers? Simple way for now.

                    $trips[] = TransportTrip::create($tripData);
                }

                return $trips;
            }

            $vehicleId = $data['vehicle_id'] ?? null;
            $driverId = $data['driver_employee_id'] ?? null;
            
            if ($vehicleId) {
                $vehicle = Vehicle::where('organization_id', $orgId)->lockForUpdate()->findOrFail($vehicleId);

                if (!in_array($vehicle->status, ['available', 'assigned'])) {
                    throw new Exception("Vehicle is not available.", 409);
                }

                if ($vehicle->registration_expiry_date && $vehicle->registration_expiry_date < $tripDate) {
                    throw new Exception("Vehicle registration expired before trip.", 409);
                }
                if ($vehicle->insurance_expiry_date && $vehicle->insurance_expiry_date < $tripDate) {
                    throw new Exception("Vehicle insurance expired before trip.", 409);
                }

                $overlappingTrips = TransportTrip::where('organization_id', $orgId)
                    ->where('trip_date', $tripDate)
                    ->where('vehicle_id', $vehicleId)
                    ->whereIn('status', ['planned', 'in_progress'])
                    ->exists();

                if ($overlappingTrips) {
                    throw new Exception("Vehicle already booked for this date.", 409);
                }
            }

            if ($driverId) {
                $overlappingDriver = TransportTrip::where('organization_id', $orgId)
                    ->where('trip_date', $tripDate)
                    ->where('driver_employee_id', $driverId)
                    ->whereIn('status', ['planned', 'in_progress'])
                    ->exists();

                if ($overlappingDriver) {
                    throw new Exception("Driver already booked for this date.", 409);
                }
            }

            $data['organization_id'] = $orgId;
            $data['trip_reference'] = ReferenceGenerator::generate('TR', 'transport_trips', 'trip_reference', $orgId);
            $data['status'] = 'planned';
            $data['assignment_mode'] = 'manual';

            return TransportTrip::create($data);
        });
    }

    public function addPassenger(int $orgId, int $tripId, array $data): TransportPassenger
    {
        return DB::transaction(function () use ($orgId, $tripId, $data) {
            $trip = TransportTrip::where('organization_id', $orgId)->lockForUpdate()->findOrFail($tripId);
            
            if (!in_array($trip->status, ['planned', 'in_progress'])) {
                throw new Exception("Trip is not active.", 409);
            }

            if ($trip->vehicle_id) {
                $vehicle = Vehicle::where('organization_id', $orgId)->find($trip->vehicle_id);
                if ($vehicle && $vehicle->capacity === null) {
                    throw new Exception("Cannot add passengers until vehicle capacity is set.", 409);
                }

                $currentPassengers = TransportPassenger::where('organization_id', $orgId)
                    ->where('transport_trip_id', $trip->id)
                    ->count();
                
                if ($vehicle && $currentPassengers >= $vehicle->capacity) {
                    throw new Exception("Vehicle capacity exceeded.", 409);
                }
            }

            // check duplicate passenger (if using profile)
            if (isset($data['athlete_id'])) {
                $dup = TransportPassenger::where('organization_id', $orgId)
                    ->where('transport_trip_id', $trip->id)
                    ->where('athlete_id', $data['athlete_id'])
                    ->exists();
                if ($dup) throw new Exception("Passenger already on trip.", 409);
            } elseif (isset($data['employee_id'])) {
                $dup = TransportPassenger::where('organization_id', $orgId)
                    ->where('transport_trip_id', $trip->id)
                    ->where('employee_id', $data['employee_id'])
                    ->exists();
                if ($dup) throw new Exception("Passenger already on trip.", 409);
            } elseif (isset($data['coach_id'])) {
                $dup = TransportPassenger::where('organization_id', $orgId)
                    ->where('transport_trip_id', $trip->id)
                    ->where('coach_id', $data['coach_id'])
                    ->exists();
                if ($dup) throw new Exception("Passenger already on trip.", 409);
            }

            $data['organization_id'] = $orgId;
            $data['transport_trip_id'] = $trip->id;
            
            return TransportPassenger::create($data);
        });
    }

    public function updateTrip(int $orgId, int $tripId, array $data): TransportTrip
    {
        return DB::transaction(function () use ($orgId, $tripId, $data) {
            $trip = TransportTrip::where('organization_id', $orgId)->lockForUpdate()->findOrFail($tripId);
            
            $trip->update($data);

            if ($trip->status === 'completed' && isset($data['actual_cost']) && $data['actual_cost'] > 0 && !$trip->expense_id) {
                $recorder = new \App\Services\Operations\ExpenseRecorder();
                $expenseId = $recorder->recordExpense($orgId, $data['actual_cost'], "Transport Trip: " . $trip->trip_reference, [
                    'event_id' => $trip->event_id ?? null,
                ]);
                $trip->expense_id = $expenseId;
                $trip->save();
            }

            return $trip;
        });
    }

    public function getRouteHistory(int $orgId, int $tripId): array
    {
        $trackingService = new VehicleTrackingService();
        return $trackingService->getRouteHistoryForTrip($orgId, $tripId);
    }
}
