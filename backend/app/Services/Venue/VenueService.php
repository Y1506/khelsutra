<?php

namespace App\Services\Venue;

use App\Services\BaseService;
use App\Services\Audit\AuditLogService;
use PDO;
use Exception;

class VenueService extends BaseService
{
    protected AuditLogService $auditLog;

    public function __construct(?PDO $pdo = null, ?AuditLogService $auditLog = null)
    {
        parent::__construct($pdo);
        $this->auditLog = $auditLog ?? new AuditLogService($this->pdo);
    }

    public function listVenues(int $organizationId, int $page = 1, int $limit = 15, ?string $search = null, ?string $status = null): array
    {
        if (!$this->pdo) {
            return ['data' => [], 'total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 0];
        }

        $conditions = ["v.organization_id = :org_id", "v.deleted_at IS NULL"];
        $params = [':org_id' => $organizationId];

        if (!empty($search)) {
            $conditions[] = "(v.name LIKE :search OR v.venue_code LIKE :search OR v.city LIKE :search OR v.venue_type LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        if (!empty($status)) {
            $conditions[] = "v.status = :status";
            $params[':status'] = $status;
        }

        $whereClause = implode(' AND ', $conditions);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM venues v WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $limit;

        $sql = "
            SELECT 
                v.*,
                COUNT(DISTINCT vf.id) as facility_count,
                COUNT(DISTINCT vb.id) as booking_count
            FROM venues v
            LEFT JOIN venue_facilities vf ON v.id = vf.venue_id AND vf.deleted_at IS NULL
            LEFT JOIN venue_bookings vb ON v.id = vb.venue_id AND vb.deleted_at IS NULL
            WHERE {$whereClause}
            GROUP BY v.id
            ORDER BY v.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $venues = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'data' => $venues,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / max(1, $limit)),
        ];
    }

    public function getVenue(int $organizationId, int $id): ?array
    {
        if (!$this->pdo) return null;

        $stmt = $this->pdo->prepare("
            SELECT * FROM venues
            WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':org_id' => $organizationId]);
        $venue = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$venue) return null;

        // Facilities
        $facStmt = $this->pdo->prepare("
            SELECT * FROM venue_facilities
            WHERE venue_id = :v_id AND organization_id = :org_id AND deleted_at IS NULL
            ORDER BY name ASC
        ");
        $facStmt->execute([':v_id' => $id, ':org_id' => $organizationId]);
        $venue['facilities'] = $facStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Recent & Upcoming Bookings
        $bookStmt = $this->pdo->prepare("
            SELECT vb.*, vf.name as facility_name, t.name as team_name
            FROM venue_bookings vb
            LEFT JOIN venue_facilities vf ON vb.facility_id = vf.id
            LEFT JOIN teams t ON vb.team_id = t.id
            WHERE vb.venue_id = :v_id AND vb.organization_id = :org_id AND vb.deleted_at IS NULL
            ORDER BY vb.booking_date DESC, vb.start_time ASC
            LIMIT 10
        ");
        $bookStmt->execute([':v_id' => $id, ':org_id' => $organizationId]);
        $venue['bookings'] = $bookStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Maintenance Logs
        $maintStmt = $this->pdo->prepare("
            SELECT vm.*, vf.name as facility_name
            FROM venue_maintenance vm
            LEFT JOIN venue_facilities vf ON vm.facility_id = vf.id
            WHERE vm.venue_id = :v_id AND vm.organization_id = :org_id AND vm.deleted_at IS NULL
            ORDER BY vm.scheduled_date DESC
            LIMIT 5
        ");
        $maintStmt->execute([':v_id' => $id, ':org_id' => $organizationId]);
        $venue['maintenance'] = $maintStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $venue;
    }

    public function createVenue(int $organizationId, array $data, ?int $performedBy = null): array
    {
        if (!$this->pdo) return [];

        $this->pdo->beginTransaction();
        try {
            $code = $data['venue_code'] ?? ('VEN-' . date('Y') . '-' . strtoupper(substr(uniqid(), -4)));

            $sql = "
                INSERT INTO venues (
                    organization_id, venue_code, name, venue_type, description,
                    address_line1, city, state, country, postal_code,
                    capacity, opening_time, closing_time, status, created_at, updated_at
                ) VALUES (
                    :org_id, :code, :name, :type, :desc,
                    :addr, :city, :state, 'India', :zip,
                    :cap, :open, :close, :status, NOW(), NOW()
                )
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':org_id' => $organizationId,
                ':code' => $code,
                ':name' => trim($data['name'] ?? ''),
                ':type' => $data['venue_type'] ?? 'Sports Complex',
                ':desc' => $data['description'] ?? null,
                ':addr' => $data['address_line1'] ?? null,
                ':city' => $data['city'] ?? 'Mumbai',
                ':state' => $data['state'] ?? 'Maharashtra',
                ':zip' => $data['postal_code'] ?? null,
                ':cap' => !empty($data['capacity']) ? (int)$data['capacity'] : null,
                ':open' => $data['opening_time'] ?? '06:00:00',
                ':close' => $data['closing_time'] ?? '22:00:00',
                ':status' => $data['status'] ?? 'active',
            ]);

            $venueId = (int)$this->pdo->lastInsertId();

            // Optional initial facilities (handles both string and array for backward compatibility)
            if (!empty($data['facility_name'])) {
                $fNames = (array)$data['facility_name'];
                $fTypes = (array)($data['facility_type'] ?? []);
                $fCaps  = (array)($data['facility_capacity'] ?? []);

                $facSql = "
                    INSERT INTO venue_facilities (organization_id, venue_id, name, facility_type, capacity, status, created_at, updated_at)
                    VALUES (:org_id, :v_id, :name, :type, :cap, 'active', NOW(), NOW())
                ";
                $fStmt = $this->pdo->prepare($facSql);

                foreach ($fNames as $index => $name) {
                    $name = trim($name);
                    if (empty($name)) continue;

                    $type = trim($fTypes[$index] ?? 'Main Field');
                    $cap = !empty($fCaps[$index]) ? (int)$fCaps[$index] : null;

                    $fStmt->execute([
                        ':org_id' => $organizationId,
                        ':v_id' => $venueId,
                        ':name' => $name,
                        ':type' => $type,
                        ':cap' => $cap
                    ]);
                    
                    $facilityId = (int)$this->pdo->lastInsertId();
                    
                    // Add sports for this facility
                    $sportsKey = "facility_sports_{$index}";
                    if (isset($data[$sportsKey]) && is_array($data[$sportsKey])) {
                        $sportSql = "INSERT INTO facility_sports (organization_id, facility_id, sport_id, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())";
                        $spStmt = $this->pdo->prepare($sportSql);
                        foreach ($data[$sportsKey] as $spId) {
                            $spId = (int)$spId;
                            if ($spId > 0) {
                                // Ignore duplicate key errors if a sport is selected twice
                                try {
                                    $spStmt->execute([$organizationId, $facilityId, $spId]);
                                } catch (\Exception $e) {}
                            }
                        }
                    }
                }
            }

            $this->pdo->commit();

            $this->auditLog->log(
                $organizationId,
                $performedBy,
                'VENUE_CREATE',
                'Venues',
                'venues',
                $venueId,
                null,
                ['code' => $code, 'name' => $data['name'] ?? ''],
                "Created venue {$data['name']} ({$code})"
            );

            return [
                'id' => $venueId,
                'venue_code' => $code,
                'name' => $data['name'] ?? ''
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function updateVenue(int $organizationId, int $id, array $data, ?int $performedBy = null): bool
    {
        if (!$this->pdo) return false;

        $existing = $this->getVenue($organizationId, $id);
        if (!$existing) return false;

        $sql = "
            UPDATE venues SET
                name = :name,
                venue_type = :type,
                description = :desc,
                address_line1 = :addr,
                city = :city,
                state = :state,
                postal_code = :zip,
                capacity = :cap,
                opening_time = :open,
                closing_time = :close,
                status = :status,
                updated_at = NOW()
            WHERE id = :id AND organization_id = :org_id
        ";

        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute([
            ':name' => trim($data['name'] ?? $existing['name']),
            ':type' => $data['venue_type'] ?? $existing['venue_type'],
            ':desc' => $data['description'] ?? $existing['description'],
            ':addr' => $data['address_line1'] ?? $existing['address_line1'],
            ':city' => $data['city'] ?? $existing['city'],
            ':state' => $data['state'] ?? $existing['state'],
            ':zip' => $data['postal_code'] ?? $existing['postal_code'],
            ':cap' => !empty($data['capacity']) ? (int)$data['capacity'] : $existing['capacity'],
            ':open' => $data['opening_time'] ?? $existing['opening_time'],
            ':close' => $data['closing_time'] ?? $existing['closing_time'],
            ':status' => $data['status'] ?? $existing['status'],
            ':id' => $id,
            ':org_id' => $organizationId,
        ]);

        if ($ok) {
            $this->auditLog->log(
                $organizationId,
                $performedBy,
                'VENUE_UPDATE',
                'Venues',
                'venues',
                $id,
                $existing,
                $data,
                "Updated venue #{$id} ({$existing['name']})"
            );
        }

        return $ok;
    }

    public function createFacility(int $organizationId, int $venueId, array $data, ?int $performedBy = null): array
    {
        if (!$this->pdo) return [];

        $sql = "
            INSERT INTO venue_facilities (
                organization_id, venue_id, name, facility_type, description, capacity, status, created_at, updated_at
            ) VALUES (
                :org_id, :v_id, :name, :type, :desc, :cap, 'active', NOW(), NOW()
            )
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':org_id' => $organizationId,
            ':v_id' => $venueId,
            ':name' => trim($data['name'] ?? ''),
            ':type' => $data['facility_type'] ?? 'Court',
            ':desc' => $data['description'] ?? null,
            ':cap' => !empty($data['capacity']) ? (int)$data['capacity'] : null,
        ]);

        $facId = (int)$this->pdo->lastInsertId();

        $this->auditLog->log(
            $organizationId,
            $performedBy,
            'FACILITY_CREATE',
            'Venues',
            'venue_facilities',
            $facId,
            null,
            ['facility_id' => $facId, 'name' => $data['name'] ?? ''],
            "Created facility {$data['name']} under venue #{$venueId}"
        );

        return ['id' => $facId, 'name' => $data['name'] ?? ''];
    }

    public function createBookingsBatch(int $organizationId, array $data, ?int $performedBy = null): array
    {
        if (!$this->pdo) return [];

        $facilityIds = $data['facility_id'] ?? [];
        $bookingDates = $data['booking_date'] ?? [];
        $startTimes = $data['start_time'] ?? [];
        $endTimes = $data['end_time'] ?? [];
        $notes = $data['notes'] ?? [];

        if (!is_array($facilityIds)) {
            $facilityIds = [$facilityIds];
            $bookingDates = [$bookingDates];
            $startTimes = [$startTimes];
            $endTimes = [$endTimes];
            $notes = [$notes];
        }

        $createdBookings = [];
        $this->pdo->beginTransaction();
        try {
            foreach ($facilityIds as $index => $facId) {
                if (empty($facId)) continue;
                $slotData = $data;
                $slotData['facility_id'] = $facId;
                $slotData['booking_date'] = is_array($bookingDates) ? ($bookingDates[$index] ?? date('Y-m-d')) : $bookingDates;
                $slotData['start_time'] = is_array($startTimes) ? ($startTimes[$index] ?? '08:00:00') : $startTimes;
                $slotData['end_time'] = is_array($endTimes) ? ($endTimes[$index] ?? '10:00:00') : $endTimes;
                $slotData['notes'] = is_array($notes) ? ($notes[$index] ?? null) : $notes;
                
                // createBooking does not have its own beginTransaction, so it's safe to call here.
                // However, createBooking does an audit log which might assume autocommit if not careful, 
                // but since it's just an INSERT, it's fine within this transaction.
                $createdBookings[] = $this->createBooking($organizationId, $slotData, $performedBy);
            }
            $this->pdo->commit();
            return $createdBookings;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function createBooking(int $organizationId, array $data, ?int $performedBy = null): array
    {
        if (!$this->pdo) return [];

        $venueId = (int)($data['venue_id'] ?? 0);
        $facilityId = !empty($data['facility_id']) ? (int)$data['facility_id'] : null;
        $bookingDate = $data['booking_date'] ?? date('Y-m-d');
        $startTime = $data['start_time'] ?? '08:00:00';
        $endTime = $data['end_time'] ?? '10:00:00';

        if ($endTime <= $startTime) {
            throw new Exception("Booking end time ({$endTime}) must be strictly after start time ({$startTime}).");
        }

        // Service-level Time Conflict Check without changing database schema (Section 35)
        if ($facilityId) {
            $conflictStmt = $this->pdo->prepare("
                SELECT id, booking_reference, start_time, end_time 
                FROM venue_bookings 
                WHERE facility_id = :fac_id 
                  AND booking_date = :b_date 
                  AND status IN ('pending', 'approved') 
                  AND deleted_at IS NULL
                  AND (start_time < :end_time AND end_time > :start_time)
                LIMIT 1
            ");
            $conflictStmt->execute([
                ':fac_id' => $facilityId,
                ':b_date' => $bookingDate,
                ':start_time' => $startTime,
                ':end_time' => $endTime
            ]);
            $conflict = $conflictStmt->fetch(PDO::FETCH_ASSOC);

            if ($conflict) {
                throw new Exception("Time slot conflict: Facility is already booked ({$conflict['booking_reference']}) from {$conflict['start_time']} to {$conflict['end_time']} on {$bookingDate}.");
            }
        }

        // Check for conflicting fixtures at this venue/facility
        $fixtureConflict = $this->checkSchedulingConflict($organizationId, $venueId, $bookingDate, $startTime, $endTime, $facilityId);
        if ($fixtureConflict && str_contains($fixtureConflict, 'fixture')) {
            throw new Exception($fixtureConflict);
        }

        $ref = $data['booking_reference'] ?? ('BKG-' . date('Y') . '-' . strtoupper(substr(uniqid(), -4)));

        // Auto-approve only if the user has the booking management permission.
        // Otherwise, the booking starts as pending and must be reviewed.
        $sessionPermissions = $_SESSION['auth']['permissions'] ?? [];
        $canAutoApprove = in_array('venue.booking.manage', $sessionPermissions, true);
        $bookingStatus = $canAutoApprove ? 'approved' : 'pending';
        $approvedBy = $canAutoApprove ? $performedBy : null;

        $sql = "
            INSERT INTO venue_bookings (
                organization_id, venue_id, facility_id, booking_reference,
                booked_by_user_id, booking_type, purpose, team_id,
                booking_date, start_time, end_time, status, approved_by, approved_at, notes, created_at, updated_at
            ) VALUES (
                :org_id, :v_id, :fac_id, :ref,
                :user_id, :b_type, :purpose, :team_id,
                :b_date, :stime, :etime, :status, :appr_by, :appr_at, :notes, NOW(), NOW()
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':org_id' => $organizationId,
            ':v_id' => $venueId,
            ':fac_id' => $facilityId,
            ':ref' => $ref,
            ':user_id' => $performedBy,
            ':b_type' => $data['booking_type'] ?? 'Training',
            ':purpose' => $data['purpose'] ?? 'Squad Training Session',
            ':team_id' => !empty($data['team_id']) ? (int)$data['team_id'] : null,
            ':b_date' => $bookingDate,
            ':stime' => $startTime,
            ':etime' => $endTime,
            ':status' => $bookingStatus,
            ':appr_by' => $approvedBy,
            ':appr_at' => $canAutoApprove ? date('Y-m-d H:i:s') : null,
            ':notes' => $data['notes'] ?? null,
        ]);

        $bookingId = (int)$this->pdo->lastInsertId();

        $this->auditLog->log(
            $organizationId,
            $performedBy,
            'VENUE_BOOKING_CREATE',
            'Venues',
            'venue_bookings',
            $bookingId,
            null,
            ['reference' => $ref, 'facility_id' => $facilityId, 'date' => $bookingDate],
            "Created venue booking {$ref} for {$bookingDate} ({$startTime} - {$endTime})"
        );

        return [
            'id' => $bookingId,
            'booking_reference' => $ref,
            'booking_date' => $bookingDate
        ];
    }

    public function deleteVenue(int $organizationId, int $id, ?int $performedBy = null): bool
    {
        if (!$this->pdo) return false;

        $existing = $this->getVenue($organizationId, $id);
        if (!$existing) return false;

        $stmt = $this->pdo->prepare("UPDATE venues SET deleted_at = NOW() WHERE id = :id AND organization_id = :org_id");
        $stmt->execute([':id' => $id, ':org_id' => $organizationId]);

        $this->auditLog->log(
            $organizationId,
            $performedBy,
            'VENUE_DELETE',
            'Venues',
            'venues',
            $id,
            $existing,
            null,
            "Soft deleted venue #{$id}"
        );

        return true;
    }

    /**
     * Update venue status between active and inactive non-destructively
     */
    public function updateStatus(int $organizationId, int $id, string $status, ?int $performedBy = null): bool
    {
        if (!$this->pdo) return false;
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new \InvalidArgumentException("Invalid venue status: {$status}");
        }

        $existing = $this->getVenue($organizationId, $id);
        if (!$existing || !empty($existing['deleted_at'])) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            UPDATE venues 
            SET status = :status, updated_at = NOW() 
            WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
        ");
        $ok = $stmt->execute([
            ':status' => $status,
            ':id' => $id,
            ':org_id' => $organizationId,
        ]);

        if ($ok) {
            $this->auditLog->log(
                $organizationId,
                $performedBy,
                'VENUE_UPDATE',
                'Venues',
                'venues',
                $id,
                ['status' => $existing['status']],
                ['status' => $status],
                "Changed venue #{$id} ({$existing['name']}) status to {$status}"
            );
        }

        return $ok;
    }

    /**
     * Check scheduling conflict against both venue bookings and scheduled fixtures.
     * Respects existing model: venue, facility, booking date, start time, end time,
     * status rules, and soft delete. Server-side validation only.
     */
    public function checkSchedulingConflict(
        int $organizationId,
        int $venueId,
        string $date,
        string $startTime,
        ?string $endTime = null,
        ?int $facilityId = null,
        ?int $excludeFixtureId = null,
        ?int $excludeBookingId = null
    ): ?string {
        if (!$this->pdo) return null;

        if ($endTime !== null && $endTime !== '' && $endTime <= $startTime) {
            return "End time ({$endTime}) must be strictly after start time ({$startTime}).";
        }

        // 1. Check against active/approved venue bookings
        $bSql = "
            SELECT id, booking_reference, start_time, end_time, facility_id
            FROM venue_bookings
            WHERE organization_id = :org_id
              AND venue_id = :v_id
              AND booking_date = :b_date
              AND status IN ('pending', 'approved')
              AND deleted_at IS NULL
        ";
        $bParams = [
            ':org_id' => $organizationId,
            ':v_id' => $venueId,
            ':b_date' => $date
        ];

        if ($facilityId !== null && $facilityId > 0) {
            $bSql .= " AND (facility_id = :fac_id OR facility_id IS NULL)";
            $bParams[':fac_id'] = $facilityId;
        }

        if ($excludeBookingId !== null && $excludeBookingId > 0) {
            $bSql .= " AND id != :ex_b_id";
            $bParams[':ex_b_id'] = $excludeBookingId;
        }

        if ($endTime !== null && $endTime !== '') {
            $bSql .= " AND (start_time < :end_time AND end_time > :start_time)";
            $bParams[':start_time'] = $startTime;
            $bParams[':end_time'] = $endTime;
        } else {
            $bSql .= " AND (start_time <= :start_time AND end_time > :start_time)";
            $bParams[':start_time'] = $startTime;
        }

        $bSql .= " LIMIT 1";
        $bStmt = $this->pdo->prepare($bSql);
        $bStmt->execute($bParams);
        $bookingConflict = $bStmt->fetch(PDO::FETCH_ASSOC);

        if ($bookingConflict) {
            return "Time slot conflict: Venue is already booked ({$bookingConflict['booking_reference']}) from {$bookingConflict['start_time']} to {$bookingConflict['end_time']} on {$date}.";
        }

        // 2. Check against scheduled fixtures
        $fSql = "
            SELECT f.id, f.fixture_reference, f.scheduled_start_time, f.scheduled_end_time, f.facility_id
            FROM fixtures f
            JOIN tournaments t ON f.tournament_id = t.id
            WHERE f.organization_id = :org_id
              AND f.venue_id = :v_id
              AND f.scheduled_date = :s_date
              AND f.status NOT IN ('cancelled')
              AND f.deleted_at IS NULL
              AND t.deleted_at IS NULL
              AND t.status NOT IN ('cancelled')
        ";
        $fParams = [
            ':org_id' => $organizationId,
            ':v_id' => $venueId,
            ':s_date' => $date
        ];

        if ($facilityId !== null && $facilityId > 0) {
            $fSql .= " AND (f.facility_id = :fac_id OR f.facility_id IS NULL)";
            $fParams[':fac_id'] = $facilityId;
        }

        if ($excludeFixtureId !== null && $excludeFixtureId > 0) {
            $fSql .= " AND f.id != :ex_f_id";
            $fParams[':ex_f_id'] = $excludeFixtureId;
        }

        if ($endTime !== null && $endTime !== '') {
            $fSql .= " AND (
                (scheduled_end_time IS NOT NULL AND scheduled_start_time < :end_time AND scheduled_end_time > :start_time)
                OR (scheduled_end_time IS NULL AND scheduled_start_time >= :start_time AND scheduled_start_time < :end_time)
            )";
            $fParams[':start_time'] = $startTime;
            $fParams[':end_time'] = $endTime;
        } else {
            $fSql .= " AND (
                (scheduled_end_time IS NOT NULL AND scheduled_start_time <= :start_time AND scheduled_end_time > :start_time)
                OR (scheduled_end_time IS NULL AND scheduled_start_time = :start_time)
            )";
            $fParams[':start_time'] = $startTime;
        }

        $fSql .= " LIMIT 1";
        $fStmt = $this->pdo->prepare($fSql);
        $fStmt->execute($fParams);
        $fixtureConflict = $fStmt->fetch(PDO::FETCH_ASSOC);

        if ($fixtureConflict) {
            $timeInfo = $fixtureConflict['scheduled_start_time'];
            if (!empty($fixtureConflict['scheduled_end_time'])) {
                $timeInfo .= ' - ' . $fixtureConflict['scheduled_end_time'];
            }
            return "Time slot conflict: Venue already has a scheduled fixture ({$fixtureConflict['fixture_reference']}) at {$timeInfo} on {$date}.";
        }

        return null;
    }
}
