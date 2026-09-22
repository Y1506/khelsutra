<?php

namespace App\Services\Operations;

use App\Models\VenueMaintenance;
use Illuminate\Database\Capsule\Manager as DB;

class MaintenanceService
{
    /**
     * Create a new maintenance ticket with generated reference.
     *
     * @param int $orgId The organization ID
     * @param array $data Ticket data
     * @return VenueMaintenance The created ticket
     */
    public function createTicket(int $orgId, array $data): VenueMaintenance
    {
        return DB::transaction(function () use ($orgId, $data) {
            $data['organization_id'] = $orgId;
            $data['maintenance_reference'] = ReferenceGenerator::generate('MN', 'venue_maintenance', 'maintenance_reference', $orgId);
            $data['status'] = 'reported';
            if (!isset($data['scheduled_date'])) $data['scheduled_date'] = date('Y-m-d');
            
            return VenueMaintenance::create($data);
        });
    }

    /**
     * Update a maintenance ticket and record expense if completed.
     *
     * @param int $orgId The organization ID
     * @param int $id The ticket ID
     * @param array $data Updated ticket data
     * @return VenueMaintenance The updated ticket
     */
    public function updateTicket(int $orgId, int $id, array $data): VenueMaintenance
    {
        return DB::transaction(function () use ($orgId, $id, $data) {
            $ticket = VenueMaintenance::where('organization_id', $orgId)->lockForUpdate()->findOrFail($id);
            
            $ticket->update($data);

            if ($ticket->status === 'completed' && $ticket->actual_cost > 0 && !$ticket->expense_id) {
                $recorder = new \App\Services\Operations\ExpenseRecorder();
                $expenseId = $recorder->recordExpense($orgId, $ticket->actual_cost, "Maintenance: " . $ticket->issue_title, [
                    'vendor_id' => $ticket->assigned_vendor_id,
                ]);
                $ticket->expense_id = $expenseId;
                $ticket->save();
            }

            return $ticket;
        });
    }
}
