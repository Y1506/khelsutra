<?php

namespace App\Services\Operations;

use App\Models\Accommodation;
use App\Models\AccommodationRoom;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class AccommodationService
{
    /**
     * Create a new accommodation.
     *
     * @param int $orgId The organization ID
     * @param array $data Accommodation data
     * @return Accommodation The created accommodation
     */
    public function createAccommodation(int $orgId, array $data): Accommodation
    {
        return DB::transaction(function () use ($orgId, $data) {
            $data['organization_id'] = $orgId;
            $data['status'] = $data['status'] ?? 'active';
            
            return Accommodation::create($data);
        });
    }

    /**
     * Create a new room in an accommodation.
     *
     * @param int $orgId The organization ID
     * @param int $accommodationId The accommodation ID
     * @param array $data Room data
     * @return AccommodationRoom The created room
     */
    public function createRoom(int $orgId, int $accommodationId, array $data): AccommodationRoom
    {
        return DB::transaction(function () use ($orgId, $accommodationId, $data) {
            $accommodation = Accommodation::where('organization_id', $orgId)->findOrFail($accommodationId);
            
            $data['organization_id'] = $orgId;
            $data['accommodation_id'] = $accommodation->id;
            $data['status'] = $data['status'] ?? 'available';

            return AccommodationRoom::create($data);
        });
    }

    /**
     * Delete an accommodation if it has no active allocations.
     *
     * @param int $orgId The organization ID
     * @param int $id The accommodation ID
     * @return bool True if deleted successfully
     */
    public function deleteAccommodation(int $orgId, int $id): bool
    {
        return DB::transaction(function () use ($orgId, $id) {
            $acc = Accommodation::where('organization_id', $orgId)->lockForUpdate()->findOrFail($id);
            
            // Check active future allocations
            $hasActiveAllocations = DB::table('accommodation_allocations')
                ->where('organization_id', $orgId)
                ->where('accommodation_id', $id)
                ->whereIn('status', ['reserved', 'checked_in'])
                ->where(function($q) {
                    $q->whereNull('check_out_date')
                      ->orWhere('check_out_date', '>', date('Y-m-d'));
                })
                ->exists();

            if ($hasActiveAllocations) {
                throw new Exception("Cannot delete accommodation with active allocations.", 409);
            }

            return $acc->delete();
        });
    }
}
