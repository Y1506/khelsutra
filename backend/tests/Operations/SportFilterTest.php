<?php

namespace Tests\Operations;

use PHPUnit\Framework\TestCase;
use App\Services\Operations\SportContextService;
use App\Rules\SportCompatible;
use App\Models\Venue;
use App\Models\Facility;
use App\Models\Sport;
use App\Http\Requests\Venues\VenueBookingRequest;
use Illuminate\Database\Capsule\Manager as DB;

class SportFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::table('tournaments')->truncate();
        DB::statement("SET FOREIGN_KEY_CHECKS=0;"); DB::table('venues')->truncate(); DB::statement("SET FOREIGN_KEY_CHECKS=1;");
        DB::table('venue_facilities')->truncate();
        DB::table('venue_sports')->truncate();
        DB::table('facility_sports')->truncate();
        DB::table('sports')->truncate();
    }

    public function testSportContextServiceResolvesTournamentSport()
    {
        $sportId = DB::table('sports')->insertGetId([
            'name' => 'Badminton',
            'is_global' => 1
        ]);

        $tournamentId = DB::table('tournaments')->insertGetId([
            'organization_id' => 1,
            'sport_id' => $sportId,
            'tournament_reference' => 'T-123', 'name' => 'Badminton Cup',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-10'
        ]);

        $service = new SportContextService();
        $this->assertEquals($sportId, $service->resolveSportContext('tournament', $tournamentId));
    }

    public function testSportCompatibleRuleFailsForWrongSport()
    {
        $badmintonId = DB::table('sports')->insertGetId(['name' => 'Badminton', 'is_global' => 1]);
        $cricketId = DB::table('sports')->insertGetId(['name' => 'Cricket', 'is_global' => 1]);

        $venue = Venue::create([
            'organization_id' => 1,
            'name' => 'Cricket Ground',
            'venue_code' => 'CG1'
        ]);
        
        $venue->sports()->attach($cricketId, ['organization_id' => 1]);

        $rule = new SportCompatible($badmintonId);
        $this->assertFalse($rule->passes('venue_id', $venue->id));
    }

    public function testSportCompatibleRulePassesForCorrectSportOnFacility()
    {
        $badmintonId = DB::table('sports')->insertGetId(['name' => 'Badminton', 'is_global' => 1]);

        $venue = Venue::create([
            'organization_id' => 1,
            'name' => 'Multi Sports Arena',
            'venue_code' => 'MSA1'
        ]);
        
        $facility = Facility::create([
            'organization_id' => 1,
            'venue_id' => $venue->id,
            'name' => 'Badminton Court 1',
            'facility_type' => 'indoor'
        ]);
        
        $facility->sports()->attach($badmintonId, ['organization_id' => 1]);

        $rule = new SportCompatible($badmintonId);
        $this->assertTrue($rule->passes('venue_id', $venue->id));
        $this->assertTrue($rule->passes('facility_id', $facility->id));
    }

    public function testBookingRequestFailsValidation()
    {
        $badmintonId = DB::table('sports')->insertGetId(['name' => 'Badminton', 'is_global' => 1]);
        $cricketId = DB::table('sports')->insertGetId(['name' => 'Cricket', 'is_global' => 1]);

        $tournamentId = DB::table('tournaments')->insertGetId([
            'organization_id' => 1,
            'sport_id' => $badmintonId,
            'tournament_reference' => 'T-123', 'name' => 'Badminton Cup',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-10'
        ]);

        $venue = Venue::create([
            'organization_id' => 1,
            'name' => 'Cricket Ground',
            'venue_code' => 'CG2'
        ]);
        $venue->sports()->attach($cricketId, ['organization_id' => 1]);

        $request = new VenueBookingRequest([
            'tournament_id' => $tournamentId,
            'venue_id' => $venue->id,
            'booked_by_user_id' => 1,
            'booking_date' => '2026-10-05',
            'start_time' => '10:00:00',
            'end_time' => '12:00:00'
        ]);

        $errors = $request->validate();
        $this->assertArrayHasKey('venue_id', $errors);
        $this->assertEquals('The selected venue_id does not support the required sport.', $errors['venue_id'][0]);
    }
}
