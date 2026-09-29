<?php

namespace Tests\Operations;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as DB;
use App\Models\Vehicle;
use App\Models\TransportTrip;
use App\Services\Operations\TransportAssignmentService;
use Exception;

class TransportAssignmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::beginTransaction();
        
        DB::table('organizations')->insertOrIgnore([
            ['id' => 1, 'name' => 'Org 1']
        ]);
        if (!defined('CURRENT_ORGANIZATION_ID')) {
            define('CURRENT_ORGANIZATION_ID', 1);
        }
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        DB::statement("SET FOREIGN_KEY_CHECKS=1;");
        parent::tearDown();
    }

    public function test_single_bus_fits()
    {
        Vehicle::create([
            'organization_id' => 1,
            'name' => 'Bus 1',
            'vehicle_number' => 'V-100',
            'capacity' => 20, 'vehicle_type' => 'Bus',
            'status' => 'available'
        ]);

        $service = new TransportAssignmentService();
        $plan = $service->planAssignment(1, [
            'travellers' => 18,
            'seat_buffer' => 0,
            'trip_date' => '2026-10-01'
        ]);

        $this->assertTrue($plan['success']);
        $this->assertCount(1, $plan['assigned_vehicles']);
        $this->assertEquals(20, $plan['assigned_vehicles'][0]['capacity']);
    }

    public function test_multiple_smaller_vehicles_when_bus_unavailable()
    {
        // Bus is booked
        $bus = Vehicle::create([
            'organization_id' => 1,
            'name' => 'Bus 1',
            'vehicle_number' => 'V-101',
            'capacity' => 20, 'vehicle_type' => 'Bus',
            'status' => 'available'
        ]);
        
        TransportTrip::create([
            'organization_id' => 1,
            'vehicle_id' => $bus->id,
            'trip_reference' => 'TR-1',
            'trip_date' => '2026-10-01',
            'status' => 'planned'
        ]);

        // Available smaller vehicles
        Vehicle::create([
            'organization_id' => 1,
            'name' => 'Van 1',
            'vehicle_number' => 'V-102',
            'capacity' => 10, 'vehicle_type' => 'Van',
            'status' => 'available'
        ]);
        Vehicle::create([
            'organization_id' => 1,
            'name' => 'Van 2',
            'vehicle_number' => 'V-103',
            'capacity' => 10, 'vehicle_type' => 'Van',
            'status' => 'available'
        ]);

        $service = new TransportAssignmentService();
        $plan = $service->planAssignment(1, [
            'travellers' => 18,
            'seat_buffer' => 0,
            'trip_date' => '2026-10-01'
        ]);

        $this->assertTrue($plan['success']);
        $this->assertCount(2, $plan['assigned_vehicles']); // Should pick two vans
    }

    public function test_capacity_shortfall()
    {
        Vehicle::create([
            'organization_id' => 1,
            'name' => 'Van 1',
            'vehicle_number' => 'V-104',
            'capacity' => 10, 'vehicle_type' => 'Van',
            'status' => 'available'
        ]);

        $service = new TransportAssignmentService();
        $plan = $service->planAssignment(1, [
            'travellers' => 18,
            'seat_buffer' => 0,
            'trip_date' => '2026-10-01'
        ]);

        $this->assertFalse($plan['success']);
        $this->assertEquals(8, $plan['shortfall']); // 18 - 10
        $this->assertCount(1, $plan['assigned_vehicles']); // Assigned the 10 seater, but still shortfall
    }

    public function test_overlapping_booking_rejection_in_candidate_pool()
    {
        $bus = Vehicle::create([
            'organization_id' => 1,
            'name' => 'Bus 1',
            'vehicle_number' => 'V-105',
            'capacity' => 30, 'vehicle_type' => 'Bus',
            'status' => 'available'
        ]);
        
        TransportTrip::create([
            'organization_id' => 1,
            'vehicle_id' => $bus->id,
            'trip_reference' => 'TR-2',
            'trip_date' => '2026-10-01',
            'status' => 'planned'
        ]);

        $service = new TransportAssignmentService();
        $plan = $service->planAssignment(1, [
            'travellers' => 25,
            'seat_buffer' => 0,
            'trip_date' => '2026-10-01'
        ]);

        $this->assertFalse($plan['success']);
        $this->assertEquals(25, $plan['shortfall']);
    }
}
