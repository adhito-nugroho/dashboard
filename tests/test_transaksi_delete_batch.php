<?php

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Models/Transaksi.php';

$db = Database::getConnection();
$transaksiModel = new \App\Models\Transaksi($db);

echo "Starting test_transaksi_delete_batch...\n";

// 1. Dapatkan referensi rekening & seksi yang ada untuk dummy data
$stmt = $db->query("
    SELECT r.id AS rekening_id, sk.seksi_id 
    FROM rekening r 
    JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id 
    WHERE sk.seksi_id IS NOT NULL 
    LIMIT 1
");
$rek = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$rek) {
    echo "SKIP: Tidak ada rekening dengan seksi_id untuk pengujian.\n";
    exit(0);
}

// 2. Insert dummy transactions
$ids = [];
for ($i = 1; $i <= 3; $i++) {
    $stmt = $db->prepare("
        INSERT INTO transaksi (tanggal, seksi_id, rekening_id, uraian, nilai, nomor_bukti)
        VALUES (CURDATE(), :seksi_id, :rekening_id, :uraian, 50000, :no_bukti)
    ");
    $uraian = "Dummy Transaksi DeleteBatch {$i} - " . time();
    $noBukti = "TEST-DEL-{$i}-" . time();
    $stmt->execute([
        ':seksi_id' => $rek['seksi_id'],
        ':rekening_id' => $rek['rekening_id'],
        ':uraian' => $uraian,
        ':no_bukti' => $noBukti,
    ]);
    $ids[] = (int) $db->lastInsertId();
}

echo "Created 3 dummy transactions: " . implode(', ', $ids) . "\n";

// 3. Test deleteBatch dengan array kosong
$deletedEmpty = $transaksiModel->deleteBatch([]);
if ($deletedEmpty !== 0) {
    echo "FAIL: deleteBatch([]) should return 0, got {$deletedEmpty}\n";
    exit(1);
}

// 4. Test deleteBatch dengan IDs dummy
$deletedCount = $transaksiModel->deleteBatch($ids);
if ($deletedCount !== 3) {
    echo "FAIL: Expected 3 deleted rows, got {$deletedCount}\n";
    exit(1);
}

// 5. Verify records no longer exist in database
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$checkStmt = $db->prepare("SELECT COUNT(*) FROM transaksi WHERE id IN ($placeholders)");
$checkStmt->execute($ids);
$remaining = (int) $checkStmt->fetchColumn();

if ($remaining !== 0) {
    echo "FAIL: Expected 0 remaining records, got {$remaining}\n";
    exit(1);
}

echo "TEST PASSED: Transaksi::deleteBatch works correctly.\n";
