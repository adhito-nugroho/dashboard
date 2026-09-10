<?php
/**
 * Migration: Perbarui nama seksi untuk TKUK dan RLPM
 * TKUK = Seksi Tata Kelola dan Usaha Kehutanan
 * RLPM = Seksi Rehabilitasi Lahan dan Pemberdayaan Masyarakat
 */

if (!isset($pdo)) {
    require_once __DIR__ . '/../../config/load_env.php';
    require_once __DIR__ . '/../../config/helpers.php';
    require_once __DIR__ . '/../../config/database.php';
    $pdo = Database::getConnection();
}

try {
    $pdo->beginTransaction();

    // Update TKUK
    $stmt1 = $pdo->prepare("UPDATE seksi SET nama_seksi = 'Seksi Tata Kelola dan Usaha Kehutanan' WHERE kode_seksi = 'TKUK'");
    $stmt1->execute();

    // Update RLPM
    $stmt2 = $pdo->prepare("UPDATE seksi SET nama_seksi = 'Seksi Rehabilitasi Lahan dan Pemberdayaan Masyarakat' WHERE kode_seksi = 'RLPM'");
    $stmt2->execute();

    $pdo->commit();
    echo "Successfully updated nama_seksi for TKUK and RLPM.\n";
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error updating seksi: " . $e->getMessage() . "\n";
}
