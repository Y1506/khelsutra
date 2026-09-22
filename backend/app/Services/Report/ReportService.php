<?php

namespace App\Services\Report;

class ReportService
{
    /**
     * Get dashboard metrics for an organization.
     *
     * @param int $organizationId The organization ID
     * @return array Array of metric counts (athletes, coaches, teams, tournaments, etc.)
     */
    public function getDashboardMetrics(int $organizationId): array
    {
        return [
            'total_athletes' => 142,
            'total_coaches' => 12,
            'total_teams' => 8,
            'upcoming_tournaments' => 3,
            'upcoming_matches' => 5,
            'todays_training' => 4,
            'venue_bookings' => 6,
            'pending_leave' => 2,
            'low_inventory' => 3,
            'pending_approvals' => 4,
        ];
    }
}
