<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as DB;

return new class extends Migration
{
    public function up(): void
    {
        $schema = DB::schema();

        // Soft deletes
        $tables = ['venue_bookings', 'transport_trips', 'accommodation_allocations'];
        foreach ($tables as $table) {
            if ($schema->hasTable($table) && !$schema->hasColumn($table, 'deleted_at')) {
                $schema->table($table, function (Blueprint $t) {
                    $t->softDeletes();
                });
            }
        }

        // Waitlist table
        if (!$schema->hasTable('booking_waitlist')) {
            $schema->create('booking_waitlist', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->unsignedBigInteger('venue_id');
                $table->unsignedBigInteger('facility_id')->nullable();
                $table->unsignedBigInteger('user_id'); // requester
                $table->dateTime('requested_start');
                $table->dateTime('requested_end');
                $table->integer('priority')->default(0);
                $table->enum('status', ['waiting', 'offered', 'accepted', 'expired', 'cancelled'])->default('waiting');
                $table->dateTime('offer_expires_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Idempotency table
        if (!$schema->hasTable('idempotency_keys')) {
            $schema->create('idempotency_keys', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('key', 100);
                $table->string('method', 10);
                $table->string('path');
                $table->string('request_hash');
                $table->integer('response_status')->nullable();
                $table->json('response_body')->nullable();
                $table->dateTime('expires_at');
                $table->timestamps();
                $table->unique(['organization_id', 'user_id', 'key'], 'uq_idemp_key');
            });
        }

        // Phase 2: Category and medical
        if (!$schema->hasTable('tournament_categories')) {
            $schema->create('tournament_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tournament_id');
                $table->unsignedBigInteger('sport_id');
                $table->string('label');
                $table->enum('gender', ['male', 'female', 'mixed', 'open'])->default('open');
                $table->integer('min_age')->nullable();
                $table->integer('max_age')->nullable();
                $table->date('age_cutoff_date')->nullable();
                $table->decimal('min_weight_kg', 5, 2)->nullable();
                $table->decimal('max_weight_kg', 5, 2)->nullable();
                $table->timestamps();
            });
        }

        if ($schema->hasTable('athletes') && !$schema->hasColumn('athletes', 'weight_kg')) {
            $schema->table('athletes', function (Blueprint $table) {
                $table->decimal('weight_kg', 5, 2)->nullable()->after('gender');
            });
        }

        // Phase 5: Vehicles compliance and cargo
        if ($schema->hasTable('vehicles') && !$schema->hasColumn('vehicles', 'insurance_expiry')) {
            $schema->table('vehicles', function (Blueprint $table) {
                $table->date('insurance_expiry')->nullable();
                $table->date('fitness_certificate_expiry')->nullable();
                $table->date('pollution_certificate_expiry')->nullable();
                $table->date('next_service_date')->nullable();
                $table->integer('next_service_odometer')->nullable();
                $table->integer('odometer_km')->nullable();
                $table->decimal('cargo_capacity_kg', 8, 2)->nullable();
                $table->decimal('cargo_volume_m3', 8, 2)->nullable();
                $table->decimal('cost_per_km', 8, 2)->nullable();
            });
        }
        
        if (!$schema->hasTable('sport_equipment_profiles')) {
            $schema->create('sport_equipment_profiles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sport_id');
                $table->decimal('kg_per_athlete', 5, 2)->default(0);
                $table->decimal('fixed_kg', 8, 2)->default(0);
                $table->timestamps();
            });
        }

        // Phase 8: Fixtures
        if ($schema->hasTable('fixtures') && !$schema->hasColumn('fixtures', 'facility_id')) {
            $schema->table('fixtures', function (Blueprint $table) {
                $table->unsignedBigInteger('facility_id')->nullable();
                $table->dateTime('scheduled_start')->nullable();
                $table->dateTime('scheduled_end')->nullable();
            });
        }
    }

    public function down(): void {}
};
