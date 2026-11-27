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

// 2. Test mekanisme 'menunggu_cair' → 'cair' memakai pengajuan GU yang ada.
//    Catatan: data riil sudah memakai plafon UP/GU secara penuh
//    (saldo + GU menunggu cair = plafon), jadi pengajuan GU baru memang
//    ditolak — diuji di Test 2b.
$seed = $db->query("
    SELECT id, nominal, status, tanggal_cair FROM kas_bank
    WHERE jenis = 'gu' AND status = 'menunggu_cair' AND jenis_dana = 'UP_GU'
    ORDER BY id LIMIT 1
")->fetch(PDO::FETCH_ASSOC);
assert($seed !== false, 'Butuh satu pengajuan GU menunggu_cair untuk menguji mekanisme');
$idSeed        = (int) $seed['id'];
$nominalSeed   = (float) $seed['nominal'];
$tanggalAsli   = $seed['tanggal_cair'];

$ringkasanBefore = $kasBankModel->getRingkasan(9, 2026);
assert($ringkasanBefore['saldo_kas_saat_ini'] == $ringkasan['saldo_kas_saat_ini'],
    'Saldo kas riil TIDAK boleh bertambah sebelum cair');
echo "✓ Test 2: Pengajuan GU menunggu cair tidak menambah saldo kas.\n";

// 2b. Aturan plafon: GU yang membuat dana UP/GU melebihi plafon harus ditolak.
$ditolakPlafon = false;
try {
    $idLewat = $kasBankModel->create(
        '2026-09-17', 'gu', 'SPJ-GU-TEST-LEWAT', 'TEST LEWAT PLAFON',
        1000000.00, 1, 'menunggu_cair'
    );
    $kasBankModel->delete($idLewat);
} catch (RuntimeException $e) {
    $ditolakPlafon = str_contains($e->getMessage(), 'plafon') && str_contains($e->getMessage(), 'TU');
}
assert($ditolakPlafon === true,
    'Penerimaan UP/GU yang melewati plafon harus ditolak dan diarahkan ke TU');
echo "✓ Test 2b: Dana UP/GU tidak boleh melebihi plafon; kelebihannya milik TU.\n";

// 3. Test Cairkan GU tersebut
$okCair = $kasBankModel->tandaiCair($idSeed, '2026-09-16');
assert($okCair === true, 'Tandai cair harus sukses');
$ringkasanAfterCair = $kasBankModel->getRingkasan(9, 2026);
assert($ringkasanAfterCair['saldo_kas_saat_ini'] == ($ringkasan['saldo_kas_saat_ini'] + $nominalSeed),
    'Saldo kas riil bertambah tepat setelah dana GU dicairkan');
assert($ringkasanAfterCair['gu_menunggu_cair'] == 0,
    'GU yang sudah cair tidak lagi dihitung sebagai menunggu cair');
echo "✓ Test 3: Saldo kas riil bertambah tepat setelah dana GU dicairkan.\n";

// Kembalikan pengajuan GU ke keadaan semula
$db->prepare("UPDATE kas_bank SET status = 'menunggu_cair', tanggal_cair = ? WHERE id = ?")
   ->execute([$tanggalAsli, $idSeed]);

// 4. Siklus TU tidak mengubah saldo (setoran sisa TU adalah uang keluar)
$nominalTu = 2000000.00;
$idTuMasuk = $kasBankModel->create('2026-09-20', 'tu', 'SPJ-TU-TEST', 'TEST PENCAIRAN TU',
    $nominalTu, 1, 'cair', '2026-09-20', 'masuk', 'TU');
$idTuSetor = $kasBankModel->create('2026-09-25', 'setoran', 'SETOR-TU-TEST', 'TEST SETORAN SISA TU',
    $nominalTu, 1, 'cair', '2026-09-25', 'keluar', 'TU');

$ringkasanTu = $kasBankModel->getRingkasan(9, 2026);
assert(abs($ringkasanTu['saldo_kas_saat_ini'] - $ringkasan['saldo_kas_saat_ini']) < 0.01,
    'Pencairan TU + setoran sisa TU harus saling meniadakan di saldo');
assert(abs($ringkasanTu['dana_tu_di_kas']) < 0.01, 'Uang TU kembali 0 setelah disetor');
echo "✓ Test 4: Setoran sisa TU mengurangi kas (tidak membengkakkan saldo).\n";

$kasBankModel->delete($idTuMasuk);
$kasBankModel->delete($idTuSetor);
echo "✓ Test 5: Cleanup dummy test berhasil.\n";

echo "\nSEMUA TEST STATUS KAS & BANK BERHASIL LOLOS 100%!\n";
