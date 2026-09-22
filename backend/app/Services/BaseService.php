<?php

namespace App\Services;

use PDO;
use Exception;

/**
 * Base service class providing database connection management and transaction support.
 */
abstract class BaseService
{
    protected ?PDO $pdo = null;

    /**
     * Create a new service instance with database connection.
     *
     * @param PDO|null $pdo Optional PDO instance; if null, creates a new connection
     */
    public function __construct(?PDO $pdo = null)
    {
        if ($pdo) {
            $this->pdo = $pdo;
        } else {
            $this->pdo = self::getDatabaseConnection();
        }
    }

    /**
     * Get or create a singleton database connection.
     *
     * @return PDO|null The database connection or null on failure
     */
    public static function getDatabaseConnection(): ?PDO
    {
        static $instance = null;
        if ($instance === null) {
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '3306');
            $db   = env('DB_DATABASE', 'khelsutra');
            $user = env('DB_USERNAME', 'root');
            $pass = env('DB_PASSWORD', '');
            try {
                $instance = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (Exception $e) {
                $instance = null;
            }
        }
        return $instance;
    }

    /**
     * Get the PDO database connection instance.
     *
     * @return PDO|null The database connection
     */
    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    /**
     * Begin a database transaction.
     *
     * @return bool True on success, false on failure
     */
    public function beginTransaction(): bool
    {
        return $this->pdo && $this->pdo->beginTransaction();
    }

    /**
     * Commit the current database transaction.
     *
     * @return bool True on success, false on failure
     */
    public function commit(): bool
    {
        return $this->pdo && $this->pdo->commit();
    }

    /**
     * Roll back the current database transaction.
     *
     * @return bool True on success, false on failure
     */
    public function rollBack(): bool
    {
        return $this->pdo && $this->pdo->rollBack();
    }
}
