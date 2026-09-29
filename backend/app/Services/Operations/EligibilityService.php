<?php

namespace App\Services\Operations;

use App\Models\Athlete;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EligibilityService
{
    /**
     * Check if an athlete is eligible for a specific category.
     * Category could be an array of rules like: ['min_age' => 18, 'max_age' => 25, 'gender' => 'M', 'max_weight' => 80]
     */
    public function check(Athlete $athlete, array $categoryRules): array
    {
        $errors = [];

        // Check age
        if (isset($categoryRules['min_age']) || isset($categoryRules['max_age'])) {
            $age = Carbon::parse($athlete->date_of_birth)->age;
            if (isset($categoryRules['min_age']) && $age < $categoryRules['min_age']) {
                $errors[] = "Athlete is below minimum age of {$categoryRules['min_age']}.";
            }
            if (isset($categoryRules['max_age']) && $age > $categoryRules['max_age']) {
                $errors[] = "Athlete is above maximum age of {$categoryRules['max_age']}.";
            }
        }

        // Check gender
        if (isset($categoryRules['gender']) && $athlete->gender !== $categoryRules['gender']) {
            $errors[] = "Athlete gender ({$athlete->gender}) does not match required gender ({$categoryRules['gender']}).";
        }

        // Check weight
        if (isset($categoryRules['max_weight']) && $athlete->weight > $categoryRules['max_weight']) {
            $errors[] = "Athlete weight exceeds maximum allowed weight of {$categoryRules['max_weight']} kg.";
        }

        return [
            'is_eligible' => count($errors) === 0,
            'errors' => $errors,
        ];
    }
}
