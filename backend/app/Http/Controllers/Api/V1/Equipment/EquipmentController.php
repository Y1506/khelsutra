<?php

namespace App\Http\Controllers\Api\V1\Equipment;

use App\Http\Controllers\Controller;
use App\Services\Equipment\EquipmentService;
use App\Helpers\ApiResponse;
use InvalidArgumentException;
use Exception;

class EquipmentController extends Controller
{
    protected EquipmentService $equipmentService;

    public function __construct(?EquipmentService $equipmentService = null)
    {
        $this->equipmentService = $equipmentService ?? new EquipmentService();
    }

    /**
     * List equipment with optional search, status, condition, and pagination.
     */
    public function index(int $orgId): array
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 15)));
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $condition = trim($_GET['condition'] ?? '');
        $itemId = !empty($_GET['inventory_item_id']) ? (int)$_GET['inventory_item_id'] : null;

        $result = $this->equipmentService->listEquipment(
            $orgId,
            $page,
            $limit,
            $search ?: null,
            $status ?: null,
            $condition ?: null,
            $itemId
        );

        return ApiResponse::success($result, 'Equipment retrieved successfully', 200);
    }

    /**
     * Show single equipment details with active assignment and assignment history.
     */
    public function show(int $orgId, int $id): array
    {
        $equipment = $this->equipmentService->getEquipment($orgId, $id);
        if (!$equipment) {
            return ApiResponse::error('Equipment not found or access denied.', null, 404);
        }
        return ApiResponse::success($equipment, 'Equipment retrieved successfully', 200);
    }

    /**
     * Create new equipment asset.
     */
    public function store(int $orgId, array $requestData, ?int $performedBy = null): array
    {
        if (empty($requestData['equipment_name'])) {
            return ApiResponse::error('Equipment name is required.', ['equipment_name' => ['The equipment_name field is required.']], 422);
        }

        try {
            $created = $this->equipmentService->createEquipment($orgId, $requestData, $performedBy);
            return ApiResponse::success($created, 'Equipment asset created successfully', 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to create equipment: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update equipment asset.
     */
    public function update(int $orgId, int $id, array $requestData, ?int $performedBy = null): array
    {
        try {
            $ok = $this->equipmentService->updateEquipment($orgId, $id, $requestData, $performedBy);
            if ($ok) {
                $updated = $this->equipmentService->getEquipment($orgId, $id);
                return ApiResponse::success($updated, 'Equipment updated successfully', 200);
            }
            return ApiResponse::error('Failed to update equipment.', null, 500);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to update equipment: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Soft delete equipment asset.
     */
    public function destroy(int $orgId, int $id, ?int $performedBy = null): array
    {
        try {
            $ok = $this->equipmentService->deleteEquipment($orgId, $id, $performedBy);
            if ($ok) {
                return ApiResponse::success(null, 'Equipment deleted successfully', 200);
            }
            return ApiResponse::error('Failed to delete equipment.', null, 500);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to delete equipment: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Assign equipment to athlete, coach, employee, team, or venue.
     */
    public function assign(int $orgId, int $id, array $requestData, ?int $performedBy = null): array
    {
        if (empty($requestData['assignee_type'])) {
            return ApiResponse::error('Assignee type is required (athlete, coach, employee, team, venue).', null, 422);
        }

        try {
            $assignment = $this->equipmentService->assignEquipment($orgId, $id, $requestData, $performedBy);
            return ApiResponse::success($assignment, 'Equipment assigned successfully', 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to assign equipment: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Return assigned equipment and update condition / status.
     */
    public function returnItem(int $orgId, int $id, array $requestData, ?int $performedBy = null): array
    {
        try {
            $result = $this->equipmentService->returnEquipment($orgId, $id, $requestData, $performedBy);
            return ApiResponse::success($result, 'Equipment returned successfully', 200);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to process return: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get assignment history ledger for an equipment item.
     */
    public function assignments(int $orgId, int $id): array
    {
        $history = $this->equipmentService->getAssignmentHistory($orgId, $id);
        return ApiResponse::success($history, 'Equipment assignment history retrieved successfully', 200);
    }
}
