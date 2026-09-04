<?php
/**
 * Test: Admin Transaksi Show (Sub Kegiatan, Kegiatan, Program, dan Rincian Komponen Biaya)
 */
require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../app/Models/Transaksi.php';
require_once __DIR__ . '/../app/Models/RincianBiaya.php';

$db = Database::getConnection();
$transaksiModel = new \App\Models\Transaksi($db);
$rincianModel   = new \App\Models\RincianBiaya($db);

echo "=== TEST 1: Transaksi Model getById returns Sub Kegiatan ===\n";
// Ambil satu transaksi yang ada
$existingTx = $transaksiModel->getById(24);
if (!$existingTx) {
    // Ambil transaksi pertama yang ada
    $stmt = $db->query("SELECT id FROM transaksi ORDER BY id ASC LIMIT 1");
    $firstId = (int)$stmt->fetchColumn();
    $existingTx = $transaksiModel->getById($firstId);
}

assert(!empty($existingTx['kode_sub_kegiatan']), "kode_sub_kegiatan harus ada");
assert(!empty($existingTx['nama_sub_kegiatan']), "nama_sub_kegiatan harus ada");
assert(!empty($existingTx['kode_kegiatan']), "kode_kegiatan harus ada");
assert(!empty($existingTx['nama_kegiatan']), "nama_kegiatan harus ada");
assert(!empty($existingTx['kode_program']), "kode_program harus ada");
assert(!empty($existingTx['nama_program']), "nama_program harus ada");
echo "[PASS] Sub Kegiatan, Kegiatan, dan Program tersedia di model getById: {$existingTx['kode_sub_kegiatan']} - {$existingTx['nama_sub_kegiatan']}\n\n";

echo "=== TEST 2: Perjalanan Dinas dengan Rincian Komponen Biaya ===\n";
// Buat dummy transaksi perjalanan dinas
$stmt = $db->prepare("
    INSERT INTO transaksi (
        tanggal, seksi_id, rekening_id, uraian, nama_penerima, pegawai_nip,
        jenis_transaksi, nomor_surat_tugas, tanggal_pelaksanaan, lokasi_kegiatan,
        nilai, nomor_bukti, status, surat_tugas_ref_id
    ) VALUES (
        '2026-09-04', 1, 1, 'Perjalanan Dinas Konsultasi Teknis',
        'Dr. Ir. Test Verifikasi', '198001012005011001', 'perjalanan_dinas',
        '090/TEST-ADMIN-SHOW/2026', '2026-09-04', 'Dinas Kehutanan Surabaya',
        1750000.00, 'TEST-SHOW-001', 'diajukan', 999
    )
");
$stmt->execute();
$testTxId = (int)$db->lastInsertId();

$komponen = [
    [
        'nama_komponen' => 'Uang Harian',
        'harga_satuan'  => 430000,
        'jumlah_hari'   => 2,
        'jumlah'        => 860000,
        'keterangan'    => '2 hari x Rp 430.000'
    ],
    [
        'nama_komponen' => 'Penginapan / Hotel',
        'harga_satuan'  => 590000,
        'jumlah_hari'   => 1,
        'jumlah'        => 590000,
        'keterangan'    => 'Hotel 1 malam'
    ],
    [
        'nama_komponen' => 'BBM / Transport',
        'harga_satuan'  => 300000,
        'jumlah_hari'   => 1,
        'jumlah'        => 300000,
        'keterangan'    => 'Transport darat PP'
    ]
];

$rbId = $rincianModel->upsertDariTransaksi(
    $testTxId,
    999,
    '090/TEST-ADMIN-SHOW/2026',
    '198001012005011001',
    'Dr. Ir. Test Verifikasi',
    'Pembina Utama Muda / IV/c',
    'Kepala CDK Wilayah Bojonegoro',
    1750000,
    1750000,
    'Bojonegoro, 04 September 2026',
    1,
    $komponen
);

$loadedRincian = $rincianModel->findForTransaksi($transaksiModel->getById($testTxId));
assert($loadedRincian !== null, "Rincian biaya harus berhasil dimuat");
assert(count($loadedRincian['details']) === 3, "Harus ada 3 komponen rincian");
assert($loadedRincian['details'][0]['nama_komponen'] === 'Uang Harian');
assert((float)$loadedRincian['details'][0]['jumlah'] === 860000.0);
echo "[PASS] Komponen biaya perjalanan dinas berhasil di-load via findForTransaksi\n\n";

echo "=== TEST 3: Fallback saat transaksi_id IS NULL (data via SPJ / unlink) ===\n";
// Set transaksi_id = NULL
$db->exec("UPDATE rincian_biaya_perjalanan_dinas SET transaksi_id = NULL WHERE id = {$rbId}");
$unlinkedRincian = $rincianModel->findForTransaksi($transaksiModel->getById($testTxId));
assert($unlinkedRincian !== null, "Rincian biaya harus tetap ditemukan via fallback surat_tugas_id + nip");
assert($unlinkedRincian['header']['id'] == $rbId, "ID rincian harus sesuai");
// Verifikasi relink otomatis berjalan
$currTxId = $db->query("SELECT transaksi_id FROM rincian_biaya_perjalanan_dinas WHERE id = {$rbId}")->fetchColumn();
assert($currTxId == $testTxId, "transaksi_id harus tertaut otomatis");
echo "[PASS] Fallback & auto-relink berhasil saat transaksi_id sebelumnya NULL\n\n";

echo "=== TEST 4: Render view detail show.php ===\n";
$transaksi = $transaksiModel->getById($testTxId);
$rincianBiaya = $unlinkedRincian;

// Render view ke output buffer
ob_start();
include __DIR__ . '/../views/transaksi/show.php';
$html = ob_get_clean();

// Verifikasi konten di HTML hasil render
assert(strpos($html, $transaksi['kode_sub_kegiatan']) !== false, "HTML harus mengandung kode_sub_kegiatan");
assert(strpos($html, $transaksi['nama_sub_kegiatan']) !== false, "HTML harus mengandung nama_sub_kegiatan");
assert(strpos($html, 'Uang Harian') !== false, "HTML harus menampilkan Uang Harian");
assert(strpos($html, 'Penginapan / Hotel') !== false, "HTML harus menampilkan Penginapan / Hotel");
assert(strpos($html, 'BBM / Transport') !== false, "HTML harus menampilkan BBM / Transport");
assert(strpos($html, '860.000') !== false, "HTML harus menampilkan jumlah komponen 860.000");
assert(strpos($html, 'Dr. Ir. Test Verifikasi') !== false, "HTML harus menampilkan nama pegawai penerima");
assert(strpos($html, 'Pembina Utama Muda / IV/c') !== false, "HTML harus menampilkan pangkat pegawai");
assert(strpos($html, 'Dinas Kehutanan Surabaya') !== false, "HTML harus menampilkan lokasi kegiatan");
echo "[PASS] View detail berhasil me-render Sub Kegiatan dan semua Komponen Rincian Biaya secara lengkap!\n\n";

// Cleanup test data
$db->exec("DELETE FROM rincian_biaya_perjalanan_dinas_detail WHERE rincian_biaya_id = {$rbId}");
$db->exec("DELETE FROM rincian_biaya_perjalanan_dinas WHERE id = {$rbId}");
$db->exec("DELETE FROM transaksi WHERE id = {$testTxId}");
echo "[CLEANUP] Data testing dihapus dengan bersih.\n";
echo "\n=== SEMUA PENGUJIAN SUKSES (100% PASS) ===\n";
