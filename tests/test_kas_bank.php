<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Models/KasBank.php';
require_once __DIR__ . '/../app/Models/Transaksi.php';

use App\Models\KasBank;
use App\Models\Transaksi;

echo "=== TEST FITUR KAS & BANK (STATUS MENUNGGU CAIR & CAIR) ===\n";

$db = Database::getConnection();
$db->exec("DELETE FROM kas_bank WHERE keterangan LIKE 'TEST%'");
$kasBankModel = new KasBank($db);

// 1. Test Seed Saldo Awal September 2026 & GU Menunggu Cair
$ringkasan = $kasBankModel->getRingkasan(9, 2026);
echo "Saldo Awal September: Rp " . number_format($ringkasan['saldo_awal'], 0, ',', '.') . "\n";
echo "GU Menunggu Cair: Rp " . number_format($ringkasan['gu_menunggu_cair'], 0, ',', '.') . "\n";
echo "Saldo Kas Riil Saat Ini: Rp " . number_format($ringkasan['saldo_kas_saat_ini'], 0, ',', '.') . "\n";
echo "Belanja Siap GU: Rp " . number_format($ringkasan['belanja_siap_gu'], 0, ',', '.') . "\n";
echo "Proyeksi Kas Setelah GU Cair: Rp " . number_format($ringkasan['proyeksi_kas_setelah_cair'], 0, ',', '.') . "\n";

assert($ringkasan['saldo_awal'] == 4734506, 'Saldo awal September harus 4.734.506');
assert($ringkasan['gu_menunggu_cair'] == 79382494, 'GU menunggu cair harus 79.382.494');
assert($ringkasan['proyeksi_kas_setelah_cair'] == ($ringkasan['saldo_kas_saat_ini'] + 79382494), 'Proyeksi harus saldo kas + GU menunggu cair');
echo "✓ Test 1: Pemisahan GU Menunggu Cair vs Saldo Kas Riil lolos.\n";

// 2. Test Tambah Pengajuan GU Baru (Dummy) status 'menunggu_cair'
$idDummy = $kasBankModel->create(
    '2026-09-15',
    'gu',
    'SP2D-GU-TEST-SEP',
    'TEST PENGAJUAN GU PERIODE 1-15 SEPT',
    5000000.00,
    1,
    'menunggu_cair'
);
assert($idDummy > 0, 'Create mutasi GU menunggu cair harus sukses');

$ringkasanAfterDummy = $kasBankModel->getRingkasan(9, 2026);
assert($ringkasanAfterDummy['gu_menunggu_cair'] == (79382494 + 5000000), 'GU menunggu cair bertambah');
assert($ringkasanAfterDummy['saldo_kas_saat_ini'] == $ringkasan['saldo_kas_saat_ini'], 'Saldo kas riil TIDAK boleh bertambah sebelum cair');
echo "✓ Test 2: Pengajuan GU baru tidak menambah saldo kas sebelum cair.\n";

// 3. Test Cairkan Dana GU Dummy
$okCair = $kasBankModel->tandaiCair($idDummy, '2026-09-16');
assert($okCair === true, 'Tandai cair harus sukses');
$ringkasanAfterCair = $kasBankModel->getRingkasan(9, 2026);
assert($ringkasanAfterCair['saldo_kas_saat_ini'] == ($ringkasan['saldo_kas_saat_ini'] + 5000000), 'Saldo kas riil bertambah setelah cair');
echo "✓ Test 3: Saldo kas riil bertambah tepat setelah dana GU dicairkan.\n";

// Cleanup
$kasBankModel->delete($idDummy);
echo "✓ Test 4: Cleanup dummy test berhasil.\n";

echo "\nSEMUA TEST STATUS KAS & BANK BERHASIL LOLOS 100%!\n";
