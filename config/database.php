<?php
/**
 * Database Connection Configuration
 * 
 * Secure MySQL PDO connection using environment variables
 * PHP 8 compatible
 */

class Database {
    private static ?PDO $connection = null;
    
    /**
     * Get database connection instance (Singleton pattern)
     * 
     * @return PDO
     * @throws PDOException
     */
    public static function getConnection(): PDO {
        if (self::$connection === null) {
            self::$connection = self::createConnection();
        }
        
        return self::$connection;
    }
    
    /**
     * Create new PDO connection
     * 
     * @return PDO
     * @throws PDOException
     */
    private static function createConnection(): PDO {
        try {
            // Load environment variables
            $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
            $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
            $database = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'db_anggaran';
            $username = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: '';
            $password = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';
            
            // Validate required credentials
            if (empty($username)) {
                throw new RuntimeException('Database username is not configured. Please set DB_USER environment variable.');
            }
            
            // Build DSN
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $host,
                $port,
                $database
            );
            
            // PDO options for security and error handling
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            // Create PDO connection
            $pdo = new PDO($dsn, $username, $password, $options);
            
            return $pdo;
            
        } catch (PDOException $e) {
            // Log error (in production, log to file instead of displaying)
            error_log('Database connection failed: ' . $e->getMessage());
            
            // Re-throw with user-friendly message
            throw new PDOException(
                'Database connection failed. Please check your configuration.',
                0,
                $e
            );
        } catch (RuntimeException $e) {
            // Re-throw configuration errors
            throw $e;
        }
    }
    
    /**
     * Close database connection
     */
    public static function closeConnection(): void {
        self::$connection = null;
    }
}

