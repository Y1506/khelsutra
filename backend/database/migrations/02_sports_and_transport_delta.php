<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as DB;

return new class extends Migration
{
    public function up(): void
    {
        $schema = DB::schema();

        // 1. Modify sports table (already InnoDB)
        if ($schema->hasTable('sports')) {
            $schema->table('sports', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('sports', 'slug')) {
                    $table->string('slug', 100)->nullable()->unique();
                }
                if (!$schema->hasColumn('sports', 'is_team_sport')) {
                    $table->boolean('is_team_sport')->default(false);
                }
                if (!$schema->hasColumn('sports', 'min_team_size')) {
                    $table->integer('min_team_size')->nullable();
                }
                if (!$schema->hasColumn('sports', 'max_team_size')) {
                    $table->integer('max_team_size')->nullable();
                }
            });
        }

        // 2. Create pivots
        $pivots = ['athlete_sports', 'coach_sports', 'facility_sports', 'venue_sports'];
        
        // Athlete sports
        if (!$schema->hasTable('athlete_sports')) {
            $schema->create('athlete_sports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->unsignedBigInteger('athlete_id');
                $table->unsignedBigInteger('sport_id');
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->unique(['organization_id', 'athlete_id', 'sport_id'], 'uq_athlete_sport');
            });
        }

        // Coach sports
        if (!$schema->hasTable('coach_sports')) {
            $schema->create('coach_sports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->unsignedBigInteger('coach_id'); // refers to coach_profiles
                $table->unsignedBigInteger('sport_id');
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->unique(['organization_id', 'coach_id', 'sport_id'], 'uq_coach_sport');
            });
        }

        // Facility sports
        if (!$schema->hasTable('facility_sports')) {
            $schema->create('facility_sports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->unsignedBigInteger('facility_id');
                $table->unsignedBigInteger('sport_id');
                $table->timestamps();

                $table->unique(['organization_id', 'facility_id', 'sport_id'], 'uq_facility_sport');
            });
        }

        // Venue sports
        if (!$schema->hasTable('venue_sports')) {
            $schema->create('venue_sports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->unsignedBigInteger('venue_id');
                $table->unsignedBigInteger('sport_id');
                $table->timestamps();

                $table->unique(['organization_id', 'venue_id', 'sport_id'], 'uq_venue_sport');
            });
        }

        // 3. Add sport_id to events and fixtures
        if ($schema->hasTable('events') && !$schema->hasColumn('events', 'sport_id')) {
            $schema->table('events', function (Blueprint $table) {
                $table->unsignedBigInteger('sport_id')->nullable()->after('venue_id');
            });
        }

        if ($schema->hasTable('fixtures') && !$schema->hasColumn('fixtures', 'sport_id')) {
            $schema->table('fixtures', function (Blueprint $table) {
                $table->unsignedBigInteger('sport_id')->nullable()->after('venue_id');
            });
        }

        // 4. Transport Assignment
        if ($schema->hasTable('transport_trips')) {
            $schema->table('transport_trips', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('transport_trips', 'assignment_mode')) {
                    $table->enum('assignment_mode', ['manual', 'auto'])->default('manual')->after('status');
                }
                if (!$schema->hasColumn('transport_trips', 'passenger_count')) {
                    $table->integer('passenger_count')->default(0)->after('assignment_mode');
                }
                if (!$schema->hasColumn('transport_trips', 'trip_group_id')) {
                    $table->string('trip_group_id', 50)->nullable()->after('trip_reference');
                }
                if (!$schema->hasColumn('transport_trips', 'source_type')) {
                    $table->string('source_type', 50)->nullable();
                }
                if (!$schema->hasColumn('transport_trips', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable();
                }
            });
        }
        
        // 5. Bookings and Accommodations Context Prop
        if ($schema->hasTable('venue_bookings')) {
            $schema->table('venue_bookings', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('venue_bookings', 'source_type')) {
                    $table->string('source_type', 50)->nullable();
                }
                if (!$schema->hasColumn('venue_bookings', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable();
                }
            });
        }
        
        if ($schema->hasTable('accommodation_allocations')) {
            $schema->table('accommodation_allocations', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('accommodation_allocations', 'source_type')) {
                    $table->string('source_type', 50)->nullable();
                }
                if (!$schema->hasColumn('accommodation_allocations', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Safe additive down
    }
};
