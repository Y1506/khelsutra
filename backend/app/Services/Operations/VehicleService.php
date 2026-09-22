<?php

namespace App\Services\Operations;

use App\Models\Vehicle;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class VehicleService
{
    /**
     * Create a new vehicle.
     *
     * @param int $orgId Organization ID
     * @param array $data Vehicle data
     * @return Vehicle Created vehicle
     */
    public function createVehicle(int $orgId, array $data): Vehicle
    {
        return DB::transaction(function () use ($orgId, $data) {
            $data['organization_id'] = $orgId;
            $data['status'] = $data['status'] ?? 'available';
            
            return Vehicle::create($data);
        });
    }

    /**
     * Delete a vehicle.
     *
     * @param int $orgId Organization ID
     * @param int $id Vehicle ID to delete
     * @return bool True on success
     * @throws Exception If vehicle has active trips or not found
     */
    public function deleteVehicle(int $orgId, int $id): bool
    {
        return DB::transaction(function () use ($orgId, $id) {
            $vehicle = Vehicle::where('organization_id', $orgId)->lockForUpdate()->findOrFail($id);

            // check active trips
            $activeTrips = DB::table('transport_trips')
                ->where('organization_id', $orgId)
                ->where('vehicle_id', $id)
                ->whereIn('status', ['planned', 'in_progress'])
                ->exists();

            if ($activeTrips) {
                throw new Exception("Cannot delete vehicle with active trips.", 409);
            }

            return $vehicle->delete();
        });
    }
}
