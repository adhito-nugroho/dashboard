<?php

declare(strict_types=1);

/**
 * SIBADAK — Database Migration Runner
 * CLI:  php migrate.php
 * Web:  buka migrate.php di browser (hanya jika APP_DEBUG=true atau dari CLI)
 */

define('MIGRATION_RUNNER', true);

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>SIBADAK Migration</title></head>";
    echo "<body style='background:#0f172a;color:#f8fafc;font-family:Consolas,monospace;padding:24px;'>";
    echo '<h2 style="font-family:sans-serif;">SIBADAK Database Migration Runner</h2>';
}

function migration_log(string $msg, string $type = 'info'): void
{
    global $isCli;

    if ($isCli) {
        $prefix = match ($type) {
            'success' => '[OK] ',
            'error'   => '[ERROR] ',
            'warning' => '[WARN] ',
            default   => '[INFO] ',
        };
        echo $prefix . $msg . PHP_EOL;
        return;
    }

    $color = match ($type) {
        'success' => '#34d399',
        'error'   => '#f87171',
        'warning' => '#fbbf24',
        default   => '#60a5fa',
    };
    echo '<div style="color:' . $color . ';margin:6px 0;background:#1e293b;padding:8px 12px;border-radius:6px;">'
        . '[' . strtoupper($type) . '] ' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
        . '</div>';
}

require_once __DIR__ . '/config/config.php';

// Blok akses web di production (kecuali APP_DEBUG)
if (!$isCli && !APP_DEBUG) {
    migration_log('Akses web ditolak. Jalankan via CLI: php migrate.php', 'error');
    if (!$isCli) {
        echo '</body></html>';
    }
    exit(1);
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? '3306';
$name = $_ENV['DB_NAME'] ?? 'db_kth_cdk_bjn';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';

migration_log("Connecting to MySQL ({$host}:{$port})...");

try {
    // Pastikan database ada
    $pdoInit = new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdoInit->exec(
        "CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
    migration_log("Database '{$name}' verified/created.", 'success');

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    // Tracker migrasi
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $applied = $pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

    $migrationsDir = __DIR__ . '/database/migrations';
    if (!is_dir($migrationsDir)) {
        mkdir($migrationsDir, 0755, true);
    }

    $files = scandir($migrationsDir) ?: [];
    sort($files);

    $ranCount = 0;

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $filePath = $migrationsDir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($filePath)) {
            continue;
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, ['sql', 'php'], true)) {
            continue;
        }

        if (in_array($file, $applied, true)) {
            continue;
        }

        migration_log("Running: {$file}...");

        try {
            if ($ext === 'sql') {
                $sql = file_get_contents($filePath);
                if ($sql !== false && trim($sql) !== '') {
                    // Multi-statement: temporarily enable emulation
                    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
                    $pdo->exec($sql);
                    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                }
            } else {
                require $filePath;
            }

            $stmt = $pdo->prepare('INSERT INTO migrations (migration) VALUES (?)');
            $stmt->execute([$file]);

            migration_log("Completed: {$file}", 'success');
            $ranCount++;
        } catch (Throwable $ex) {
            migration_log("Failed {$file}: " . $ex->getMessage(), 'error');
            if (!$isCli) {
                echo '</body></html>';
            }
            exit(1);
        }
    }

    if ($ranCount === 0) {
        migration_log('Database up-to-date. No pending migrations.', 'success');
    } else {
        migration_log("Successfully executed {$ranCount} migration(s).", 'success');
    }
} catch (PDOException $e) {
    migration_log('Database connection error: ' . $e->getMessage(), 'error');
    if (!$isCli) {
        echo '</body></html>';
    }
    exit(1);
}

if (!$isCli) {
    echo '</body></html>';
}
