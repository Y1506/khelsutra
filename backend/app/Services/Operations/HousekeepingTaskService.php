<?php

namespace App\Services\Operations;

use App\Models\HousekeepingTask;
use App\Models\HousekeepingSchedule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

class HousekeepingTaskService
{
    public function createManualTask($orgId, array $data, $performedBy)
    {
        return DB::transaction(function () use ($orgId, $data, $performedBy) {
            $data['organization_id'] = $orgId;
            $data['task_reference'] = $this->generateReference($orgId);
            $data['status'] = 'pending';
            
            return HousekeepingTask::create($data);
        });
    }

    public function assignTask($orgId, $taskId, $employeeId, $performedBy)
    {
        return DB::transaction(function () use ($orgId, $taskId, $employeeId, $performedBy) {
            $task = HousekeepingTask::where('organization_id', $orgId)->findOrFail($taskId);
            $task->assigned_employee_id = $employeeId;
            if ($task->status === 'pending') {
                $task->status = 'assigned';
            }
            $task->save();
            return $task;
        });
    }

    public function updateStatus($orgId, $taskId, $status, $performedBy, $additionalData = [])
    {
        return DB::transaction(function () use ($orgId, $taskId, $status, $performedBy, $additionalData) {
            $task = HousekeepingTask::where('organization_id', $orgId)->findOrFail($taskId);
            
            $task->status = $status;
            
            if ($status === 'completed') {
                $task->completed_at = now();
                $task->completed_by = $performedBy;
            } elseif ($status === 'verified') {
                $task->verified_at = now();
                $task->verified_by = $performedBy;
            } elseif ($status === 'closed') {
                $task->closed_at = now();
                $task->closed_by = $performedBy;
            } elseif ($status === 'reopened') {
                // Reset completion metrics
                $task->completed_at = null;
                $task->completed_by = null;
                $task->verified_at = null;
                $task->verified_by = null;
            }

            if (isset($additionalData['notes'])) {
                // You might have a notes column, leaving as is or extend if needed
                // $task->notes = $additionalData['notes'];
            }

            $task->save();
            return $task;
        });
    }

    private function generateReference($orgId)
    {
        $prefix = 'HK-';
        $date = date('Ymd');
        $random = strtoupper(Str::random(4));
        return $prefix . $date . '-' . $random;
    }
}
