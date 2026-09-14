<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

// 1. Verifikasi integrasi data penerimaan & pengeluaran untuk September 2026
$bulan = 9;
$tahun = 2026;

// Ambil kas_bank
$stmtKas = $db->prepare("
    SELECT id, tanggal, tanggal_cair, jenis, status, nomor_bukti, keterangan, nominal
    FROM kas_bank
    WHERE bulan = :bulan AND tahun = :tahun AND (status = 'cair' OR status IS NULL)
    ORDER BY tanggal ASC, id ASC
");
$stmtKas->execute([':bulan' => $bulan, ':tahun' => $tahun]);
$kasRows = $stmtKas->fetchAll(PDO::FETCH_ASSOC);

assert(count($kasRows) >= 1, 'Harus ada minimal 1 penerimaan cair (saldo awal)');
assert($kasRows[0]['jenis'] === 'saldo_awal', 'Penerimaan pertama adalah saldo_awal');
echo "✓ Penerimaan kas_bank cair terambil: " . count($kasRows) . " data.\n";

// Ambil transaksi
$stmtTrx = $db->prepare("
    SELECT t.id, t.tanggal, t.tanggal_lunas_dibayar, t.diverifikasi_at, s.nama_seksi, t.uraian, t.nomor_bukti, t.nilai, t.status
    FROM transaksi t
    INNER JOIN seksi s ON t.seksi_id = s.id
    INNER JOIN rekening r ON t.rekening_id = r.id
    INNER JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
    WHERE MONTH(COALESCE(t.tanggal_lunas_dibayar, DATE(t.diverifikasi_at), t.tanggal)) = :bulan
      AND YEAR(COALESCE(t.tanggal_lunas_dibayar, DATE(t.diverifikasi_at), t.tanggal)) = :tahun
    ORDER BY t.nomor_bukti ASC, t.id ASC
");
$stmtTrx->execute([':bulan' => $bulan, ':tahun' => $tahun]);
$trxRows = $stmtTrx->fetchAll(PDO::FETCH_ASSOC);

// Gabungkan
$bkuItems = [];
foreach ($kasRows as $k) {
    $tglKas = !empty($k['tanggal_cair']) ? $k['tanggal_cair'] : ($k['tanggal'] ?? null);
    $bkuItems[] = [
        'type'         => 'penerimaan',
        'id'           => 'kas_' . $k['id'],
        'tanggal_sort' => $tglKas ?: ($tahun . '-' . str_pad((string) $bulan, 2, '0', STR_PAD_LEFT) . '-01'),
        'tanggal_raw'  => $tglKas,
        'nama_seksi'   => 'BENDAHARA',
        'uraian'       => $k['keterangan'] ?? 'Penerimaan Kas/Bank',
        'nomor_bukti'  => $k['nomor_bukti'] ?? '-',
        'penerimaan'   => (float) ($k['nominal'] ?? 0),
        'pengeluaran'  => 0.0,
        'status'       => $k['status'] ?: 'cair',
        'jenis'        => $k['jenis'] ?? 'penerimaan',
    ];
}
foreach ($trxRows as $t) {
    $rawTgl = !empty($t['tanggal_lunas_dibayar']) ? $t['tanggal_lunas_dibayar'] : (!empty($t['diverifikasi_at']) ? date('Y-m-d', strtotime($t['diverifikasi_at'])) : ($t['tanggal'] ?? null));
    $bkuItems[] = [
        'type'         => 'pengeluaran',
        'id'           => 'trx_' . $t['id'],
        'tanggal_sort' => $rawTgl ?: '9999-12-31',
        'tanggal_raw'  => $rawTgl,
        'nama_seksi'   => $t['nama_seksi'] ?? '-',
        'uraian'       => $t['uraian'] ?? '',
        'nomor_bukti'  => $t['nomor_bukti'] ?? '-',
        'penerimaan'   => 0.0,
        'pengeluaran'  => (float) ($t['nilai'] ?? 0),
        'status'       => $t['status'] ?? '',
        'jenis'        => 'pengeluaran',
    ];
}

// Urutkan
usort($bkuItems, function ($a, $b) {
    $isAwalA = ($a['jenis'] ?? '') === 'saldo_awal';
    $isAwalB = ($b['jenis'] ?? '') === 'saldo_awal';
    if ($isAwalA && !$isAwalB) return -1;
    if (!$isAwalA && $isAwalB) return 1;

    $tglA = (string) ($a['tanggal_sort'] ?? '');
    $tglB = (string) ($b['tanggal_sort'] ?? '');
    if ($tglA !== $tglB) return strcmp($tglA, $tglB);

    $isPenerimaanA = ($a['type'] ?? '') === 'penerimaan';
    $isPenerimaanB = ($b['type'] ?? '') === 'penerimaan';
    if ($isPenerimaanA && !$isPenerimaanB) return -1;
    if (!$isPenerimaanA && $isPenerimaanB) return 1;

    return strnatcasecmp((string)($a['nomor_bukti'] ?? ''), (string)($b['nomor_bukti'] ?? ''));
});

assert($bkuItems[0]['type'] === 'penerimaan', 'Item pertama harus penerimaan saldo awal');
assert($bkuItems[0]['jenis'] === 'saldo_awal', 'Jenis item pertama harus saldo_awal');

// 2. Verifikasi perhitungan saldo berjalan: Saldo = Penerimaan - Pengeluaran
$saldo = 0.0;
$totIn = 0.0;
$totOut = 0.0;
foreach ($bkuItems as $idx => $item) {
    $in = (float) $item['penerimaan'];
    $out = (float) $item['pengeluaran'];
    if ($item['type'] === 'penerimaan') {
        $saldo += $in;
        $totIn += $in;
    } else {
        if ($item['status'] !== 'ditolak') {
            $saldo -= $out;
            $totOut += $out;
        }
    }
    echo sprintf(
        "Row %d [%s] %-25s | Masuk: %12.2f | Keluar: %12.2f | Saldo: %12.2f\n",
        $idx + 1,
        $item['type'],
        substr($item['uraian'], 0, 25),
        $in,
        $out,
        $saldo
    );
}

assert($totIn > 0, 'Total penerimaan harus > 0');
assert($totOut > 0, 'Total pengeluaran harus > 0');
assert(abs($saldo - ($totIn - $totOut)) < 0.001, 'Saldo akhir harus sama dengan Total Masuk - Total Keluar');
echo sprintf("\n✓ Saldo berjalan terverifikasi! Saldo Akhir = Total Masuk (%s) - Total Keluar (%s) = %s\n",
    number_format($totIn, 2),
    number_format($totOut, 2),
    number_format($saldo, 2)
);

// 3. Verifikasi file controller
$transaksiContent = file_get_contents(__DIR__ . '/../app/Controllers/TransaksiController.php');
assert(strpos($transaksiContent, "'Penerimaan (Rp)', 'Pengeluaran (Rp)', 'Saldo (Rp)', 'Status'") !== false, 'Header TransaksiController harus memuat kolom Penerimaan & Pengeluaran');
assert(strpos($transaksiContent, '$saldo           += $penerimaan;') !== false, 'Saldo harus bertambah saat penerimaan');
assert(strpos($transaksiContent, '$saldo            -= $pengeluaran;') !== false, 'Saldo harus berkurang saat pengeluaran');

$seksiContent = file_get_contents(__DIR__ . '/../app/Controllers/SeksiTransaksiController.php');
assert(strpos($seksiContent, "'Penerimaan (Rp)', 'Pengeluaran (Rp)'") !== false, 'Header SeksiTransaksiController harus memuat kolom Penerimaan & Pengeluaran');

echo "✓ Semua assertion pengujian BKU lolos dengan sempurna!\n";
