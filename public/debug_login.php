<?php
// Debug file - hapus setelah selesai troubleshooting
session_start();
require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DEBUG LOGIN SYSTEM ===\n\n";

try {
    $db = Database::getConnection();
    echo "[OK] Database connection successful\n\n";
    
    // Cek tabel users
    echo "--- Users Table ---\n";
    $stmt = $db->query("SELECT id, username, role, seksi_id, LEFT(password, 20) as pw_preview, LENGTH(password) as pw_length FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "[ERROR] Tabel users kosong! Migrasi belum berhasil.\n";
    } else {
        echo "Total users: " . count($users) . "\n\n";
        foreach ($users as $user) {
            echo "ID: {$user['id']}\n";
            echo "Username: {$user['username']}\n";
            echo "Role: {$user['role']}\n";
            echo "Seksi ID: " . ($user['seksi_id'] ?? 'NULL') . "\n";
            echo "Password Hash (20 chars): {$user['pw_preview']}...\n";
            echo "Password Length: {$user['pw_length']}\n\n";
        }
    }
    
    // Test password verification
    echo "--- Password Verification Test ---\n";
    $testUsername = 'admin';
    $testPassword = 'admin123';
    
    $stmt = $db->prepare("SELECT password FROM users WHERE username = ?");
    $stmt->execute([$testUsername]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "User '$testUsername' found in database\n";
        echo "Stored hash: " . substr($user['password'], 0, 30) . "...\n";
        
        $verified = password_verify($testPassword, $user['password']);
        echo "Password verify result: " . ($verified ? 'SUCCESS ✓' : 'FAILED ✗') . "\n\n";
        
        if (!$verified) {
            echo "[INFO] Generating new hash for 'admin123':\n";
            $newHash = password_hash($testPassword, PASSWORD_DEFAULT);
            echo $newHash . "\n\n";
            echo "SQL untuk update:\n";
            echo "UPDATE users SET password = '$newHash' WHERE username = 'admin';\n";
        }
    } else {
        echo "[ERROR] User '$testUsername' tidak ditemukan\n";
    }
    
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}

echo "\n=== END DEBUG ===\n";
echo "\nHAPUS FILE INI SETELAH SELESAI TROUBLESHOOTING!\n";
