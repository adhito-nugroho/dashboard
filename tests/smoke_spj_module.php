<?php

/**
 * Smoke test modul SPJ (read-only, tanpa efek samping).
 *
 * Menjalankan pemeriksaan cepat setelah refactor:
 *  - Autoload & keberadaan kelas baru (Repositories, Core\Instance).
 *  - Repositori Surat Tugas: daftar, cari ST, cari pegawai, peta Kota Tujuan.
 *  - SpdExportService: buildData/buildDataFromTransaksi + delegasi.
 *  - View notifikasi bersama (views/shared/standalone_notice.php) bisa dirender.
 *
 * Exit code: 0 = lulus / dilewati (DB mati), 1 = ada kegagalan.
 * Jalankan: php tests/smoke_spj_module.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/helpers.php';

use App\Core\Instance;
use App\Repositories\PerjadinRepository;
use App\Repositories\SuratTugasRepository;
use App\Services\SpdExportService;

$pass = 0;
$fail = 0;
$skip = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  [PASS] {$name}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
    } else {
        $fail++;
        echo "  [FAIL] {$name}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
    }
}

function skip(string $name, string $why = 'koneksi DB tidak tersedia'): void
{
    global $skip;
    $skip++;
    echo "  [SKIP] {$name} — {$why}" . PHP_EOL;
}

echo "== Smoke test modul SPJ ==" . PHP_EOL . PHP_EOL;

// ── 1. Kelas & autoload ──────────────────────────────────────────────
echo "[1] Kelas inti" . PHP_EOL;
check('SuratTugasRepository load', class_exists(SuratTugasRepository::class));
check('PerjadinRepository load', class_exists(PerjadinRepository::class));
check('Core\\Instance load', class_exists(Instance::class));
check('SpdExportService load', class_exists(SpdExportService::class));

// ── 2. Identitas instansi ────────────────────────────────────────────
echo PHP_EOL . "[2] Identitas instansi (Core\\Instance)" . PHP_EOL;
check('kpaNama() tidak kosong', Instance::kpaNama() !== '', Instance::kpaNama());
check('kpaNip() hanya digit', Instance::kpaNip() !== '' && ctype_digit(Instance::kpaNip()), Instance::kpaNip());
check('INSTANSI terisi', Instance::INSTANSI !== '');
check('TEMPAT_BERANGKAT terisi', Instance::TEMPAT_BERANGKAT !== '');

// ── 3. Repositori Surat Tugas ────────────────────────────────────────
echo PHP_EOL . "[3] SuratTugasRepository" . PHP_EOL;
$stRepo = new SuratTugasRepository();
$available = $stRepo->isAvailable();
check('isAvailable() mengembalikan bool', is_bool($available), $available ? 'true' : 'false');

if (!$available) {
    skip('list()', 'db_surat_tugas tidak terjangkau');
    skip('find()/pegawaiList()/kotaTujuanKosongMap()');
    skip('SpdExportService::buildData()');
} else {
    $tahun = (int) date('Y');
    $list = $stRepo->list(['tahun' => $tahun, 'limit' => 5]);
    check('list() mengembalikan array', is_array($list), count($list) . ' baris');

    if (!$list) {
        skip('find()/pegawaiList()', "tidak ada Surat Tugas tahun {$tahun}");
    } else {
        $row = $list[0];
        check('list() baris punya kota_tujuan', array_key_exists('kota_tujuan', $row));
        check('list() baris punya nomor_surat', !empty($row['nomor_surat']));

        $stId = (int) $row['id'];
        $st = $stRepo->find($stId);
        check('find() mengembalikan baris', is_array($st) && (int) $st['id'] === $stId);

        $pegawai = $stRepo->pegawaiList($stId);
        check('pegawaiList() mengembalikan array', is_array($pegawai), count($pegawai) . ' pegawai');

        $map = $stRepo->kotaTujuanKosongMap([$stId]);
        check('kotaTujuanKosongMap() punya entri ST', array_key_exists($stId, $map));
        $expectedKosong = trim((string) ($st['kota_tujuan'] ?? '')) === '';
        check('kotaTujuanKosongMap() sesuai isi kota_tujuan', ($map[$stId] ?? null) === $expectedKosong,
            'kota_tujuan=' . var_export($st['kota_tujuan'] ?? null, true));

        check('resolveIdByNomor() menemukan ST', $stRepo->resolveIdByNomor((string) $st['nomor_surat']) === $stId);

        // ── SpdExportService dengan data nyata ──
        $svc = new SpdExportService($stRepo, new PerjadinRepository());
        if ($pegawai) {
            $nip = (string) ($pegawai[0]['nip'] ?? '');
            $data = $nip !== '' ? $svc->buildData($stId, $nip) : null;
            check('buildData() mengembalikan data', is_array($data));
            if (is_array($data)) {
                foreach (['kota_tujuan', 'kota_tujuan_kosong', 'tempat_tujuan', 'instansi', 'kpa_nip'] as $key) {
                    check("buildData() punya kunci '{$key}'", array_key_exists($key, $data));
                }
                check('kota_tujuan_kosong konsisten',
                    ($data['kota_tujuan_kosong'] === true) === ($expectedKosong),
                    'kosong=' . var_export($expectedKosong, true));

                $trx = ['surat_tugas_ref_id' => $stId, 'nomor_surat_tugas' => (string) $st['nomor_surat'], 'pegawai_nip' => $nip];
                check('buildDataFromTransaksi() dari ref_id', is_array($svc->buildDataFromTransaksi($trx)));
            }
        } else {
            skip('buildData()', 'ST tidak memiliki pegawai');
        }
    }
}

// ── 4. Peta kota kosong untuk baris transaksi (murni) ────────────────
echo PHP_EOL . "[4] kotaTujuanKosongMapForTransaksi (sintetis)" . PHP_EOL;
$synthetic = [
    ['id' => 1, 'jenis_transaksi' => 'perjalanan_dinas', 'surat_tugas_ref_id' => 999999],
    ['id' => 2, 'jenis_transaksi' => 'perjalanan_dinas', 'surat_tugas_ref_id' => 0],
    ['id' => 3, 'jenis_transaksi' => 'pengadaan', 'surat_tugas_ref_id' => 999999],
];
$mapSyn = $stRepo->kotaTujuanKosongMapForTransaksi($synthetic);
check('mengabaikan ref_id kosong (baris 2)', !array_key_exists(2, $mapSyn));
check('mengabaikan non-perjalanan dinas (baris 3)', !array_key_exists(3, $mapSyn));
if ($available && !$stRepo->hasKotaTujuanColumn()) {
    check('schema tanpa kota_tujuan => ditandai kosong (baris 1)', ($mapSyn[1] ?? null) === true);
}
check('hasKotaTujuanColumn() mengembalikan bool', is_bool($stRepo->hasKotaTujuanColumn()),
    $stRepo->hasKotaTujuanColumn() ? 'kolom ada' : 'kolom tidak ada');

// ── 5. View notifikasi bersama ───────────────────────────────────────
echo PHP_EOL . "[5] View notifikasi bersama" . PHP_EOL;
$html = (static function (): string {
    $noticeTitle = 'Kota Tujuan Belum Diisi';
    $noticeMessage = 'Pesan uji';
    $noticeVariant = 'warning';
    $noticeHint = 'Petunjuk';
    $noticeBackUrl = '/spj';
    $noticeBackLabel = 'Kembali';
    ob_start();
    include __DIR__ . '/../views/shared/standalone_notice.php';
    return (string) ob_get_clean();
})();
check('render memuat judul', str_contains($html, 'Kota Tujuan Belum Diisi'));
check('render memuat tombol kembali', str_contains($html, 'href="/spj"'));

// ── 6. Validasi form rincian biaya (murni, tanpa DB) ─────────────────
echo PHP_EOL . "[6] RincianBiayaInputService (murni)" . PHP_EOL;
$inputSvc = new \App\Services\RincianBiayaInputService();

$default = $inputSvc->defaultKomponen();
check('defaultKomponen() 4 baris', count($default) === 4, count($default) . ' baris');
check('defaultKomponen() ada Uang Harian', ($default[0]['nama_komponen'] ?? '') === 'Uang Harian');

$okPayload = $inputSvc->parse([
    'ditetapkan_sejumlah' => '1.500.000',
    'dibayar_semula'      => '0',
    'tempat_tanggal'      => 'Bojonegoro, 1 Januari 2026',
    'komponen'            => [
        ['nama_komponen' => 'Uang Harian', 'harga_satuan' => '150.000', 'jumlah_hari' => '2', 'jumlah' => '', 'keterangan' => ''],
    ],
]);
check('parse() input valid tanpa error', $okPayload['errors'] === [], implode('; ', $okPayload['errors']));
check('parse() menghitung jumlah dari harga*hari', ($okPayload['details'][0]['jumlah'] ?? 0) == 300000.0);
check('parse() header float', ($okPayload['header']['ditetapkan_sejumlah'] ?? null) == 1500000.0);

$errPayload = $inputSvc->parse([
    'komponen' => [
        ['nama_komponen' => 'BBM', 'harga_satuan' => '0', 'jumlah_hari' => '', 'jumlah' => '0', 'keterangan' => ''],
    ],
]);
check('parse() menolak harga satuan 0', count($errPayload['errors']) > 0);

$emptyPayload = $inputSvc->parse(['komponen' => []]);
check('parse() menolak komponen kosong', count($emptyPayload['errors']) > 0);

// ── Ringkasan ────────────────────────────────────────────────────────
echo PHP_EOL . "== Selesai: {$pass} lulus, {$fail} gagal, {$skip} dilewati ==" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
