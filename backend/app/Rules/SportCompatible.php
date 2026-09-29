<?php

namespace App\Rules;

use App\Models\Venue;
use App\Models\Facility;
use Illuminate\Contracts\Validation\Rule;

class SportCompatible implements Rule
{
    protected ?int $sportId;

    public function __construct(?int $sportId)
    {
        $this->sportId = $sportId;
    }

    public function passes($attribute, $value)
    {
        if (!$this->sportId || empty($value)) {
            return true;
        }

        if ($attribute === 'venue_id') {
            $venue = Venue::find($value);
            if (!$venue) return false;
            
            if ($venue->sports()->where('sports.id', $this->sportId)->exists()) {
                return true;
            }
            
            return Facility::where('venue_id', $value)
                ->whereHas('sports', function($q) {
                    $q->where('sports.id', $this->sportId);
                })->exists();
                
        } elseif ($attribute === 'facility_id') {
            $facility = Facility::find($value);
            if (!$facility) return false;
            
            return $facility->sports()->where('sports.id', $this->sportId)->exists();
        }

        return true;
    }

    public function message()
    {
        return 'The selected :attribute does not support the required sport.';
    }
}
