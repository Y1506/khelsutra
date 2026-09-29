<?php

namespace App\Http\Controllers\Api\V1\Operations;

use App\Helpers\ApiResponse;
use App\Services\Operations\VenueBookingService;
use App\Services\Operations\VenueAvailabilityService;
use App\Http\Requests\Venues\VenueBookingRequest;
use App\Models\VenueBooking;

class VenueBookingController
{
    protected VenueBookingService $bookingService;
    protected VenueAvailabilityService $availabilityService;

    public function __construct()
    {
        $this->bookingService = new VenueBookingService();
        $this->availabilityService = new VenueAvailabilityService();
    }

    public function index(int $orgId, array $requestData): array
    {
        $query = VenueBooking::where('organization_id', $orgId);
        
        if (!empty($requestData['venue_id'])) {
            $query->where('venue_id', $requestData['venue_id']);
        }
        if (!empty($requestData['booking_date'])) {
            $query->where('booking_date', $requestData['booking_date']);
        }

        $bookings = $query->orderBy('booking_date', 'desc')->get();
        return ApiResponse::success(['data' => $bookings->toArray()]);
    }

    public function availability(int $orgId, int $venueId, array $requestData): array
    {
        if (empty($requestData['date'])) {
            return ApiResponse::error('Date is required', null, 400);
        }

        $check = $this->availabilityService->checkAvailability(
            $orgId,
            $venueId,
            $requestData['facility_id'] ?? null,
            $requestData['date'],
            $requestData['start_time'] ?? '00:00:00',
            $requestData['end_time'] ?? '23:59:59'
        );

        return ApiResponse::success($check);
    }

    public function facilityAvailability(int $orgId, int $facilityId, array $requestData): array
    {
        if (empty($requestData['date'])) {
            return ApiResponse::error('Date is required', null, 400);
        }

        // We need to look up the venue_id for this facility
        $facility = \App\Models\Facility::where('organization_id', $orgId)->findOrFail($facilityId);

        $check = $this->availabilityService->checkAvailability(
            $orgId,
            $facility->venue_id,
            $facilityId,
            $requestData['date'],
            $requestData['start_time'] ?? '00:00:00',
            $requestData['end_time'] ?? '23:59:59'
        );

        return ApiResponse::success($check);
    }

    public function store(int $orgId, array $requestData): array
    {
        $request = new VenueBookingRequest($requestData);
        $errors = $request->validate();
        
        if (!empty($errors)) {
            return ApiResponse::error('Validation failed', $errors, 422);
        }

        try {
            // Mock permissions check
            $hasManagePerm = true; 
            $booking = $this->bookingService->createBooking($orgId, $request->data, $hasManagePerm);
            return ApiResponse::success($booking->toArray(), 'Booking created successfully', 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Entity not found', null, 404);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), null, $e->getCode() === 409 ? 409 : 400);
        }
    }

    public function cancel(int $orgId, int $id, array $requestData): array
    {
        try {
            $userId = $requestData['user_id'] ?? 1; // mocked
            $reason = $requestData['cancellation_reason'] ?? $requestData['reason'] ?? '';
            $booking = $this->bookingService->cancelBooking($orgId, $id, $userId, $reason);
            return ApiResponse::success($booking->toArray(), 'Booking cancelled');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Booking not found', null, 404);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), null, $e->getCode() === 409 ? 409 : 400);
        }
    }
}
