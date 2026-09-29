<?php

namespace App\Services\Operations;

use App\Models\Venue;
use Carbon\Carbon;

class AlternativeSuggestionService
{
    protected VenueAvailabilityService $availabilityService;

    public function __construct(VenueAvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }

    /**
     * Return alternative time slots on the same day when a 409 overlap occurs.
     */
    public function suggestAlternatives(
        int $orgId,
        int $venueId,
        ?int $facilityId,
        string $date,
        string $requestedStartTime,
        string $requestedEndTime,
        int $durationMinutes
    ): array {
        $venue = Venue::where('organization_id', $orgId)->find($venueId);
        if (!$venue) {
            return [];
        }

        $openingTime = $venue->opening_time ?? '00:00:00';
        $closingTime = $venue->closing_time ?? '23:59:59';
        
        $suggestions = [];
        $currentStart = Carbon::parse($date . ' ' . $openingTime);
        $endOfDay = Carbon::parse($date . ' ' . $closingTime);

        // Simple scan: 30-minute increments
        while ($currentStart->copy()->addMinutes($durationMinutes)->lte($endOfDay)) {
            $slotStart = $currentStart->format('H:i:s');
            $slotEnd = $currentStart->copy()->addMinutes($durationMinutes)->format('H:i:s');
            
            $check = $this->availabilityService->checkAvailability(
                $orgId,
                $venueId,
                $facilityId,
                $date,
                $slotStart,
                $slotEnd
            );

            if ($check['available']) {
                $suggestions[] = [
                    'start_time' => $slotStart,
                    'end_time' => $slotEnd,
                ];
                
                if (count($suggestions) >= 3) {
                    break; // return up to 3 alternatives
                }
            }
            
            $currentStart->addMinutes(30);
        }

        return $suggestions;
    }
}
