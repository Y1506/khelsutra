<?php

namespace App\Services\Coach;

use App\Services\BaseService;
use App\Services\Audit\AuditLogService;
use PDO;

class CoachService extends BaseService
{
    protected AuditLogService $auditLog;

    public function __construct(?PDO $pdo = null, ?AuditLogService $auditLog = null)
    {
        parent::__construct($pdo);
        $this->auditLog = $auditLog ?? new AuditLogService($this->pdo);
    }

    public function listCoaches(int $organizationId, int $page = 1, int $limit = 15, ?string $search = null, ?string $specialization = null, ?string $status = null): array
    {
        if (!$this->pdo) {
            return ['data' => [], 'total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 0];
        }

        $conditions = ["e.organization_id = :org_id", "e.deleted_at IS NULL", "cp.deleted_at IS NULL"];
        $params = [':org_id' => $organizationId];

        if (!empty($search)) {
            $conditions[] = "(e.first_name LIKE :search OR e.last_name LIKE :search OR e.employee_code LIKE :search OR cp.coach_code LIKE :search OR cp.specialization LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        if (!empty($specialization)) {
            $conditions[] = "cp.specialization = :spec";
            $params[':spec'] = $specialization;
        }

        if (!empty($status)) {
            $conditions[] = "cp.status = :status";
            $params[':status'] = $status;
        }

        $whereClause = implode(' AND ', $conditions);

        // Count query
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT cp.id) 
            FROM coach_profiles cp
            JOIN employees e ON cp.employee_id = e.id
            WHERE {$whereClause}
        ");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $limit;

        // Fetch query with assigned teams
        $sql = "
            SELECT 
                cp.id as coach_profile_id,
                cp.coach_code,
                cp.specialization,
                cp.qualification,
                cp.experience_years,
                cp.status as coach_status,
                cp.joining_date as coach_joining_date,
                e.id as employee_id,
                e.employee_code,
                e.first_name,
                e.last_name,
                e.phone,
                e.email,
                e.designation,
                d.name as department_name,
                GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') as assigned_teams,
                COUNT(DISTINCT tc.team_id) as team_count
            FROM coach_profiles cp
            JOIN employees e ON cp.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN team_coaches tc ON cp.id = tc.coach_id
            LEFT JOIN teams t ON tc.team_id = t.id AND t.deleted_at IS NULL
            WHERE {$whereClause}
            GROUP BY cp.id, e.id, d.name
            ORDER BY cp.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $coaches = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'data' => $coaches,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / max(1, $limit)),
        ];
    }

    public function getCoach(int $organizationId, int $coachProfileId): ?array
    {
        if (!$this->pdo) return null;

        $stmt = $this->pdo->prepare("
            SELECT 
                cp.*,
                cp.id as coach_profile_id,
                cp.status as coach_status,
                e.id as employee_id,
                e.employee_code,
                e.first_name,
                e.middle_name,
                e.last_name,
                e.date_of_birth,
                e.gender,
                e.blood_group,
                e.phone,
                e.email,
                e.address_line1,
                e.city,
                e.state,
                e.postal_code,
                e.designation,
                e.employment_type,
                e.employment_status,
                e.emergency_contact_name,
                e.emergency_contact_phone,
                e.emergency_contact_relationship,
                e.department_id,
                d.name as department_name
            FROM coach_profiles cp
            JOIN employees e ON cp.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE cp.id = :id AND cp.organization_id = :org_id AND cp.deleted_at IS NULL AND e.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $coachProfileId, ':org_id' => $organizationId]);
        $coach = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$coach) return null;

        // Fetch assigned teams
        $teamStmt = $this->pdo->prepare("
            SELECT tc.*, t.name as team_name, t.sport_id, s.name as sport_name
            FROM team_coaches tc
            JOIN teams t ON tc.team_id = t.id AND t.deleted_at IS NULL
            LEFT JOIN sports s ON t.sport_id = s.id
            WHERE tc.coach_id = :coach_id AND tc.organization_id = :org_id
            ORDER BY tc.is_primary DESC, t.name ASC
        ");
        $teamStmt->execute([':coach_id' => $coachProfileId, ':org_id' => $organizationId]);
        $coach['teams'] = $teamStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Fetch recent training sessions
        $trainStmt = $this->pdo->prepare("
            SELECT ts.*, t.name as team_name, v.name as venue_name
            FROM training_sessions ts
            LEFT JOIN teams t ON ts.team_id = t.id
            LEFT JOIN venues v ON ts.venue_id = v.id
            WHERE ts.coach_id = :coach_id AND ts.organization_id = :org_id AND ts.deleted_at IS NULL
            ORDER BY ts.training_date DESC, ts.start_time DESC
            LIMIT 5
        ");
        $trainStmt->execute([':coach_id' => $coachProfileId, ':org_id' => $organizationId]);
        $coach['training_sessions'] = $trainStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Fetch employee documents
        $docStmt = $this->pdo->prepare("
            SELECT * FROM employee_documents
            WHERE employee_id = :emp_id AND organization_id = :org_id AND deleted_at IS NULL
            ORDER BY id DESC
        ");
        $docStmt->execute([':emp_id' => $coach['employee_id'], ':org_id' => $organizationId]);
        $coach['documents'] = $docStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $coach;
    }

    public function createCoach(int $organizationId, array $data, ?int $performedBy = null): array
    {
        if (empty(trim($data['first_name'] ?? ''))) {
            throw new \InvalidArgumentException('First name is required.');
        }
        if (empty(trim($data['last_name'] ?? ''))) {
            throw new \InvalidArgumentException('Last name is required.');
        }
        if (empty(trim($data['specialization'] ?? ''))) {
            throw new \InvalidArgumentException('Specialization is required.');
        }
        if (isset($data['date_of_birth']) && trim($data['date_of_birth']) === '') {
            throw new \InvalidArgumentException('Date of birth is required.');
        }
        if (isset($data['gender']) && trim($data['gender']) === '') {
            throw new \InvalidArgumentException('Gender is required.');
        }
        if (isset($data['designation']) && trim($data['designation']) === '') {
            throw new \InvalidArgumentException('Designation is required.');
        }
        if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive'], true)) {
            throw new \InvalidArgumentException('Valid coach status is required.');
        }

        if (!$this->pdo) return [];

        $this->pdo->beginTransaction();
        try {
            // 1. Create employee
            $empCode = $data['employee_code'] ?? ('EMP-' . date('Y') . '-' . strtoupper(substr(uniqid(), -4)));
            $coachCode = $data['coach_code'] ?? ('CCH-' . date('Y') . '-' . strtoupper(substr(uniqid(), -4)));

            $empSql = "
                INSERT INTO employees (
                    organization_id, employee_code, first_name, middle_name, last_name,
                    date_of_birth, gender, blood_group, phone, email, address_line1,
                    city, state, country, postal_code, department_id, designation,
                    joining_date, employment_type, employment_status, notes, created_at, updated_at
                ) VALUES (
                    :org_id, :emp_code, :first_name, :middle_name, :last_name,
                    :dob, :gender, :blood_group, :phone, :email, :addr,
                    :city, :state, 'India', :zip, :dept_id, :designation,
                    :joining_date, :employment_type, 'active', :notes, NOW(), NOW()
                )
            ";

            $empStmt = $this->pdo->prepare($empSql);
            $empStmt->execute([
                ':org_id' => $organizationId,
                ':emp_code' => $empCode,
                ':first_name' => trim($data['first_name'] ?? ''),
                ':middle_name' => trim($data['middle_name'] ?? ''),
                ':last_name' => trim($data['last_name'] ?? ''),
                ':dob' => !empty($data['date_of_birth']) ? $data['date_of_birth'] : '1985-01-01',
                ':gender' => $data['gender'] ?? 'not_specified',
                ':blood_group' => $data['blood_group'] ?? null,
                ':phone' => $data['phone'] ?? null,
                ':email' => $data['email'] ?? null,
                ':addr' => $data['address_line1'] ?? null,
                ':city' => $data['city'] ?? null,
                ':state' => $data['state'] ?? null,
                ':zip' => $data['postal_code'] ?? null,
                ':dept_id' => !empty($data['department_id']) ? (int)$data['department_id'] : null,
                ':designation' => $data['designation'] ?? 'Coach',
                ':joining_date' => !empty($data['joining_date']) ? $data['joining_date'] : date('Y-m-d'),
                ':employment_type' => $data['employment_type'] ?? 'full_time',
                ':notes' => $data['notes'] ?? null,
            ]);

            $employeeId = (int)$this->pdo->lastInsertId();

            // 2. Create coach_profiles
            $cchSql = "
                INSERT INTO coach_profiles (
                    organization_id, employee_id, coach_code, specialization,
                    qualification, certifications, experience_years, joining_date,
                    license_number, status, notes, created_at, updated_at
                ) VALUES (
                    :org_id, :emp_id, :code, :spec,
                    :qual, :cert, :exp, :joining,
                    :lic_num, :status, :notes, NOW(), NOW()
                )
            ";
            $cchStmt = $this->pdo->prepare($cchSql);
            $cchStmt->execute([
                ':org_id' => $organizationId,
                ':emp_id' => $employeeId,
                ':code' => $coachCode,
                ':spec' => $data['specialization'] ?? 'General Coaching',
                ':qual' => $data['qualification'] ?? null,
                ':cert' => $data['certifications'] ?? null,
                ':exp' => isset($data['experience_years']) ? (float)$data['experience_years'] : 0,
                ':joining' => !empty($data['joining_date']) ? $data['joining_date'] : date('Y-m-d'),
                ':lic_num' => $data['license_number'] ?? null,
                ':status' => $data['status'] ?? 'active',
                ':notes' => $data['notes'] ?? null,
            ]);

            $coachProfileId = (int)$this->pdo->lastInsertId();

            // 3. Optional Team Assignment
            if (!empty($data['team_id'])) {
                $tcSql = "
                    INSERT INTO team_coaches (organization_id, team_id, coach_id, coach_role, start_date, is_primary, created_at, updated_at)
                    VALUES (:org_id, :team_id, :coach_id, :role, CURDATE(), 1, NOW(), NOW())
                ";
                $tcStmt = $this->pdo->prepare($tcSql);
                $tcStmt->execute([
                    ':org_id' => $organizationId,
                    ':team_id' => (int)$data['team_id'],
                    ':coach_id' => $coachProfileId,
                    ':role' => $data['coach_role'] ?? 'head_coach'
                ]);
            }

            $this->pdo->commit();

            // 4. Audit Log
            $this->auditLog->log(
                $organizationId,
                $performedBy,
                'COACH_CREATE',
                'Coaching Staff',
                'coach_profiles',
                $coachProfileId,
                null,
                ['coach_code' => $coachCode, 'name' => ($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')],
                "Registered coach {$data['first_name']} {$data['last_name']} with code {$coachCode}"
            );

            return [
                'coach_profile_id' => $coachProfileId,
                'employee_id' => $employeeId,
                'coach_code' => $coachCode,
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? ''
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function updateCoach(int $organizationId, int $coachProfileId, array $data, ?int $performedBy = null): bool
    {
        if (!$this->pdo) return false;

        $existing = $this->getCoach($organizationId, $coachProfileId);
        if (!$existing) return false;

        // Ensure resulting coach record preserves all 7 required fields
        $finalFirstName = isset($data['first_name']) ? trim($data['first_name']) : trim($existing['first_name'] ?? '');
        $finalLastName = isset($data['last_name']) ? trim($data['last_name']) : trim($existing['last_name'] ?? '');
        $finalDob = isset($data['date_of_birth']) ? trim($data['date_of_birth']) : trim($existing['date_of_birth'] ?? '');
        $finalGender = isset($data['gender']) ? trim($data['gender']) : trim($existing['gender'] ?? '');
        $finalDesignation = isset($data['designation']) ? trim($data['designation']) : trim($existing['designation'] ?? '');
        $finalSpec = isset($data['specialization']) ? trim($data['specialization']) : trim($existing['specialization'] ?? '');
        $finalStatus = isset($data['status']) ? trim($data['status']) : trim($existing['coach_status'] ?? ($existing['status'] ?? ''));

        if (empty($finalFirstName)) {
            throw new \InvalidArgumentException('First name is required.');
        }
        if (empty($finalLastName)) {
            throw new \InvalidArgumentException('Last name is required.');
        }
        if (empty($finalDob)) {
            throw new \InvalidArgumentException('Date of birth is required.');
        }
        if (empty($finalGender)) {
            throw new \InvalidArgumentException('Gender is required.');
        }
        if (empty($finalDesignation)) {
            throw new \InvalidArgumentException('Designation is required.');
        }
        if (empty($finalSpec)) {
            throw new \InvalidArgumentException('Specialization is required.');
        }
        if (empty($finalStatus) || !in_array($finalStatus, ['active', 'inactive'], true)) {
            throw new \InvalidArgumentException('Valid coach status is required.');
        }

        $this->pdo->beginTransaction();
        try {
            $employeeId = (int)$existing['employee_id'];

            // 1. Update employee
            $empSql = "
                UPDATE employees SET
                    first_name = :first_name,
                    middle_name = :middle_name,
                    last_name = :last_name,
                    date_of_birth = :dob,
                    gender = :gender,
                    blood_group = :blood_group,
                    phone = :phone,
                    email = :email,
                    address_line1 = :addr,
                    city = :city,
                    state = :state,
                    postal_code = :zip,
                    department_id = :dept_id,
                    designation = :designation,
                    employment_type = :employment_type,
                    notes = :notes,
                    updated_at = NOW()
                WHERE id = :emp_id AND organization_id = :org_id
            ";
            $empStmt = $this->pdo->prepare($empSql);
            $empStmt->execute([
                ':first_name' => $data['first_name'] ?? $existing['first_name'],
                ':middle_name' => $data['middle_name'] ?? $existing['middle_name'],
                ':last_name' => $data['last_name'] ?? $existing['last_name'],
                ':dob' => $data['date_of_birth'] ?? $existing['date_of_birth'],
                ':gender' => $data['gender'] ?? $existing['gender'],
                ':blood_group' => $data['blood_group'] ?? $existing['blood_group'],
                ':phone' => $data['phone'] ?? $existing['phone'],
                ':email' => $data['email'] ?? $existing['email'],
                ':addr' => $data['address_line1'] ?? $existing['address_line1'],
                ':city' => $data['city'] ?? $existing['city'],
                ':state' => $data['state'] ?? $existing['state'],
                ':zip' => $data['postal_code'] ?? $existing['postal_code'],
                ':dept_id' => !empty($data['department_id']) ? (int)$data['department_id'] : ($existing['department_id'] ?? null),
                ':designation' => $data['designation'] ?? $existing['designation'],
                ':employment_type' => $data['employment_type'] ?? $existing['employment_type'],
                ':notes' => $data['notes'] ?? $existing['notes'],
                ':emp_id' => $employeeId,
                ':org_id' => $organizationId,
            ]);

            // 2. Update coach profile
            $cchSql = "
                UPDATE coach_profiles SET
                    specialization = :spec,
                    qualification = :qual,
                    certifications = :cert,
                    experience_years = :exp,
                    license_number = :lic_num,
                    status = :status,
                    notes = :notes,
                    updated_at = NOW()
                WHERE id = :id AND organization_id = :org_id
            ";
            $cchStmt = $this->pdo->prepare($cchSql);
            $cchStmt->execute([
                ':spec' => $data['specialization'] ?? $existing['specialization'],
                ':qual' => $data['qualification'] ?? $existing['qualification'],
                ':cert' => $data['certifications'] ?? $existing['certifications'],
                ':exp' => isset($data['experience_years']) ? (float)$data['experience_years'] : $existing['experience_years'],
                ':lic_num' => $data['license_number'] ?? $existing['license_number'],
                ':status' => $data['status'] ?? $existing['coach_status'],
                ':notes' => $data['notes'] ?? $existing['notes'],
                ':id' => $coachProfileId,
                ':org_id' => $organizationId,
            ]);

            // 3. Update team assignment if provided
            if (!empty($data['team_id'])) {
                $teamId = (int)$data['team_id'];
                $checkStmt = $this->pdo->prepare("SELECT id FROM team_coaches WHERE coach_id = :cch_id AND team_id = :t_id AND organization_id = :org_id");
                $checkStmt->execute([':cch_id' => $coachProfileId, ':t_id' => $teamId, ':org_id' => $organizationId]);
                if (!$checkStmt->fetch()) {
                    $tcStmt = $this->pdo->prepare("
                        INSERT INTO team_coaches (organization_id, team_id, coach_id, coach_role, start_date, is_primary, created_at, updated_at)
                        VALUES (:org_id, :team_id, :coach_id, :role, CURDATE(), 1, NOW(), NOW())
                    ");
                    $tcStmt->execute([
                        ':org_id' => $organizationId,
                        ':team_id' => $teamId,
                        ':coach_id' => $coachProfileId,
                        ':role' => $data['coach_role'] ?? 'head_coach'
                    ]);
                }
            }

            $this->pdo->commit();

            $this->auditLog->log(
                $organizationId,
                $performedBy,
                'COACH_UPDATE',
                'Coaching Staff',
                'coach_profiles',
                $coachProfileId,
                $existing,
                $data,
                "Updated coach #{$coachProfileId} ({$existing['first_name']} {$existing['last_name']})"
            );

            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Update coach status between active and inactive non-destructively
     */
    public function updateStatus(int $organizationId, int $coachProfileId, string $status, ?int $performedBy = null): bool
    {
        if (!$this->pdo) return false;
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new \InvalidArgumentException("Invalid coach status: {$status}");
        }

        $existing = $this->getCoach($organizationId, $coachProfileId);
        if (!$existing) return false;

        $stmt = $this->pdo->prepare("
            UPDATE coach_profiles 
            SET status = :status, updated_at = NOW() 
            WHERE id = :id AND organization_id = :org_id AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':status' => $status,
            ':id' => $coachProfileId,
            ':org_id' => $organizationId,
        ]);

        $this->auditLog->log(
            $organizationId,
            $performedBy,
            'COACH_STATUS_UPDATE',
            'Coaching Staff',
            'coach_profiles',
            $coachProfileId,
            ['status' => $existing['coach_status']],
            ['status' => $status],
            "Changed coach #{$coachProfileId} ({$existing['first_name']} {$existing['last_name']}) status to {$status}"
        );

        return true;
    }

    public function deleteCoach(int $organizationId, int $coachProfileId, ?int $performedBy = null): bool
    {
        if (!$this->pdo) return false;

        $existing = $this->getCoach($organizationId, $coachProfileId);
        if (!$existing) return false;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("UPDATE coach_profiles SET deleted_at = NOW() WHERE id = :id AND organization_id = :org_id");
            $stmt->execute([':id' => $coachProfileId, ':org_id' => $organizationId]);

            $stmtEmp = $this->pdo->prepare("UPDATE employees SET deleted_at = NOW() WHERE id = :id AND organization_id = :org_id");
            $stmtEmp->execute([':id' => $existing['employee_id'], ':org_id' => $organizationId]);

            $this->pdo->commit();

            $this->auditLog->log(
                $organizationId,
                $performedBy,
                'COACH_DELETE',
                'Coaching Staff',
                'coach_profiles',
                $coachProfileId,
                $existing,
                null,
                "Soft deleted coach #{$coachProfileId}"
            );

            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
