<?php

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../app/Models/Program.php';
require_once __DIR__ . '/../app/Models/Kegiatan.php';
require_once __DIR__ . '/../app/Models/SubKegiatan.php';
require_once __DIR__ . '/../app/Models/Seksi.php';
require_once __DIR__ . '/../app/Models/Rekening.php';
require_once __DIR__ . '/../app/Models/Pagu.php';
require_once __DIR__ . '/../app/Models/Rak.php';
require_once __DIR__ . '/../app/Models/Transaksi.php';
require_once __DIR__ . '/../app/Controllers/TransaksiController.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = Database::getConnection();
$transaksiModel = new \App\Models\Transaksi($db);
$seksiModel = new \App\Models\Seksi($db);
$paguModel = new \App\Models\Pagu($db);
$rakModel = new \App\Models\Rak($db);
$programModel = new \App\Models\Program($db);
$kegiatanModel = new \App\Models\Kegiatan($db);
$subKegiatanModel = new \App\Models\SubKegiatan($db);
$rekeningModel = new \App\Models\Rekening($db);

$controller = new \App\Controllers\TransaksiController(
    $transaksiModel,
    $seksiModel,
    $paguModel,
    $rakModel,
    $programModel,
    $kegiatanModel,
    $subKegiatanModel,
    $rekeningModel
);

// 1. Create dummy transactions
$stmt = $db->query("
    SELECT r.id AS rekening_id, sk.seksi_id 
    FROM rekening r 
    JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id 
    WHERE sk.seksi_id IS NOT NULL 
    LIMIT 1
");
$rek = $stmt->fetch(PDO::FETCH_ASSOC);

$ids = [];
for ($i = 1; $i <= 2; $i++) {
    $stmt = $db->prepare("
        INSERT INTO transaksi (tanggal, seksi_id, rekening_id, uraian, nilai, nomor_bukti)
        VALUES (CURDATE(), :seksi_id, :rekening_id, :uraian, 75000, :no_bukti)
    ");
    $stmt->execute([
        ':seksi_id' => $rek['seksi_id'],
        ':rekening_id' => $rek['rekening_id'],
        ':uraian' => "Dummy Controller DeleteBatch {$i} - " . time(),
        ':no_bukti' => "CTRL-DEL-{$i}-" . time(),
    ]);
    $ids[] = (int) $db->lastInsertId();
}

// 2. Mock POST data
$_POST['ids'] = $ids;
$_SERVER['REQUEST_METHOD'] = 'POST';

// Register shutdown function to check database after exit
register_shutdown_function(function() use ($db, $ids) {
    if (empty($_SESSION['flash_message']) || $_SESSION['flash_type'] !== 'success') {
        echo "FAIL: Flash message not set properly. Message: " . ($_SESSION['flash_message'] ?? 'null') . "\n";
        exit(1);
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $checkStmt = $db->prepare("SELECT COUNT(*) FROM transaksi WHERE id IN ($placeholders)");
    $checkStmt->execute($ids);
    $remaining = (int) $checkStmt->fetchColumn();

    if ($remaining !== 0) {
        echo "FAIL: Expected 0 remaining rows, got {$remaining}\n";
        exit(1);
    }

    echo "TEST PASSED: TransaksiController::deleteBatch executed successfully.\n";
});

// Suppress output
ob_start();
$controller->deleteBatch();
