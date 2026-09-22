<?php

namespace App\Services\Attendance;

use App\Services\BaseService;
use App\Services\Audit\AuditLogService;
use PDO;

class AttendanceService extends BaseService
{
    protected AuditLogService $auditLog;

    public function __construct(?PDO $pdo = null, ?AuditLogService $auditLog = null)
    {
        parent::__construct($pdo);
        $this->auditLog = $auditLog ?? new AuditLogService($this->pdo);
    }

    /**
     * Record training attendance for a participant.
     *
     * @param int $orgId Organization ID
     * @param int $sessionId Training session ID
     * @param array $data Attendance data (athlete_id, coach_id, or employee_id, attendance_status, times, remarks)
     * @param int|null $performedBy User ID who performed the action
     * @return array|null Recorded attendance data or null if PDO unavailable
     * @throws \InvalidArgumentException If validation fails
     */
    public function recordTrainingAttendance(int $orgId, int $sessionId, array $data, ?int $performedBy = null): ?array
    {
        if (!$this->pdo) return null;

        $athleteId = !empty($data['athlete_id']) ? (int)$data['athlete_id'] : null;
        $coachId   = !empty($data['coach_id']) ? (int)$data['coach_id'] : null;
        $employeeId= !empty($data['employee_id']) ? (int)$data['employee_id'] : null;

        // Exactly one subject must be specified
        $subjectCount = ($athleteId !== null ? 1 : 0) + ($coachId !== null ? 1 : 0) + ($employeeId !== null ? 1 : 0);
        if ($subjectCount !== 1) {
            throw new \InvalidArgumentException("Exactly one attendance subject (athlete_id, coach_id, or employee_id) must be provided.");
        }

        $status = $data['attendance_status'] ?? 'present';
        $allowedStatuses = ['present', 'absent', 'late', 'excused'];
        if (!in_array($status, $allowedStatuses, true)) {
            throw new \InvalidArgumentException("Invalid attendance status: {$status}");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO training_attendance (
                organization_id, training_session_id, athlete_id, coach_id, employee_id,
                attendance_status, check_in_time, check_out_time, remarks, recorded_by,
                created_at, updated_at
            ) VALUES (
                :org_id, :session_id, :ath_id, :coach_id, :emp_id,
                :status, :cin, :cout, :remarks, :by,
                NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                attendance_status = :status2,
                check_in_time = :cin2,
                check_out_time = :cout2,
                remarks = :remarks2,
                recorded_by = :by2,
                updated_at = NOW()
        ");

        $cin = $data['check_in_time'] ?? null;
        $cout = $data['check_out_time'] ?? null;
        $remarks = $data['remarks'] ?? null;

        $stmt->execute([
            ':org_id' => $orgId,
            ':session_id' => $sessionId,
            ':ath_id' => $athleteId,
            ':coach_id' => $coachId,
            ':emp_id' => $employeeId,
            ':status' => $status,
            ':cin' => $cin,
            ':cout' => $cout,
            ':remarks' => $remarks,
            ':by' => $performedBy,
            ':status2' => $status,
            ':cin2' => $cin,
            ':cout2' => $cout,
            ':remarks2' => $remarks,
            ':by2' => $performedBy,
        ]);

        $this->auditLog->log($orgId, $performedBy, 'ATTENDANCE_RECORD', 'Attendance', 'training_attendance', $sessionId, null, $data, "Recorded training attendance ({$status}) for session #{$sessionId}");

        return [
            'training_session_id' => $sessionId,
            'athlete_id' => $athleteId,
            'coach_id' => $coachId,
            'employee_id' => $employeeId,
            'attendance_status' => $status,
            'check_in_time' => $cin,
            'check_out_time' => $cout,
        ];
    }

    /**
     * Record match attendance for a participant.
     *
     * @param int $orgId Organization ID
     * @param int $matchId Match ID
     * @param array $data Attendance data (athlete_id, coach_id, or employee_id, attendance_status, remarks)
     * @param int|null $performedBy User ID who performed the action
     * @return array|null Recorded attendance data or null if PDO unavailable
     * @throws \InvalidArgumentException If validation fails
     */
    public function recordMatchAttendance(int $orgId, int $matchId, array $data, ?int $performedBy = null): ?array
    {
        if (!$this->pdo) return null;

        $athleteId = !empty($data['athlete_id']) ? (int)$data['athlete_id'] : null;
        $coachId   = !empty($data['coach_id']) ? (int)$data['coach_id'] : null;
        $employeeId= !empty($data['employee_id']) ? (int)$data['employee_id'] : null;

        // Exactly one subject must be specified
        $subjectCount = ($athleteId !== null ? 1 : 0) + ($coachId !== null ? 1 : 0) + ($employeeId !== null ? 1 : 0);
        if ($subjectCount !== 1) {
            throw new \InvalidArgumentException("Exactly one attendance subject (athlete_id, coach_id, or employee_id) must be provided.");
        }

        $status = $data['attendance_status'] ?? 'present';
        $allowedStatuses = ['present', 'absent', 'late', 'excused'];
        if (!in_array($status, $allowedStatuses, true)) {
            throw new \InvalidArgumentException("Invalid attendance status: {$status}");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO match_attendance (
                organization_id, match_id, athlete_id, coach_id, employee_id,
                attendance_status, remarks, recorded_by,
                created_at, updated_at
            ) VALUES (
                :org_id, :match_id, :ath_id, :coach_id, :emp_id,
                :status, :remarks, :by,
                NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                attendance_status = :status2,
                remarks = :remarks2,
                recorded_by = :by2,
                updated_at = NOW()
        ");

        $remarks = $data['remarks'] ?? null;

        $stmt->execute([
            ':org_id' => $orgId,
            ':match_id' => $matchId,
            ':ath_id' => $athleteId,
            ':coach_id' => $coachId,
            ':emp_id' => $employeeId,
            ':status' => $status,
            ':remarks' => $remarks,
            ':by' => $performedBy,
            ':status2' => $status,
            ':remarks2' => $remarks,
            ':by2' => $performedBy,
        ]);

        $this->auditLog->log($orgId, $performedBy, 'ATTENDANCE_RECORD', 'Attendance', 'match_attendance', $matchId, null, $data, "Recorded match attendance ({$status}) for match #{$matchId}");

        return [
            'match_id' => $matchId,
            'athlete_id' => $athleteId,
            'coach_id' => $coachId,
            'employee_id' => $employeeId,
            'attendance_status' => $status,
        ];
    }

    /**
     * Get training attendance history with optional filtering.
     *
     * @param int $orgId Organization ID
     * @param int|null $sessionId Optional training session ID to filter by
     * @param int $limit Maximum records to return
     * @return array Attendance history records
     */
    public function getTrainingAttendanceHistory(int $orgId, ?int $sessionId = null, int $limit = 50): array
    {
        if (!$this->pdo) return [];
        $sql = "
            SELECT ta.*, 
                   CONCAT(a.first_name, ' ', a.last_name) as athlete_name, a.athlete_code,
                   cp.coach_code,
                   CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.employee_code,
                   ts.title as session_title, ts.training_date
            FROM training_attendance ta
            LEFT JOIN athletes a ON ta.athlete_id = a.id
            LEFT JOIN coach_profiles cp ON ta.coach_id = cp.id
            LEFT JOIN employees e ON ta.employee_id = e.id
            LEFT JOIN training_sessions ts ON ta.training_session_id = ts.id
            WHERE ta.organization_id = :org_id
        ";
        $params = [':org_id' => $orgId];
        if ($sessionId) {
            $sql .= " AND ta.training_session_id = :sid ";
            $params[':sid'] = $sessionId;
        }
        $sql .= " ORDER BY ta.id DESC LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Mark training attendance (convenience wrapper).
     *
     * @param int $orgId Organization ID
     * @param array $data Attendance data including training_session_id
     * @param int|null $performedBy User ID who performed the action
     * @return array|null Recorded attendance with ID
     */
    public function markTrainingAttendance(int $orgId, array $data, ?int $performedBy = null): ?array
    {
        $sessionId = (int)($data['training_session_id'] ?? 1);
        $res = $this->recordTrainingAttendance($orgId, $sessionId, $data, $performedBy);
        if ($res) {
            $res['id'] = $this->pdo->lastInsertId() ?: 1;
        }
        return $res;
    }

    /**
     * Mark match attendance (convenience wrapper).
     *
     * @param int $orgId Organization ID
     * @param array $data Attendance data including match_id
     * @param int|null $performedBy User ID who performed the action
     * @return array|null Recorded attendance with ID
     */
    public function markMatchAttendance(int $orgId, array $data, ?int $performedBy = null): ?array
    {
        $matchId = (int)($data['match_id'] ?? 1);
        $res = $this->recordMatchAttendance($orgId, $matchId, $data, $performedBy);
        if ($res) {
            $res['id'] = $this->pdo->lastInsertId() ?: 1;
        }
        return $res;
    }

    /**
     * List training attendance records with filters.
     *
     * @param int $orgId Organization ID
     * @param array $filters Filter parameters (training_session_id)
     * @return array Attendance records
     */
    public function listTrainingAttendance(int $orgId, array $filters = []): array
    {
        $sessionId = !empty($filters['training_session_id']) ? (int)$filters['training_session_id'] : null;
        return $this->getTrainingAttendanceHistory($orgId, $sessionId);
    }

    /**
     * List match attendance records with filters.
     *
     * @param int $orgId Organization ID
     * @param array $filters Filter parameters
     * @return array Attendance records
     */
    public function listMatchAttendance(int $orgId, array $filters = []): array
    {
        if (!$this->pdo) return [];
        $stmt = $this->pdo->prepare("
            SELECT ma.*, CONCAT(e.first_name, ' ', e.last_name) as participant_name
            FROM match_attendance ma
            LEFT JOIN employees e ON ma.employee_id = e.id
            WHERE ma.organization_id = :org_id
            ORDER BY ma.id DESC LIMIT 50
        ");
        $stmt->execute([':org_id' => $orgId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
