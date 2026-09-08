<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Models/KasBank.php';
require_once __DIR__ . '/../app/Models/Transaksi.php';

use App\Models\KasBank;
use App\Models\Transaksi;

echo "=== TEST FITUR KAS & BANK (UP/GU) ===\n";

$db = Database::getConnection();
$db->exec("DELETE FROM kas_bank WHERE keterangan LIKE 'TEST%'");
$kasBankModel = new KasBank($db);
$transaksiModel = new Transaksi($db);

// 1. Test Seed Saldo Awal September 2026
$ringkasan = $kasBankModel->getRingkasan(9, 2026);
echo "Saldo Awal September: Rp " . number_format($ringkasan['saldo_awal'], 0, ',', '.') . "\n";
assert($ringkasan['saldo_awal'] == 4734506, 'Saldo awal September harus 4.734.506');
assert($ringkasan['plafond_up'] == 84117000, 'Plafond UP harus 84.117.000');
echo "✓ Test 1: Saldo Awal dan Plafond UP sesuai.\n";

// 2. Test Tambah Pencairan GU
$dummyGu = 79382494.00;
$idGu = $kasBankModel->create(
    '2026-09-12',
    'gu',
    'SP2D-GU-TEST-001',
    'TEST PENCAIRAN GU MINGGU KE-2',
    $dummyGu,
    1
);
assert($idGu > 0, 'Create mutasi GU harus sukses');
echo "✓ Test 2: Pencairan GU berhasil dicatat (ID: $idGu).\n";

// 3. Test Ringkasan Setelah Pencairan GU
$ringkasanAfterGu = $kasBankModel->getRingkasan(9, 2026);
$expectedPenerimaan = 4734506 + $dummyGu;
assert($ringkasanAfterGu['total_penerimaan'] == $expectedPenerimaan, 'Total penerimaan harus 84.117.000');
echo "Total Penerimaan September: Rp " . number_format($ringkasanAfterGu['total_penerimaan'], 0, ',', '.') . "\n";
echo "✓ Test 3: Total penerimaan terhitung akurat.\n";

// 4. Test Update Mutasi
$okUpdate = $kasBankModel->update($idGu, '2026-09-12', 'gu', 'SP2D-GU-TEST-REVISED', 'TEST PENCAIRAN GU REVISED', $dummyGu);
assert($okUpdate === true, 'Update mutasi harus sukses');
$guRecord = $kasBankModel->getById($idGu);
assert($guRecord['nomor_bukti'] === 'SP2D-GU-TEST-REVISED', 'Nomor bukti harus terupdate');
echo "✓ Test 4: Update mutasi berhasil.\n";

// 5. Test Hapus Mutasi Dummy
$okDelete = $kasBankModel->delete($idGu);
assert($okDelete === true, 'Delete mutasi harus sukses');
$guRecordAfter = $kasBankModel->getById($idGu);
assert($guRecordAfter === null, 'Record harus terhapus');
echo "✓ Test 5: Delete mutasi dummy berhasil.\n";

echo "\nSEMUA TEST KAS & BANK BERHASIL LOLOS 100%!\n";
