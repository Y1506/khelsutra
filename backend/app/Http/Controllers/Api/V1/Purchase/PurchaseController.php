<?php

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Helpers\ApiResponse;
use App\Services\Purchase\PurchaseService;
use Exception;
use InvalidArgumentException;

/**
 * REST API controller for procurement workflows:
 * Purchase Requests, Purchase Orders, and Goods Receipts.
 */
class PurchaseController
{
    protected PurchaseService $purchaseService;

    public function __construct(?PurchaseService $purchaseService = null)
    {
        $this->purchaseService = $purchaseService ?? new PurchaseService();
    }

    // ==========================================
    // 1. PURCHASE REQUESTS
    // ==========================================

    public function requests(int $orgId, array $requestData = []): array
    {
        $page = (int)($requestData['page'] ?? 1);
        $limit = (int)($requestData['limit'] ?? 15);
        $search = isset($requestData['search']) ? trim($requestData['search']) : null;
        $status = isset($requestData['status']) ? trim($requestData['status']) : null;

        $result = $this->purchaseService->listPurchaseRequests($orgId, $page, $limit, $search, $status);
        return ApiResponse::success($result, 'Purchase requests retrieved successfully', 200);
    }

    public function showRequest(int $orgId, int $id): array
    {
        $pr = $this->purchaseService->getPurchaseRequest($orgId, $id);
        if (!$pr) {
            return ApiResponse::error('Purchase request not found or access denied.', null, 404);
        }
        return ApiResponse::success($pr, 'Purchase request retrieved successfully', 200);
    }

    public function storeRequest(int $orgId, array $requestData, ?int $performedBy = null): array
    {
        try {
            $created = $this->purchaseService->createPurchaseRequest($orgId, $requestData, $performedBy);
            return ApiResponse::success($created, 'Purchase request created successfully', 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to create purchase request: ' . $e->getMessage(), null, 500);
        }
    }

    public function submitRequest(int $orgId, int $id, ?int $performedBy = null): array
    {
        try {
            $updated = $this->purchaseService->submitPurchaseRequest($orgId, $id, $performedBy);
            return ApiResponse::success($updated, 'Purchase request submitted for approval', 200);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to submit purchase request: ' . $e->getMessage(), null, 500);
        }
    }

    public function approveRequest(int $orgId, int $id, ?int $performedBy = null): array
    {
        try {
            $updated = $this->purchaseService->approvePurchaseRequest($orgId, $id, $performedBy);
            return ApiResponse::success($updated, 'Purchase request approved successfully', 200);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to approve purchase request: ' . $e->getMessage(), null, 500);
        }
    }

    public function rejectRequest(int $orgId, int $id, array $requestData, ?int $performedBy = null): array
    {
        $reason = trim($requestData['rejection_reason'] ?? ($requestData['reason'] ?? ''));
        try {
            $updated = $this->purchaseService->rejectPurchaseRequest($orgId, $id, $reason ?: null, $performedBy);
            return ApiResponse::success($updated, 'Purchase request rejected', 200);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to reject purchase request: ' . $e->getMessage(), null, 500);
        }
    }

    public function cancelRequest(int $orgId, int $id, ?int $performedBy = null): array
    {
        try {
            $updated = $this->purchaseService->cancelPurchaseRequest($orgId, $id, $performedBy);
            return ApiResponse::success($updated, 'Purchase request cancelled', 200);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to cancel purchase request: ' . $e->getMessage(), null, 500);
        }
    }

    // ==========================================
    // 2. PURCHASE ORDERS
    // ==========================================

    public function orders(int $orgId, array $requestData = []): array
    {
        $page = (int)($requestData['page'] ?? 1);
        $limit = (int)($requestData['limit'] ?? 15);
        $search = isset($requestData['search']) ? trim($requestData['search']) : null;
        $status = isset($requestData['status']) ? trim($requestData['status']) : null;
        $vendorId = !empty($requestData['vendor_id']) ? (int)$requestData['vendor_id'] : null;

        $result = $this->purchaseService->listPurchaseOrders($orgId, $page, $limit, $search, $status, $vendorId);
        return ApiResponse::success($result, 'Purchase orders retrieved successfully', 200);
    }

    public function showOrder(int $orgId, int $id): array
    {
        $po = $this->purchaseService->getPurchaseOrder($orgId, $id);
        if (!$po) {
            return ApiResponse::error('Purchase order not found or access denied.', null, 404);
        }
        return ApiResponse::success($po, 'Purchase order retrieved successfully', 200);
    }

    public function storeOrder(int $orgId, array $requestData, ?int $performedBy = null): array
    {
        try {
            $created = $this->purchaseService->createPurchaseOrder($orgId, $requestData, $performedBy);
            return ApiResponse::success($created, 'Purchase order created successfully', 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to create purchase order: ' . $e->getMessage(), null, 500);
        }
    }

    public function updateOrderStatus(int $orgId, int $id, array $requestData, ?int $performedBy = null): array
    {
        $newStatus = trim($requestData['status'] ?? '');
        if (empty($newStatus)) {
            return ApiResponse::error('Status field is required.', null, 422);
        }

        try {
            $updated = $this->purchaseService->updatePoStatus($orgId, $id, $newStatus, $performedBy);
            return ApiResponse::success($updated, 'Purchase order status updated', 200);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to update purchase order status: ' . $e->getMessage(), null, 500);
        }
    }

    // ==========================================
    // 3. GOODS RECEIPTS & INVENTORY INTEGRATION
    // ==========================================

    public function receiveGoods(int $orgId, int $poId, array $requestData, ?int $performedBy = null): array
    {
        try {
            $result = $this->purchaseService->receiveGoods($orgId, $poId, $requestData, $performedBy);
            return ApiResponse::success($result, 'Goods received and inventory updated successfully', 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to process goods receipt: ' . $e->getMessage(), null, 500);
        }
    }

    public function receipts(int $orgId, array $requestData = []): array
    {
        $page = (int)($requestData['page'] ?? 1);
        $limit = (int)($requestData['limit'] ?? 15);
        $poId = !empty($requestData['purchase_order_id']) ? (int)$requestData['purchase_order_id'] : null;

        $result = $this->purchaseService->listGoodsReceipts($orgId, $page, $limit, $poId);
        return ApiResponse::success($result, 'Goods receipts retrieved successfully', 200);
    }

    public function showReceipt(int $orgId, int $id): array
    {
        $receipt = $this->purchaseService->getGoodsReceipt($orgId, $id);
        if (!$receipt) {
            return ApiResponse::error('Goods receipt not found or access denied.', null, 404);
        }
        return ApiResponse::success($receipt, 'Goods receipt retrieved successfully', 200);
    }
}
