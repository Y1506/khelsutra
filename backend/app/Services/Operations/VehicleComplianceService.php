<?php

namespace App\Services\Operations;

use App\Models\Vehicle;
use Carbon\Carbon;

class VehicleComplianceService
{
    /**
     * Check if a vehicle is compliant for a trip on a specific date.
     */
    public function checkCompliance(Vehicle $vehicle, string $tripDate): array
    {
        $tripDateCarbon = Carbon::parse($tripDate);
        $errors = [];

        if ($vehicle->insurance_expiry_date && Carbon::parse($vehicle->insurance_expiry_date)->lt($tripDateCarbon)) {
            $errors[] = 'Vehicle insurance expires before or on the trip date.';
        }

        // Assuming fitness_certificate_expiry might be a field.
        if (isset($vehicle->fitness_certificate_expiry) && Carbon::parse($vehicle->fitness_certificate_expiry)->lt($tripDateCarbon)) {
            $errors[] = 'Vehicle fitness certificate expires before or on the trip date.';
        }

        if ($vehicle->status === 'maintenance') {
            $errors[] = 'Vehicle is currently under maintenance.';
        }

        return [
            'is_compliant' => count($errors) === 0,
            'errors' => $errors
        ];
    }
}
