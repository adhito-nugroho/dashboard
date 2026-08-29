<?php
/**
 * Read-Only Database Connection Configuration for db_surat_tugas
 * 
 * Hanya digunakan untuk query SELECT (read-only) ke sistem Surat Tugas.
 * Tidak pernah menjalankan INSERT/UPDATE/DELETE.
 */

class DatabaseSuratTugas {
    private static ?PDO $connection = null;
    private static bool $connectionAttempted = false;

    /**
     * Get read-only PDO connection to db_surat_tugas
     * Returns null if connection fails (graceful degradation)
     */
    public static function getConnection(): ?PDO {
        if (!self::$connectionAttempted) {
            self::$connectionAttempted = true;
            self::$connection = self::createConnection();
        }
        
        return self::$connection;
    }

    /**
     * Cek apakah koneksi ke db_surat_tugas aktif dan dapat diakses
     */
    public static function isAvailable(): bool {
        return self::getConnection() !== null;
    }

    private static function createConnection(): ?PDO {
        try {
            // Load environment variables khusus db_surat_tugas atau fallback ke default MySQL
            $host = $_ENV['ST_DB_HOST'] ?? getenv('ST_DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost');
            $port = $_ENV['ST_DB_PORT'] ?? getenv('ST_DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');
            $database = $_ENV['ST_DB_NAME'] ?? getenv('ST_DB_NAME') ?: 'db_surat_tugas';
            $username = $_ENV['ST_DB_USER'] ?? getenv('ST_DB_USER') ?: ($_ENV['DB_USER'] ?? 'root');
            $password = $_ENV['ST_DB_PASS'] ?? getenv('ST_DB_PASS') ?: ($_ENV['DB_PASS'] ?? '');

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $host,
                $port,
                $database
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            return new PDO($dsn, $username, $password, $options);
        } catch (Throwable $e) {
            error_log('Warning: Connection to db_surat_tugas failed: ' . $e->getMessage());
            return null;
        }
    }

    public static function closeConnection(): void {
        self::$connection = null;
        self::$connectionAttempted = false;
    }
}
