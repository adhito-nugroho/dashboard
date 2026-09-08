<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Models/Transaksi.php';

use App\Models\Transaksi;

echo "=== TEST SISTEM PENOMORAN BUKTI SEMENTARA & VERIFIKASI ===\n";

$db = Database::getConnection();
$transaksiModel = new Transaksi($db);

// Test 1: Helper Romawi
assert(Transaksi::getBulanRomawi(1) === 'I', 'Bulan 1 harus I');
assert(Transaksi::getBulanRomawi(9) === 'IX', 'Bulan 9 harus IX');
assert(Transaksi::getBulanRomawi(12) === 'XII', 'Bulan 12 harus XII');
echo "✓ Test 1: Helper Bulan Romawi lolos.\n";

// Test 2: Generate Draft Nomor
$drafts = $transaksiModel->getNextNomorBuktiDraft(9, 2026, 3);
assert(count($drafts) === 3, 'Harus menghasilkan 3 nomor draft');
assert(strpos($drafts[0], '123.6.6/GU/DRAFT-') === 0, 'Format draft harus 123.6.6/GU/DRAFT-...');
assert(strpos($drafts[0], '/IX/2026') !== false, 'Draft harus mengandung bulan & tahun romawi');
echo "✓ Test 2: Generate Draft Nomor lolos (" . $drafts[0] . ").\n";

// Ambil seksi_id & rekening_id yang valid
$seksi = $db->query("SELECT id FROM seksi LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$rekening = $db->query("SELECT id FROM rekening LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$seksi || !$rekening) {
    die("X Tidak ada data seksi / rekening untuk testing.\n");
}

$seksiId = (int) $seksi['id'];
$rekeningId = (int) $rekening['id'];
$testDate = '2026-09-15';

// Bersihkan test data lama jika ada
$db->exec("DELETE FROM transaksi WHERE uraian LIKE 'TEST_NOMOR_BUKTI_%'");

// Buat 2 transaksi draft diajukan (Trx A dan Trx B)
$idA = $transaksiModel->createSeksi(
    $testDate,
    $seksiId,
    $rekeningId,
    'TEST_NOMOR_BUKTI_A',
    100000,
    '123.6.6/GU/DRAFT-998/IX/2026',
    1
);

$idB = $transaksiModel->createSeksi(
    $testDate,
    $seksiId,
    $rekeningId,
    'TEST_NOMOR_BUKTI_B',
    200000,
    '123.6.6/GU/DRAFT-999/IX/2026',
    1
);

echo "✓ Test 3: Trx A (ID: $idA) & Trx B (ID: $idB) berhasil dibuat dengan nomor draft.\n";

// Hitung nomor resmi awal yang ada di DB untuk September 2026 sebelum test
$stmt = $db->query("SELECT COUNT(*) FROM transaksi WHERE status = 'diverifikasi' AND MONTH(tanggal) = 9 AND YEAR(tanggal) = 2026 AND nomor_bukti LIKE '123.6.6/GU/%'");
$baseResmiCount = (int) $stmt->fetchColumn();

// Verifikasi Trx B LEBIH DULU (out of order verification)
$okB = $transaksiModel->verifikasi($idB, 'diverifikasi', 1, 'OK');
assert($okB === true, 'Verifikasi B harus sukses');
$trxB = $transaksiModel->getById($idB);

echo "Trx B (diverifikasi pertama) mendapat No: " . $trxB['nomor_bukti'] . "\n";
assert(strpos($trxB['nomor_bukti'], '123.6.6/GU/') === 0, 'No Bukti B harus resmi');
assert(strpos($trxB['nomor_bukti'], 'DRAFT') === false, 'No Bukti B tidak boleh ada kata DRAFT');

// Verifikasi Trx A KEMUDIAN
$okA = $transaksiModel->verifikasi($idA, 'diverifikasi', 1, 'OK');
assert($okA === true, 'Verifikasi A harus sukses');
$trxA = $transaksiModel->getById($idA);

echo "Trx A (diverifikasi kedua) mendapat No: " . $trxA['nomor_bukti'] . "\n";
assert(strpos($trxA['nomor_bukti'], '123.6.6/GU/') === 0, 'No Bukti A harus resmi');
assert(strpos($trxA['nomor_bukti'], 'DRAFT') === false, 'No Bukti A tidak boleh ada kata DRAFT');

// Ekstrak nomor urut
preg_match('#^123\.6\.6/GU/(\d+)/#', $trxB['nomor_bukti'], $mB);
preg_match('#^123\.6\.6/GU/(\d+)/#', $trxA['nomor_bukti'], $mA);
$urutB = (int) ($mB[1] ?? 0);
$urutA = (int) ($mA[1] ?? 0);

assert($urutA === $urutB + 1, "Nomor urut A ($urutA) harus tepat 1 angka setelah B ($urutB) karena diverifikasi setelah B!");
echo "✓ Test 4: Verifikasi out-of-order terbukti memberikan nomor urut resmi secara sekuensial (B: $urutB -> A: $urutA).\n";

// Test 5: Batal Verifikasi Trx A
$okBatal = $transaksiModel->batalVerifikasi($idA);
assert($okBatal === true, 'Batal verifikasi harus sukses');
$trxAAfterBatal = $transaksiModel->getById($idA);
assert($trxAAfterBatal['status'] === 'diajukan', 'Status harus kembali diajukan');
assert(strpos($trxAAfterBatal['nomor_bukti'], 'DRAFT') !== false, 'Nomor bukti harus kembali ke format DRAFT');
echo "✓ Test 5: Batal verifikasi berhasil mengembalikan status ke 'diajukan' dan nomor bukti ke draft (" . $trxAAfterBatal['nomor_bukti'] . ").\n";

// Test 6: Re-verifikasi Trx A
$okReverif = $transaksiModel->verifikasi($idA, 'diverifikasi', 1, 'OK ulang');
assert($okReverif === true, 'Re-verifikasi harus sukses');
$trxAReverif = $transaksiModel->getById($idA);
assert(strpos($trxAReverif['nomor_bukti'], '123.6.6/GU/') === 0, 'Re-verifikasi harus memberikan nomor resmi');
assert(strpos($trxAReverif['nomor_bukti'], 'DRAFT') === false, 'Re-verifikasi nomor resmi tidak boleh ada DRAFT');
echo "✓ Test 6: Re-verifikasi berhasil memberikan nomor resmi (" . $trxAReverif['nomor_bukti'] . ").\n";

// Cleanup
$db->exec("DELETE FROM transaksi WHERE id IN ($idA, $idB)");
echo "✓ Test data dibersihkan.\n";

echo "\nSEMUA TEST SISTEM PENOMORAN BUKTI BERHASIL LOLOS 100%!\n";
