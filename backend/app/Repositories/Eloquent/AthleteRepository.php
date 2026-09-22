<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\AthleteRepositoryInterface;
use PDO;

class AthleteRepository implements AthleteRepositoryInterface
{
    protected ?PDO $pdo = null;

    /**
     * AthleteRepository constructor.
     *
     * @param PDO|null $pdo Database connection
     */
    public function __construct(?PDO $pdo = null)
    {
        if ($pdo) {
            $this->pdo = $pdo;
        } else {
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '3306');
            $db   = env('DB_DATABASE', 'khelsutra');
            $user = env('DB_USERNAME', 'root');
            $pass = env('DB_PASSWORD', '');
            try {
                $this->pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (\Exception $e) {
                $this->pdo = null;
            }
        }
    }

    /**
     * Get paginated athletes for an organization.
     *
     * @param int $organizationId Organization ID
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array List of athletes
     */
    public function getPaginated(int $organizationId, int $page = 1, int $limit = 15): array
    {
        if (!$this->pdo) return [];
        $offset = ($page - 1) * $limit;
        $stmt = $this->pdo->prepare("SELECT a.*, s.name as sport_name FROM athletes a LEFT JOIN sports s ON a.current_sport_id = s.id WHERE a.organization_id = :org_id AND a.deleted_at IS NULL ORDER BY a.id DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':org_id', $organizationId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Find an athlete by ID.
     *
     * @param int $organizationId Organization ID
     * @param int $id Athlete ID
     * @return array|null Athlete data or null if not found
     */
    public function findById(int $organizationId, int $id): ?array
    {
        if (!$this->pdo) return null;
        $stmt = $this->pdo->prepare("SELECT a.*, s.name as sport_name FROM athletes a LEFT JOIN sports s ON a.current_sport_id = s.id WHERE a.organization_id = :org_id AND a.id = :id AND a.deleted_at IS NULL");
        $stmt->bindValue(':org_id', $organizationId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $res = $stmt->fetch();
        return $res ?: null;
    }

    /**
     * Create a new athlete.
     *
     * @param array $data Athlete data
     * @return array Created athlete data with ID
     */
    public function create(array $data): array
    {
        if (!$this->pdo) return $data;
        $sql = "INSERT INTO athletes (organization_id, athlete_code, first_name, last_name, date_of_birth, gender, blood_group, current_sport_id, phone, email, status, created_at, updated_at) 
                VALUES (:org_id, :athlete_code, :first_name, :last_name, :dob, :gender, :blood_group, :sport_id, :phone, :email, 'active', NOW(), NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':org_id' => $data['organization_id'],
            ':athlete_code' => $data['athlete_code'] ?? 'ATH-' . time(),
            ':first_name' => $data['first_name'],
            ':last_name' => $data['last_name'] ?? null,
            ':dob' => $data['date_of_birth'],
            ':gender' => $data['gender'],
            ':blood_group' => $data['blood_group'] ?? null,
            ':sport_id' => $data['current_sport_id'] ?? $data['primary_sport_id'] ?? 1,
            ':phone' => $data['phone'] ?? null,
            ':email' => $data['email'] ?? null,
        ]);
        $data['id'] = (int)$this->pdo->lastInsertId();
        return $data;
    }

    /**
     * Update an athlete.
     *
     * @param int $organizationId Organization ID
     * @param int $id Athlete ID
     * @param array $data Updated athlete data
     * @return bool True on success
     */
    public function update(int $organizationId, int $id, array $data): bool
    {
        if (!$this->pdo) return false;
        $fields = [];
        $params = [':org_id' => $organizationId, ':id' => $id];
        foreach ($data as $key => $val) {
            if (in_array($key, ['first_name', 'last_name', 'phone', 'email', 'status', 'blood_group', 'current_sport_id'])) {
                $fields[] = "{$key} = :{$key}";
                $params[":{$key}"] = $val;
            }
        }
        if (empty($fields)) return false;
        $sql = "UPDATE athletes SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE organization_id = :org_id AND id = :id AND deleted_at IS NULL";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Soft delete an athlete.
     *
     * @param int $organizationId Organization ID
     * @param int $id Athlete ID
     * @return bool True on success
     */
    public function delete(int $organizationId, int $id): bool
    {
        if (!$this->pdo) return false;
        $stmt = $this->pdo->prepare("UPDATE athletes SET deleted_at = NOW() WHERE organization_id = :org_id AND id = :id");
        return $stmt->execute([':org_id' => $organizationId, ':id' => $id]);
    }
}
