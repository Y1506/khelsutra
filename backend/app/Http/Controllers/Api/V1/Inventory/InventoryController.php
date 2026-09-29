<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\InventoryCategoryService;
use App\Helpers\ApiResponse;
use InvalidArgumentException;
use Exception;

/**
 * Dedicated REST API controller for Member 5 Inventory Management:
 * List items, item details, create/update, stock operations, stock history, low-stock.
 */
class InventoryController extends Controller
{
    protected InventoryService $inventoryService;
    protected InventoryCategoryService $categoryService;

    public function __construct(
        ?InventoryService $inventoryService = null,
        ?InventoryCategoryService $categoryService = null
    ) {
        $this->inventoryService = $inventoryService ?? new InventoryService();
        $this->categoryService = $categoryService ?? new InventoryCategoryService();
    }

    /**
     * List inventory items with optional filters, search, and pagination.
     */
    public function index(int $orgId, array $requestData = []): array
    {
        $page = max(1, (int)($requestData['page'] ?? ($_GET['page'] ?? 1)));
        $limit = max(1, min(100, (int)($requestData['limit'] ?? ($_GET['limit'] ?? 15))));
        $search = trim($requestData['search'] ?? ($_GET['search'] ?? ''));
        $categoryId = !empty($requestData['category_id'] ?? ($_GET['category_id'] ?? null))
            ? (int)($requestData['category_id'] ?? $_GET['category_id'])
            : null;
        $status = trim($requestData['status'] ?? ($_GET['status'] ?? ''));
        $lowStockOnly = !empty($requestData['low_stock'] ?? ($_GET['low_stock'] ?? false)) || $status === 'low_stock';

        $result = $this->inventoryService->listItems(
            $orgId,
            $page,
            $limit,
            $search ?: null,
            $categoryId,
            $status ?: null,
            $lowStockOnly
        );

        return ApiResponse::success($result, 'Inventory items retrieved successfully', 200);
    }

    /**
     * Show single inventory item details with transaction history.
     */
    public function show(int $orgId, int $id): array
    {
        $item = $this->inventoryService->getItem($orgId, $id);
        if (!$item) {
            return ApiResponse::error('Inventory item not found or access denied.', null, 404);
        }
        return ApiResponse::success($item, 'Inventory item retrieved successfully', 200);
    }

    /**
     * Create a new inventory item.
     */
    public function store(int $orgId, array $requestData, ?int $performedBy = null): array
    {
        if (empty($requestData['item_name'])) {
            return ApiResponse::error('Item name is required.', ['item_name' => ['The item_name field is required.']], 422);
        }

        try {
            $created = $this->inventoryService->createItem($orgId, $requestData, $performedBy);
            return ApiResponse::success($created, 'Inventory item created successfully', 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Unable to create inventory item: ' . $e->getMessage(), null, 400);
        }
    }

    /**
     * Update an existing inventory item profile.
     */
    public function update(int $orgId, int $id, array $requestData, ?int $performedBy = null): array
    {
        try {
            $ok = $this->inventoryService->updateItem($orgId, $id, $requestData, $performedBy);
            if (!$ok) {
                return ApiResponse::error('Inventory item not found or update failed.', null, 404);
            }
            $updated = $this->inventoryService->getItem($orgId, $id);
            return ApiResponse::success($updated, 'Inventory item updated successfully', 200);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Unable to update inventory item: ' . $e->getMessage(), null, 400);
        }
    }

    /**
     * Soft-delete an inventory item.
     */
    public function destroy(int $orgId, int $id, ?int $performedBy = null): array
    {
        try {
            $ok = $this->inventoryService->deleteItem($orgId, $id, $performedBy);
            if (!$ok) {
                return ApiResponse::error('Inventory item not found or access denied.', null, 404);
            }
            return ApiResponse::success(null, 'Inventory item deleted successfully', 200);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 404);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }

    /**
     * Record a stock transaction (Stock In, Stock Out, Adjustment).
     */
    public function recordTransaction(int $orgId, int $id, array $requestData, ?int $performedBy = null): array
    {
        try {
            $result = $this->inventoryService->recordStockTransaction($orgId, $id, $requestData, $performedBy);
            return ApiResponse::success($result, 'Stock transaction recorded successfully', 200);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }

    /**
     * Get stock transactions history for an item.
     */
    public function transactions(int $orgId, int $id, array $requestData = []): array
    {
        $page = max(1, (int)($requestData['page'] ?? ($_GET['page'] ?? 1)));
        $limit = max(1, min(100, (int)($requestData['limit'] ?? ($_GET['limit'] ?? 20))));
        $type = !empty($requestData['type'] ?? ($_GET['type'] ?? null)) ? trim($requestData['type'] ?? $_GET['type']) : null;

        $result = $this->inventoryService->getStockTransactions($orgId, $id, $page, $limit, $type);
        return ApiResponse::success($result, 'Stock transactions retrieved successfully', 200);
    }

    /**
     * Get low stock alerts for an organization.
     */
    public function lowStock(int $orgId): array
    {
        $items = $this->inventoryService->getLowStockItems($orgId);
        return ApiResponse::success($items, 'Low stock items retrieved successfully', 200);
    }

    /**
     * Get inventory categories list.
     */
    public function categories(int $orgId): array
    {
        $cats = $this->categoryService->listCategories($orgId);
        return ApiResponse::success($cats, 'Inventory categories retrieved successfully', 200);
    }
}
