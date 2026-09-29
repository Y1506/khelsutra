<?php

namespace App\Services\Operations;

use App\Models\HousekeepingSchedule;
use App\Models\HousekeepingTask;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

class HousekeepingScheduleService
{
    public function createSchedule($orgId, array $data, $performedBy)
    {
        return DB::transaction(function () use ($orgId, $data) {
            $data['organization_id'] = $orgId;
            $data['next_run_date'] = $this->calculateNextRunDate($data['start_date'], $data['frequency'], $data['day_of_week'] ?? null, $data['day_of_month'] ?? null);
            return HousekeepingSchedule::create($data);
        });
    }

    public function updateSchedule($orgId, $scheduleId, array $data, $performedBy)
    {
        return DB::transaction(function () use ($orgId, $scheduleId, $data) {
            $schedule = HousekeepingSchedule::where('organization_id', $orgId)->findOrFail($scheduleId);
            $schedule->update($data);
            
            // Recalculate next run date if necessary
            $schedule->next_run_date = $this->calculateNextRunDate(
                max($schedule->start_date->toDateString(), date('Y-m-d')),
                $schedule->frequency,
                $schedule->day_of_week,
                $schedule->day_of_month
            );
            $schedule->save();
            
            return $schedule;
        });
    }

    public function generateScheduledTasks($orgId = null)
    {
        $query = HousekeepingSchedule::where('status', 'active')
            ->whereNotNull('next_run_date')
            ->where('next_run_date', '<=', now()->toDateString());
            
        if ($orgId) {
            $query->where('organization_id', $orgId);
        }

        $schedules = $query->get();

        $generatedTasks = [];

        DB::transaction(function () use ($schedules, &$generatedTasks) {
            foreach ($schedules as $schedule) {
                // Generate task
                $task = HousekeepingTask::create([
                    'organization_id' => $schedule->organization_id,
                    'schedule_id' => $schedule->id,
                    'task_reference' => $this->generateReference($schedule->organization_id),
                    'task_type' => $schedule->task_type,
                    'venue_id' => $schedule->venue_id,
                    'facility_id' => $schedule->facility_id,
                    'area' => $schedule->area,
                    'priority' => $schedule->priority,
                    'scheduled_date' => $schedule->next_run_date,
                    'start_time' => $schedule->time,
                    'assigned_employee_id' => $schedule->assigned_employee_id,
                    'status' => 'pending'
                ]);

                $generatedTasks[] = $task;

                // Update next_run_date
                $schedule->next_run_date = $this->calculateNextRunDate(
                    Carbon::parse($schedule->next_run_date)->addDay()->toDateString(),
                    $schedule->frequency,
                    $schedule->day_of_week,
                    $schedule->day_of_month
                );
                
                if ($schedule->end_date && Carbon::parse($schedule->next_run_date)->gt($schedule->end_date)) {
                    $schedule->next_run_date = null; // Expired
                    $schedule->status = 'inactive';
                }
                
                $schedule->save();
            }
        });

        return $generatedTasks;
    }

    private function calculateNextRunDate($startDate, $frequency, $dayOfWeek, $dayOfMonth)
    {
        $date = Carbon::parse($startDate);
        
        switch ($frequency) {
            case 'daily':
                return $date->toDateString();
            case 'weekly':
                if ($dayOfWeek !== null) {
                    while ($date->dayOfWeekIso !== (int)$dayOfWeek) {
                        $date->addDay();
                    }
                }
                return $date->toDateString();
            case 'monthly':
                if ($dayOfMonth !== null) {
                    $targetDate = Carbon::create($date->year, $date->month, $dayOfMonth);
                    if ($targetDate->lt($date)) {
                        $targetDate->addMonth();
                    }
                    return $targetDate->toDateString();
                }
                return $date->toDateString();
        }
        
        return $date->toDateString();
    }

    private function generateReference($orgId)
    {
        $prefix = 'HK-';
        $date = date('Ymd');
        $random = strtoupper(Str::random(4));
        return $prefix . $date . '-' . $random;
    }
}
