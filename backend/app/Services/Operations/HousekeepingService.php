<?php

namespace App\Services\Operations;

use App\Models\HousekeepingTask;
use Illuminate\Database\Capsule\Manager as DB;

class HousekeepingService
{
    /**
     * Create a new housekeeping task with generated reference.
     *
     * @param int $orgId The organization ID
     * @param array $data Task data
     * @return HousekeepingTask The created task
     */
    public function createTask(int $orgId, array $data): HousekeepingTask
    {
        return DB::transaction(function () use ($orgId, $data) {
            $data['organization_id'] = $orgId;
            $data['task_reference'] = ReferenceGenerator::generate('HK', 'housekeeping_tasks', 'task_reference', $orgId);
            $data['status'] = 'pending';
            if (!isset($data['scheduled_date'])) $data['scheduled_date'] = date('Y-m-d');
            
            return HousekeepingTask::create($data);
        });
    }

    /**
     * Update a housekeeping task.
     *
     * @param int $orgId The organization ID
     * @param int $id The task ID
     * @param array $data Updated task data
     * @return HousekeepingTask The updated task
     */
    public function updateTask(int $orgId, int $id, array $data): HousekeepingTask
    {
        return DB::transaction(function () use ($orgId, $id, $data) {
            $task = HousekeepingTask::where('organization_id', $orgId)->findOrFail($id);
            $task->update($data);
            return $task;
        });
    }
}
