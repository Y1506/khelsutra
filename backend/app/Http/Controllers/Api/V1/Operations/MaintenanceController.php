<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Helpers\ApiResponse;
use App\Services\Operations\MaintenanceService;
use App\Models\VenueMaintenance;

class MaintenanceController
{
    protected MaintenanceService $service;

    /**
     * Create a new MaintenanceController instance.
     */
    public function __construct()
    {
        $this->service = new MaintenanceService();
    }

    /**
     * Get all maintenance tickets for an organization.
     *
     * @param int $orgId The organization ID
     * @param array $requestData Request parameters
     * @return array API response with maintenance tickets data
     */
    public function index(int $orgId, array $requestData): array
    {
        $query = VenueMaintenance::where('organization_id', $orgId);
        $tickets = $query->orderBy('created_at', 'desc')->get();
        return ApiResponse::success(['data' => $tickets->toArray()]);
    }

    /**
     * Create a new maintenance ticket.
     *
     * @param int $orgId The organization ID
     * @param array $requestData Ticket data
     * @return array API response with created ticket or error
     */
    public function store(int $orgId, array $requestData): array
    {
        try {
            $ticket = $this->service->createTicket($orgId, $requestData);
            return ApiResponse::success($ticket->toArray(), 'Maintenance ticket created', 201);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }

    /**
     * Update a maintenance ticket.
     *
     * @param int $orgId The organization ID
     * @param int $id The ticket ID
     * @param array $requestData Updated ticket data
     * @return array API response with updated ticket or error
     */
    public function update(int $orgId, int $id, array $requestData): array
    {
        try {
            $ticket = $this->service->updateTicket($orgId, $id, $requestData);
            return ApiResponse::success($ticket->toArray(), 'Maintenance ticket updated');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }
}
