<?php
// Manual migration runner
session_start();
require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== MANUAL MIGRATION: CREATE USERS TABLE ===\n\n";

try {
    $db = Database::getConnection();
    echo "[OK] Database connected\n\n";
    
    // Read SQL file
    $sqlFile = __DIR__ . '/../database/migrations/create_users_and_insert.sql';
    
    if (!file_exists($sqlFile)) {
        die("[ERROR] File tidak ditemukan: $sqlFile\n");
    }
    
    $sql = file_get_contents($sqlFile);
    echo "[INFO] SQL file loaded: " . strlen($sql) . " bytes\n\n";
    
    // Split SQL by semicolon (simple parser)
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );
    
    echo "[INFO] Found " . count($statements) . " SQL statements\n\n";
    echo "Executing...\n";
    echo str_repeat('-', 50) . "\n";
    
    $success = 0;
    $failed = 0;
    
    foreach ($statements as $index => $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        // Skip SELECT statements for display
        if (stripos($statement, 'SELECT') === 0) {
            echo "\n[SKIP] SELECT statement #" . ($index + 1) . "\n";
            continue;
        }
        
        try {
            $db->exec($statement);
            $success++;
            
            // Show first 80 chars of statement
            $preview = substr(str_replace(["\n", "\r"], ' ', $statement), 0, 80);
            echo "\n[OK] #" . ($index + 1) . ": " . $preview . "...\n";
            
        } catch (PDOException $e) {
            $failed++;
            echo "\n[ERROR] #" . ($index + 1) . ": " . $e->getMessage() . "\n";
            echo "Statement: " . substr($statement, 0, 100) . "...\n";
        }
    }
    
    echo "\n" . str_repeat('-', 50) . "\n";
    echo "\n=== SUMMARY ===\n";
    echo "Success: $success\n";
    echo "Failed: $failed\n\n";
    
    // Verify users table
    echo "=== VERIFICATION ===\n";
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "[OK] Table 'users' exists\n\n";
        
        $stmt = $db->query("SELECT id, username, role, seksi_id FROM users");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Total users: " . count($users) . "\n\n";
        
        foreach ($users as $user) {
            echo "- {$user['username']} ({$user['role']}) [seksi_id: " . ($user['seksi_id'] ?? 'NULL') . "]\n";
        }
        
        // Test password
        echo "\n=== PASSWORD TEST ===\n";
        $stmt = $db->prepare("SELECT password FROM users WHERE username = 'admin'");
        $stmt->execute();
        $admin = $stmt->fetch();
        
        if ($admin) {
            $testPass = 'admin123';
            $verified = password_verify($testPass, $admin['password']);
            echo "Password verify for 'admin' / 'admin123': " . ($verified ? "SUCCESS ✓" : "FAILED ✗") . "\n";
            
            if (!$verified) {
                echo "\n[WARNING] Password hash tidak cocok!\n";
                echo "Generating new hash...\n";
                $newHash = password_hash($testPass, PASSWORD_DEFAULT);
                echo "\nJalankan SQL ini:\n";
                echo "UPDATE users SET password = '$newHash' WHERE username = 'admin';\n";
            }
        }
        
    } else {
        echo "[ERROR] Table 'users' tidak ditemukan!\n";
    }
    
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}

echo "\n=== END MIGRATION ===\n";
