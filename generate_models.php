<?php
/**
 * Utility script to auto-generate Eloquent model classes for operations-related entities.
 *
 * Creates model files in backend/app/Models/ with appropriate traits (SoftDeletes,
 * BelongsToOrganization) and table configurations based on the models array.
 */

$models = [
    'Venue' => ['table' => 'venues', 'soft_deletes' => true],
    'Facility' => ['table' => 'venue_facilities', 'soft_deletes' => true],
    'VenueBooking' => ['table' => 'venue_bookings', 'soft_deletes' => true],
    'VenueMaintenance' => ['table' => 'venue_maintenance', 'soft_deletes' => true],
    'HousekeepingTask' => ['table' => 'housekeeping_tasks', 'soft_deletes' => true],
    'Event' => ['table' => 'events', 'soft_deletes' => true],
    'EventParticipant' => ['table' => 'event_participants', 'soft_deletes' => false, 'no_updated_at' => true],
    'SchoolActivity' => ['table' => 'school_activities', 'soft_deletes' => true],
    'Vehicle' => ['table' => 'vehicles', 'soft_deletes' => true],
    'TransportTrip' => ['table' => 'transport_trips', 'soft_deletes' => false],
    'TransportPassenger' => ['table' => 'transport_passengers', 'soft_deletes' => false, 'no_updated_at' => true],
    'Accommodation' => ['table' => 'accommodations', 'soft_deletes' => true],
    'AccommodationRoom' => ['table' => 'accommodation_rooms', 'soft_deletes' => false],
    'AccommodationAllocation' => ['table' => 'accommodation_allocations', 'soft_deletes' => false],
];

foreach ($models as $class => $config) {
    $code = "<?php\n\nnamespace App\Models;\n\nuse Illuminate\Database\Eloquent\Model;\nuse App\Traits\BelongsToOrganization;\n";
    if ($config['soft_deletes']) {
        $code .= "use Illuminate\Database\Eloquent\SoftDeletes;\n";
    }
    $code .= "\nclass {$class} extends Model\n{\n";
    
    $traits = ["BelongsToOrganization"];
    if ($config['soft_deletes']) {
        $traits[] = "SoftDeletes";
    }
    $code .= "    use " . implode(', ', $traits) . ";\n\n";
    
    $code .= "    protected \$table = '{$config['table']}';\n";
    $code .= "    protected \$guarded = ['id'];\n";
    
    if (isset($config['no_updated_at']) && $config['no_updated_at']) {
        $code .= "\n    public const UPDATED_AT = null;\n";
    }
    
    $code .= "}\n";
    
    $file = __DIR__ . "/backend/app/Models/{$class}.php";
    // Check if the file already exists and extends Eloquent.
    // If it does, we might overwrite it.
    file_put_contents($file, $code);
    echo "Created {$class}.php\n";
}
