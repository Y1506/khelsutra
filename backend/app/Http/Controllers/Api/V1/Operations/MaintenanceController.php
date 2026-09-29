<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Helpers\ApiResponse;
use App\Services\Operations\MaintenanceService;
use App\Models\VenueMaintenance;

class MaintenanceController
{
    protected MaintenanceService $service;

    public function __construct()
    {
        $this->service = new MaintenanceService();
    }

    public function index(int $orgId, array $requestData): array
    {
        $query = VenueMaintenance::where('organization_id', $orgId);
        $tickets = $query->orderBy('created_at', 'desc')->get();
        return ApiResponse::success(['data' => $tickets->toArray()]);
    }

    public function store(int $orgId, array $requestData): array
    {
        try {
            // Mass assignment protection
            $allowed = [
                'venue_id', 'facility_id', 'issue_title', 'issue_description', 
                'priority', 'assigned_employee_id', 'assigned_vendor_id', 
                'scheduled_date', 'estimated_cost', 'notes'
            ];
            $data = array_intersect_key($requestData, array_flip($allowed));

            if (empty($data['issue_title']) || empty($data['priority']) || empty($data['venue_id'])) {
                return ApiResponse::error('Missing required fields', null, 422);
            }

            $ticket = $this->service->createTicket($orgId, $data);
            return ApiResponse::success($ticket->toArray(), 'Maintenance ticket created', 201);
        } catch (\Exception $e) {
            error_log('Maintenance store error: ' . $e->getMessage());
            return ApiResponse::error('Failed to create ticket', null, 400);
        }
    }

    public function update(int $orgId, int $id, array $requestData): array
    {
        try {
            // Mass assignment protection
            $allowed = [
                'issue_title', 'issue_description', 'priority', 'assigned_employee_id', 
                'assigned_vendor_id', 'scheduled_date', 'estimated_cost', 'actual_cost',
                'completed_date', 'status', 'notes'
            ];
            $data = array_intersect_key($requestData, array_flip($allowed));

            $ticket = $this->service->updateTicket($orgId, $id, $data);
            return ApiResponse::success($ticket->toArray(), 'Maintenance ticket updated');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Ticket not found', null, 404);
        } catch (\Exception $e) {
            error_log('Maintenance update error: ' . $e->getMessage());
            return ApiResponse::error('Failed to update ticket', null, 400);
        }
    }
}
