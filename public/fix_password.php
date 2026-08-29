<?php
// Fix password hash untuk semua user
session_start();
require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== FIX PASSWORD HASH ===\n\n";

try {
    $db = Database::getConnection();
    echo "[OK] Database connected\n\n";
    
    // Generate hash baru untuk password 'admin123'
    $password = 'admin123';
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    
    echo "Password: $password\n";
    echo "New hash: $newHash\n\n";
    
    // Update semua user
    $users = ['admin', 'tu', 'rlpm', 'tkuk'];
    
    echo "Updating all users...\n";
    echo str_repeat('-', 50) . "\n";
    
    foreach ($users as $username) {
        try {
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE username = ?");
            $stmt->execute([$newHash, $username]);
            echo "[OK] Updated: $username\n";
        } catch (PDOException $e) {
            echo "[ERROR] Failed to update $username: " . $e->getMessage() . "\n";
        }
    }
    
    echo str_repeat('-', 50) . "\n\n";
    
    // Verify
    echo "=== VERIFICATION ===\n";
    foreach ($users as $username) {
        $stmt = $db->prepare("SELECT password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user) {
            $verified = password_verify($password, $user['password']);
            $status = $verified ? "✓ SUCCESS" : "✗ FAILED";
            echo "$username: $status\n";
        }
    }
    
    echo "\n=== SELESAI ===\n";
    echo "Semua user sekarang menggunakan password: admin123\n";
    echo "Silakan coba login!\n";
    
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}
