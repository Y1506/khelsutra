<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\TeamRepositoryInterface;
use PDO;

class TeamRepository implements TeamRepositoryInterface
{
    protected ?PDO $pdo = null;

    /**
     * TeamRepository constructor.
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
     * Get paginated teams for an organization.
     *
     * @param int $organizationId Organization ID
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array List of teams
     */
    public function getPaginated(int $organizationId, int $page = 1, int $limit = 15): array
    {
        if (!$this->pdo) return [];
        $offset = ($page - 1) * $limit;
        $stmt = $this->pdo->prepare("SELECT t.*, s.name as sport_name FROM teams t LEFT JOIN sports s ON t.sport_id = s.id WHERE t.organization_id = :org_id AND t.deleted_at IS NULL ORDER BY t.id DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':org_id', $organizationId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Find a team by ID.
     *
     * @param int $organizationId Organization ID
     * @param int $id Team ID
     * @return array|null Team data or null if not found
     */
    public function findById(int $organizationId, int $id): ?array
    {
        if (!$this->pdo) return null;
        $stmt = $this->pdo->prepare("SELECT t.*, s.name as sport_name FROM teams t LEFT JOIN sports s ON t.sport_id = s.id WHERE t.organization_id = :org_id AND t.id = :id AND t.deleted_at IS NULL");
        $stmt->bindValue(':org_id', $organizationId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $res = $stmt->fetch();
        return $res ?: null;
    }

    /**
     * Create a new team.
     *
     * @param array $data Team data
     * @return array Created team data with ID
     */
    public function create(array $data): array
    {
        if (!$this->pdo) return $data;
        $stmt = $this->pdo->prepare("INSERT INTO teams (organization_id, sport_id, sport_category_id, name, short_name, gender, max_players, status, created_at, updated_at) 
                                     VALUES (:org_id, :sport_id, :cat_id, :name, :short_name, :gender, :max_players, 'active', NOW(), NOW())");
        $stmt->execute([
            ':org_id' => $data['organization_id'],
            ':sport_id' => $data['sport_id'],
            ':cat_id' => $data['sport_category_id'] ?? null,
            ':name' => $data['name'],
            ':short_name' => $data['short_name'] ?? null,
            ':gender' => $data['gender'] ?? 'open',
            ':max_players' => $data['max_players'] ?? null,
        ]);
        $data['id'] = (int)$this->pdo->lastInsertId();
        return $data;
    }
}
