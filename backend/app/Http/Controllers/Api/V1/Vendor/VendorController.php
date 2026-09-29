<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorService;
use App\Helpers\ApiResponse;
use InvalidArgumentException;
use Exception;

class VendorController extends Controller
{
    protected VendorService $vendorService;

    public function __construct(?VendorService $vendorService = null)
    {
        $this->vendorService = $vendorService ?? new VendorService();
    }

    /**
     * List vendors with filtering, search, and pagination.
     */
    public function index(int $orgId): array
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 15)));
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $vendorType = trim($_GET['vendor_type'] ?? '');

        $result = $this->vendorService->listVendors(
            $orgId,
            $page,
            $limit,
            $search ?: null,
            $status ?: null,
            $vendorType ?: null
        );

        return ApiResponse::success($result, 'Vendors retrieved successfully', 200);
    }

    /**
     * Show vendor details with metrics, recent invoices, and purchase orders.
     */
    public function show(int $orgId, int $id): array
    {
        $vendor = $this->vendorService->getVendor($orgId, $id);
        if (!$vendor) {
            return ApiResponse::error('Vendor not found or access denied.', null, 404);
        }
        return ApiResponse::success($vendor, 'Vendor retrieved successfully', 200);
    }

    /**
     * Create a new vendor.
     */
    public function store(int $orgId, array $requestData, ?int $performedBy = null): array
    {
        if (empty($requestData['company_name'])) {
            return ApiResponse::error('Company name is required.', ['company_name' => ['The company_name field is required.']], 422);
        }

        try {
            $created = $this->vendorService->createVendor($orgId, $requestData, $performedBy);
            return ApiResponse::success($created, 'Vendor created successfully', 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to create vendor: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Update an existing vendor.
     */
    public function update(int $orgId, int $id, array $requestData, ?int $performedBy = null): array
    {
        try {
            $ok = $this->vendorService->updateVendor($orgId, $id, $requestData, $performedBy);
            if ($ok) {
                $updated = $this->vendorService->getVendor($orgId, $id);
                return ApiResponse::success($updated, 'Vendor updated successfully', 200);
            }
            return ApiResponse::error('Failed to update vendor.', null, 500);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to update vendor: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Set vendor status (active, inactive, blacklisted).
     */
    public function setStatus(int $orgId, int $id, array $requestData, ?int $performedBy = null): array
    {
        $status = trim($requestData['status'] ?? '');
        if (empty($status)) {
            return ApiResponse::error('Status field is required.', null, 422);
        }

        try {
            $result = $this->vendorService->setStatus($orgId, $id, $status, $performedBy);
            return ApiResponse::success($result, 'Vendor status updated successfully', 200);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to update status: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Soft delete a vendor.
     */
    public function destroy(int $orgId, int $id, ?int $performedBy = null): array
    {
        try {
            $ok = $this->vendorService->deleteVendor($orgId, $id, $performedBy);
            if ($ok) {
                return ApiResponse::success(null, 'Vendor deleted successfully', 200);
            }
            return ApiResponse::error('Failed to delete vendor.', null, 500);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to delete vendor: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * List invoices for a vendor.
     */
    public function invoices(int $orgId, int $vendorId): array
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 15)));
        $status = trim($_GET['status'] ?? '');
        $search = trim($_GET['search'] ?? '');

        $result = $this->vendorService->listInvoices($orgId, $vendorId, $page, $limit, $status ?: null, $search ?: null);
        return ApiResponse::success($result, 'Vendor invoices retrieved successfully', 200);
    }

    /**
     * Create an invoice for a vendor.
     */
    public function storeInvoice(int $orgId, int $vendorId, array $requestData, ?int $performedBy = null): array
    {
        if (empty($requestData['invoice_number'])) {
            return ApiResponse::error('Invoice number is required.', null, 422);
        }

        try {
            $created = $this->vendorService->createInvoice($orgId, $vendorId, $requestData, $performedBy);
            return ApiResponse::success($created, 'Vendor invoice created successfully', 201);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to create invoice: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get single invoice.
     */
    public function showInvoice(int $orgId, int $invoiceId): array
    {
        $invoice = $this->vendorService->getInvoice($orgId, $invoiceId);
        if (!$invoice) {
            return ApiResponse::error('Invoice not found or access denied.', null, 404);
        }
        return ApiResponse::success($invoice, 'Invoice retrieved successfully', 200);
    }

    /**
     * Update an invoice.
     */
    public function updateInvoice(int $orgId, int $invoiceId, array $requestData, ?int $performedBy = null): array
    {
        try {
            $ok = $this->vendorService->updateInvoice($orgId, $invoiceId, $requestData, $performedBy);
            if ($ok) {
                $updated = $this->vendorService->getInvoice($orgId, $invoiceId);
                return ApiResponse::success($updated, 'Invoice updated successfully', 200);
            }
            return ApiResponse::error('Failed to update invoice.', null, 500);
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        } catch (Exception $e) {
            return ApiResponse::error('Failed to update invoice: ' . $e->getMessage(), null, 500);
        }
    }
}
