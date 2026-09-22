<?php

namespace App\Helpers;

class OperationsPermissionHelper
{
    /**
     * Map operations action to existing permissions.
     */
    public static function getPermissions(string $action): array
    {
        $map = [
            'view_venues' => ['venue.view', 'venue.manage', 'housekeeping.manage'],
            'create_venue' => ['venue.create'],
            'update_venue' => ['venue.update'],
            'manage_venue' => ['venue.manage'],
            
            'create_booking' => ['booking.create'],
            'manage_booking' => ['venue.booking.manage'],
            
            'manage_maintenance' => ['venue.maintenance.manage'],
            'manage_housekeeping' => ['housekeeping.manage'],
            
            'view_events' => ['event.view'],
            'manage_events' => ['event.manage'],
            
            'manage_transport' => ['transport.manage'],
            'view_transport' => ['event.view', 'transport.manage'],
            
            'manage_accommodation' => ['accommodation.manage'],
            'view_accommodation' => ['event.view', 'accommodation.manage'],
            
            'manage_finance' => ['finance.manage'],
        ];

        return $map[$action] ?? [];
    }

    /**
     * Check if user has any of the required permissions for an action.
     *
     * @param int $userId User ID
     * @param string $action Action name to check
     * @return bool Whether user has permission
     */
    public static function hasAny(int $userId, string $action): bool
    {
        $perms = self::getPermissions($action);
        if (empty($perms)) return false;

        // Note: In real application, this would call Member 1's authorization service.
        // Assuming $user->hasAnyPermission($perms) exists in Member 1 code.
        return true; 
    }
}
