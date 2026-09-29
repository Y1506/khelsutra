<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('housekeeping_schedules')) {
            Schema::create('housekeeping_schedules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->index();
                $table->string('name');
                $table->string('task_type');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('venue_id');
                $table->unsignedBigInteger('facility_id')->nullable();
                $table->string('area')->nullable();
                $table->enum('frequency', ['daily', 'weekly', 'monthly']);
                $table->time('time');
                $table->integer('day_of_week')->nullable();
                $table->integer('day_of_month')->nullable();
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->unsignedBigInteger('assigned_employee_id')->nullable();
                $table->string('priority')->default('medium');
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->date('next_run_date')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('housekeeping_tasks')) {
            Schema::table('housekeeping_tasks', function (Blueprint $table) {
                if (!Schema::hasColumn('housekeeping_tasks', 'schedule_id')) {
                    $table->unsignedBigInteger('schedule_id')->nullable()->after('id');
                }
                if (!Schema::hasColumn('housekeeping_tasks', 'match_id')) {
                    $table->unsignedBigInteger('match_id')->nullable()->after('schedule_id');
                }
                if (!Schema::hasColumn('housekeeping_tasks', 'event_id')) {
                    $table->unsignedBigInteger('event_id')->nullable()->after('match_id');
                }
                if (!Schema::hasColumn('housekeeping_tasks', 'area')) {
                    $table->string('area')->nullable()->after('facility_id');
                }
                if (!Schema::hasColumn('housekeeping_tasks', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable();
                }
                if (!Schema::hasColumn('housekeeping_tasks', 'completed_by')) {
                    $table->unsignedBigInteger('completed_by')->nullable();
                }
                if (!Schema::hasColumn('housekeeping_tasks', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable();
                }
                if (!Schema::hasColumn('housekeeping_tasks', 'verified_by')) {
                    $table->unsignedBigInteger('verified_by')->nullable();
                }
                if (!Schema::hasColumn('housekeeping_tasks', 'closed_at')) {
                    $table->timestamp('closed_at')->nullable();
                }
                if (!Schema::hasColumn('housekeeping_tasks', 'closed_by')) {
                    $table->unsignedBigInteger('closed_by')->nullable();
                }
                
                // Ensure organization_id exists
                if (!Schema::hasColumn('housekeeping_tasks', 'organization_id')) {
                    $table->unsignedBigInteger('organization_id')->after('id')->index();
                }
            });
            // Update status column to allow new values (this might be tricky depending on DB, but modifying enum is possible via raw SQL)
            // Or just leave it if it's already a string. According to instructions, "do not change the status column from enum unless necessary, or just use string/enum".
            // We'll change it to string for safety if possible, or add the enum values.
            \DB::statement("ALTER TABLE housekeeping_tasks MODIFY COLUMN status VARCHAR(255) DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('housekeeping_schedules');
        
        if (Schema::hasTable('housekeeping_tasks')) {
            Schema::table('housekeeping_tasks', function (Blueprint $table) {
                $table->dropColumn([
                    'schedule_id', 'match_id', 'event_id', 'area',
                    'completed_at', 'completed_by', 'verified_at',
                    'verified_by', 'closed_at', 'closed_by'
                ]);
            });
        }
    }
};
