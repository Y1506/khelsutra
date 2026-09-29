<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryCategoryService;
use App\Helpers\ApiResponse;
use InvalidArgumentException;
use Exception;

class InventoryCategoryController extends Controller
{
    protected InventoryCategoryService $categoryService;

    public function __construct(?InventoryCategoryService $categoryService = null)
    {
        $this->categoryService = $categoryService ?? new InventoryCategoryService();
    }

    /**
     * List all inventory categories for an organization.
     */
    public function index(int $orgId): array
    {
        $status = $_GET['status'] ?? null;
        $categories = $this->categoryService->listCategories($orgId, $status);
        return ApiResponse::success($categories, 'Inventory categories retrieved successfully', 200);
    }

    /**
     * Show a single inventory category.
     */
    public function show(int $orgId, int $id): array
    {
        $category = $this->categoryService->getCategory($orgId, $id);
        if (!$category) {
            return ApiResponse::error('Category not found or access denied.', null, 404);
        }
        return ApiResponse::success($category, 'Category retrieved successfully', 200);
    }

    /**
     * Create a new inventory category.
     */
    public function store(int $orgId, array $requestData, ?int $performedBy = null): array
    {
        if (empty($requestData['name'])) {
            return ApiResponse::error('Category name is required.', ['name' => ['The name field is required.']], 422);
        }

        try {
            $cat = $this->categoryService->createCategory($orgId, $requestData, $performedBy);
            return ApiResponse::success($cat, 'Inventory category created successfully', 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Unable to create category: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update an inventory category.
     */
    public function update(int $orgId, int $id, array $requestData, ?int $performedBy = null): array
    {
        try {
            $updated = $this->categoryService->updateCategory($orgId, $id, $requestData, $performedBy);
            if (!$updated) {
                return ApiResponse::error('Category not found or access denied.', null, 404);
            }
            return ApiResponse::success($updated, 'Inventory category updated successfully', 200);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Unable to update category: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Activate or deactivate an inventory category.
     */
    public function setStatus(int $orgId, int $id, array $requestData, ?int $performedBy = null): array
    {
        $status = $requestData['status'] ?? '';
        try {
            $updated = $this->categoryService->setStatus($orgId, $id, $status, $performedBy);
            if (!$updated) {
                return ApiResponse::error('Category not found or access denied.', null, 404);
            }
            return ApiResponse::success($updated, "Category status changed to '{$status}' successfully", 200);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Unable to change status: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Soft-delete an inventory category.
     */
    public function destroy(int $orgId, int $id, ?int $performedBy = null): array
    {
        try {
            $ok = $this->categoryService->deleteCategory($orgId, $id, $performedBy);
            if (!$ok) {
                return ApiResponse::error('Category not found or access denied.', null, 404);
            }
            return ApiResponse::success(null, 'Inventory category deleted successfully', 200);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 404);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), null, 400);
        }
    }
}
