<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\DB;

class AthleteFitnessProvider
{
    /**
     * Return fitness status: 'fit', 'unfit', or 'unknown'
     */
    public function getFitnessStatus(int $athleteId, int $organizationId): string
    {
        // Check active injuries
        $activeInjury = DB::table('athlete_injuries')
            ->where('athlete_id', $athleteId)
            ->where('organization_id', $organizationId)
            ->where(function ($q) {
                $q->whereNull('expected_recovery_date')
                  ->orWhere('expected_recovery_date', '>=', now()->toDateString());
            })
            ->exists();

        if ($activeInjury) {
            return 'unfit';
        }

        // Check latest medical clearance
        $clearance = DB::table('athlete_medical_clearances')
            ->where('athlete_id', $athleteId)
            ->where('organization_id', $organizationId)
            ->orderBy('clearance_date', 'desc')
            ->first();

        if (!$clearance) {
            return 'unknown';
        }

        // Check if clearance is expired
        if ($clearance->valid_until && $clearance->valid_until < now()->toDateString()) {
            return 'unknown';
        }

        if ($clearance->clearance_status === 'unfit') {
            return 'unfit';
        }

        // fit or fit_with_restrictions
        return 'fit';
    }
}
