<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\DB;
use App\Models\Venue;

class FixtureSchedulingService
{
    protected VenueAvailabilityService $availabilityService;

    public function __construct(VenueAvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }

    public function propose(int $orgId, int $tournamentId, array $options = []): array
    {
        // Find fixtures without a facility (we only have read access to Member 3's tables, but we can query them)
        // Assume Member 3 has `fixtures` table with tournament_id, date, start_time, end_time, venue_id, facility_id
        
        $unassignedFixtures = DB::table('fixtures')
            ->where('organization_id', $orgId)
            ->where('tournament_id', $tournamentId)
            ->whereNull('facility_id')
            ->get();
            
        $proposals = [];

        // We can get tournament_venues to know which venues we can use
        $tournamentVenues = DB::table('tournament_venues')
            ->where('organization_id', $orgId)
            ->where('tournament_id', $tournamentId)
            ->pluck('venue_id')
            ->toArray();

        if (empty($tournamentVenues)) {
            // Default to all active venues if none specifically linked
            $tournamentVenues = Venue::where('organization_id', $orgId)->where('status', 'active')->pluck('id')->toArray();
        }

        foreach ($unassignedFixtures as $fixture) {
            $proposal = [
                'fixture_id' => $fixture->id,
                'date' => $fixture->date ?? date('Y-m-d'),
                'start_time' => $fixture->start_time ?? '09:00:00',
                'end_time' => $fixture->end_time ?? '11:00:00',
                'suggested_venues' => []
            ];

            foreach ($tournamentVenues as $venueId) {
                // Find active facilities in this venue
                $facilities = DB::table('venue_facilities')
                    ->where('organization_id', $orgId)
                    ->where('venue_id', $venueId)
                    ->where('status', 'active')
                    ->pluck('id');

                foreach ($facilities as $facilityId) {
                    $check = $this->availabilityService->checkAvailability(
                        $orgId,
                        $venueId,
                        $facilityId,
                        $proposal['date'],
                        $proposal['start_time'],
                        $proposal['end_time']
                    );

                    if ($check['available']) {
                        $proposal['suggested_venues'][] = [
                            'venue_id' => $venueId,
                            'facility_id' => $facilityId
                        ];
                    }
                }
            }
            
            $proposals[] = $proposal;
        }

        return $proposals;
    }
}
