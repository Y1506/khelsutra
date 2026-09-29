<?php

namespace App\Services\Training;

use App\Services\BaseService;
use App\Services\Audit\AuditLogService;
use PDO;

class TrainingService extends BaseService
{
    protected AuditLogService $auditLog;

    public function __construct(?PDO $pdo = null, ?AuditLogService $auditLog = null)
    {
        parent::__construct($pdo);
        $this->auditLog = $auditLog ?? new AuditLogService($this->pdo);
    }

    public function listSessions(int $organizationId, int $page = 1, int $limit = 15, ?string $search = null, ?string $date = null, ?string $status = null, ?int $teamId = null): array
    {
        if (!$this->pdo) {
            return ['data' => [], 'total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 0];
        }

        $conditions = ["ts.organization_id = :org_id", "ts.deleted_at IS NULL"];
        $params = [':org_id' => $organizationId];

        if (!empty($search)) {
            $conditions[] = "(ts.title LIKE :search OR ts.training_reference LIKE :search OR ts.training_type LIKE :search OR t.name LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        if (!empty($date)) {
            $conditions[] = "ts.training_date = :date";
            $params[':date'] = $date;
        }

        if (!empty($status)) {
            $conditions[] = "ts.status = :status";
            $params[':status'] = $status;
        }

        if (!empty($teamId)) {
            $conditions[] = "ts.team_id = :team_id";
            $params[':team_id'] = $teamId;
        }

        $whereClause = implode(' AND ', $conditions);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM training_sessions ts LEFT JOIN teams t ON ts.team_id = t.id WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $limit;

        $sql = "
            SELECT 
                ts.*,
                t.name as team_name,
                s.name as sport_name,
                v.name as venue_name,
                vf.name as facility_name,
                CONCAT(e.first_name, ' ', e.last_name) as coach_name,
                COUNT(DISTINCT ta.id) as total_roster_count,
                SUM(CASE WHEN ta.attendance_status = 'present' THEN 1 ELSE 0 END) as present_count
            FROM training_sessions ts
            LEFT JOIN teams t ON ts.team_id = t.id
            LEFT JOIN sports s ON t.sport_id = s.id
            LEFT JOIN venues v ON ts.venue_id = v.id
            LEFT JOIN venue_facilities vf ON ts.facility_id = vf.id
            LEFT JOIN coach_profiles cp ON ts.coach_id = cp.id
            LEFT JOIN employees e ON cp.employee_id = e.id
            LEFT JOIN training_attendance ta ON ts.id = ta.training_session_id
            WHERE {$whereClause}
            GROUP BY ts.id, t.name, s.name, v.name, vf.name, e.first_name, e.last_name
            ORDER BY ts.training_date DESC, ts.start_time DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'data' => $sessions,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / max(1, $limit)),
        ];
    }

    public function getSession(int $organizationId, int $id): ?array
    {
        if (!$this->pdo) return null;

        $stmt = $this->pdo->prepare("
            SELECT 
                ts.*,
                t.name as team_name,
                t.sport_id,
                s.name as sport_name,
                v.name as venue_name,
                vf.name as facility_name,
                CONCAT(e.first_name, ' ', e.last_name) as coach_name,
                cp.coach_code,
                e.phone as coach_phone
            FROM training_sessions ts
            LEFT JOIN teams t ON ts.team_id = t.id
            LEFT JOIN sports s ON t.sport_id = s.id
            LEFT JOIN venues v ON ts.venue_id = v.id
            LEFT JOIN venue_facilities vf ON ts.facility_id = vf.id
            LEFT JOIN coach_profiles cp ON ts.coach_id = cp.id
            LEFT JOIN employees e ON cp.employee_id = e.id
            WHERE ts.id = :id AND ts.organization_id = :org_id AND ts.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':org_id' => $organizationId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) return null;

        // Fetch roster athletes with attendance status
        $athStmt = $this->pdo->prepare("
            SELECT 
                a.id as athlete_id,
                a.athlete_code,
                a.first_name,
                a.last_name,
                tm.jersey_number,
                tm.member_role,
                ta.id as attendance_id,
                COALESCE(ta.attendance_status, 'present') as attendance_status,
                ta.check_in_time,
                ta.remarks
            FROM team_members tm
            JOIN athletes a ON tm.athlete_id = a.id AND a.deleted_at IS NULL
            LEFT JOIN training_attendance ta ON ta.training_session_id = :session_id AND ta.athlete_id = a.id
            WHERE tm.team_id = :team_id AND tm.organization_id = :org_id AND tm.is_current = 1
            ORDER BY tm.jersey_number ASC, a.first_name ASC
        ");
        $athStmt->execute([
            ':session_id' => $id,
            ':team_id' => $session['team_id'],
            ':org_id' => $organizationId
        ]);
        $session['roster_attendance'] = $athStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $session;
    }

    public function createSession(int $organizationId, array $data, ?int $performedBy = null): array
    {
        if (!$this->pdo) return [];

        $this->pdo->beginTransaction();
        try {
            $ref = $data['training_reference'] ?? ('TRN-' . date('Y') . '-' . strtoupper(substr(uniqid(), -4)));

            $sql = "
                INSERT INTO training_sessions (
                    organization_id, training_reference, team_id, coach_id, venue_id,
                    facility_id, training_type, title, objectives, training_date,
                    start_time, end_time, status, notes, created_at, updated_at
                ) VALUES (
                    :org_id, :ref, :team_id, :coach_id, :venue_id,
                    :fac_id, :type, :title, :obj, :tdate,
                    :stime, :etime, :status, :notes, NOW(), NOW()
                )
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':org_id' => $organizationId,
                ':ref' => $ref,
                ':team_id' => !empty($data['team_id']) ? (int)$data['team_id'] : null,
                ':coach_id' => !empty($data['coach_id']) ? (int)$data['coach_id'] : null,
                ':venue_id' => !empty($data['venue_id']) ? (int)$data['venue_id'] : null,
                ':fac_id' => !empty($data['facility_id']) ? (int)$data['facility_id'] : null,
                ':type' => $data['training_type'] ?? 'Tactical Drill',
                ':title' => trim($data['title'] ?? 'Scheduled Training Session'),
                ':obj' => $data['objectives'] ?? null,
                ':tdate' => $data['training_date'] ?? date('Y-m-d'),
                ':stime' => $data['start_time'] ?? '08:00:00',
                ':etime' => $data['end_time'] ?? '10:00:00',
                ':status' => $data['status'] ?? 'scheduled',
                ':notes' => $data['notes'] ?? null,
            ]);

            $sessionId = (int)$this->pdo->lastInsertId();

            // Populate initial attendance for team members
            if (!empty($data['team_id'])) {
                $tmStmt = $this->pdo->prepare("SELECT athlete_id FROM team_members WHERE team_id = :t_id AND organization_id = :org_id AND is_current = 1");
                $tmStmt->execute([':t_id' => (int)$data['team_id'], ':org_id' => $organizationId]);
                $athletes = $tmStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

                if (!empty($athletes)) {
                    $attSql = "
                        INSERT INTO training_attendance (organization_id, training_session_id, athlete_id, attendance_status, recorded_by, created_at, updated_at)
                        VALUES (:org_id, :session_id, :ath_id, 'present', :recorded_by, NOW(), NOW())
                    ";
                    $attStmt = $this->pdo->prepare($attSql);
                    foreach ($athletes as $athId) {
                        $attStmt->execute([
                            ':org_id' => $organizationId,
                            ':session_id' => $sessionId,
                            ':ath_id' => $athId,
                            ':recorded_by' => $performedBy
                        ]);
                    }
                }
            }

            $this->pdo->commit();

            $this->auditLog->log(
                $organizationId,
                $performedBy,
                'TRAINING_CREATE',
                'Training Sessions',
                'training_sessions',
                $sessionId,
                null,
                ['reference' => $ref, 'title' => $data['title'] ?? ''],
                "Scheduled training session {$ref} on {$data['training_date']}"
            );

            return [
                'id' => $sessionId,
                'training_reference' => $ref,
                'title' => $data['title'] ?? ''
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function updateSession(int $organizationId, int $id, array $data, ?int $performedBy = null): bool
    {
        if (!$this->pdo) return false;

        $existing = $this->getSession($organizationId, $id);
        if (!$existing) return false;

        $sql = "
            UPDATE training_sessions SET
                title = :title,
                training_type = :type,
                coach_id = :coach_id,
                venue_id = :venue_id,
                facility_id = :fac_id,
                objectives = :obj,
                training_date = :tdate,
                start_time = :stime,
                end_time = :etime,
                status = :status,
                notes = :notes,
                updated_at = NOW()
            WHERE id = :id AND organization_id = :org_id
        ";

        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute([
            ':title' => trim($data['title'] ?? $existing['title']),
            ':type' => $data['training_type'] ?? $existing['training_type'],
            ':coach_id' => !empty($data['coach_id']) ? (int)$data['coach_id'] : $existing['coach_id'],
            ':venue_id' => !empty($data['venue_id']) ? (int)$data['venue_id'] : $existing['venue_id'],
            ':fac_id' => !empty($data['facility_id']) ? (int)$data['facility_id'] : $existing['facility_id'],
            ':obj' => $data['objectives'] ?? $existing['objectives'],
            ':tdate' => $data['training_date'] ?? $existing['training_date'],
            ':stime' => $data['start_time'] ?? $existing['start_time'],
            ':etime' => $data['end_time'] ?? $existing['end_time'],
            ':status' => $data['status'] ?? $existing['status'],
            ':notes' => $data['notes'] ?? $existing['notes'],
            ':id' => $id,
            ':org_id' => $organizationId,
        ]);

        if ($ok) {
            $this->auditLog->log(
                $organizationId,
                $performedBy,
                'TRAINING_UPDATE',
                'Training Sessions',
                'training_sessions',
                $id,
                $existing,
                $data,
                "Updated training session #{$id}"
            );
        }

        return $ok;
    }

    public function recordAttendance(int $organizationId, int $sessionId, array $attendanceData, ?int $performedBy = null): bool
    {
        if (!$this->pdo) return false;

        $this->pdo->beginTransaction();
        try {
            foreach ($attendanceData as $athleteId => $status) {
                $athleteId = (int)$athleteId;
                $status = in_array($status, ['present', 'absent', 'late', 'excused']) ? $status : 'present';

                $checkStmt = $this->pdo->prepare("SELECT id FROM training_attendance WHERE training_session_id = :s_id AND athlete_id = :a_id AND organization_id = :org_id");
                $checkStmt->execute([':s_id' => $sessionId, ':a_id' => $athleteId, ':org_id' => $organizationId]);
                $existingId = $checkStmt->fetchColumn();

                if ($existingId) {
                    $upStmt = $this->pdo->prepare("UPDATE training_attendance SET attendance_status = :status, updated_at = NOW() WHERE id = :id");
                    $upStmt->execute([':status' => $status, ':id' => $existingId]);
                } else {
                    $inStmt = $this->pdo->prepare("INSERT INTO training_attendance (organization_id, training_session_id, athlete_id, attendance_status, recorded_by, created_at, updated_at) VALUES (:org_id, :s_id, :a_id, :status, :rec_by, NOW(), NOW())");
                    $inStmt->execute([
                        ':org_id' => $organizationId,
                        ':s_id' => $sessionId,
                        ':a_id' => $athleteId,
                        ':status' => $status,
                        ':rec_by' => $performedBy
                    ]);
                }
            }

            $this->pdo->commit();

            $this->auditLog->log(
                $organizationId,
                $performedBy,
                'TRAINING_ATTENDANCE',
                'Training Sessions',
                'training_attendance',
                $sessionId,
                null,
                ['session_id' => $sessionId, 'records_count' => count($attendanceData)],
                "Updated attendance for training session #{$sessionId}"
            );

            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function deleteSession(int $organizationId, int $id, ?int $performedBy = null): bool
    {
        if (!$this->pdo) return false;

        $existing = $this->getSession($organizationId, $id);
        if (!$existing) return false;

        $stmt = $this->pdo->prepare("UPDATE training_sessions SET deleted_at = NOW() WHERE id = :id AND organization_id = :org_id");
        $stmt->execute([':id' => $id, ':org_id' => $organizationId]);

        $this->auditLog->log(
            $organizationId,
            $performedBy,
            'TRAINING_DELETE',
            'Training Sessions',
            'training_sessions',
            $id,
            $existing,
            null,
            "Soft deleted training session #{$id}"
        );

        return true;
    }
}
