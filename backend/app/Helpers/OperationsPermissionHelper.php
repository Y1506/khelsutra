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

    public static function hasAny(int $userId, string $action): bool
    {
        $perms = self::getPermissions($action);
        if (empty($perms)) return false;

        $user = $_SESSION['auth']['user'] ?? null;
        if (!$user) return false;

        // Super Admin (1) or Sports Administrator (2) have global access
        $roleId = isset($user['role_id']) ? (int)$user['role_id'] : ($_SESSION['auth']['role']['id'] ?? 0);
        if ($roleId === 1 || $roleId === 2) {
            return true;
        }

        $userPermissions = $_SESSION['auth']['permissions'] ?? [];
        
        // Check if the user has ANY of the required permissions
        foreach ($perms as $perm) {
            if (in_array($perm, $userPermissions, true)) {
                return true;
            }
        }

        return false;
    }
}
