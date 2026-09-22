<?php

namespace App\Services\Operations;

use App\Models\TransportTrip;
use App\Models\TransportPassenger;
use App\Models\Vehicle;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

/**
 * Provides transport trip management including creation, passenger management, and updates.
 */
class TransportTripService
{
    /**
     * Create a new transport trip with vehicle and driver validation.
     *
     * Validates vehicle availability, registration, insurance, and checks for
     * overlapping trips for both vehicle and driver.
     *
     * @param int $orgId Organization ID
     * @param array $data Trip data including vehicle_id, driver_employee_id, and trip_date
     * @return TransportTrip The created transport trip
     * @throws Exception If vehicle is unavailable, expired, or already booked
     */
    public function createTrip(int $orgId, array $data): TransportTrip
    {
        return DB::transaction(function () use ($orgId, $data) {
            $vehicleId = $data['vehicle_id'];
            $driverId = $data['driver_employee_id'] ?? null;
            $tripDate = $data['trip_date'];
            
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

            return TransportTrip::create($data);
        });
    }

    /**
     * Add a passenger to an active transport trip with capacity and duplicate checks.
     *
     * Validates trip status, vehicle capacity, and prevents duplicate passengers.
     *
     * @param int $orgId Organization ID
     * @param int $tripId Trip ID
     * @param array $data Passenger data including athlete_id, employee_id, or coach_id
     * @return TransportPassenger The added passenger record
     * @throws Exception If trip is inactive, capacity exceeded, or passenger already added
     */
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

    /**
     * Update a transport trip and automatically record expenses when completed.
     *
     * When status changes to 'completed' and actual_cost is provided, automatically
     * creates an expense record linked to the trip.
     *
     * @param int $orgId Organization ID
     * @param int $tripId Trip ID
     * @param array $data Updated trip data including optional status and actual_cost
     * @return TransportTrip The updated transport trip
     * @throws Exception If trip not found
     */
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
}
