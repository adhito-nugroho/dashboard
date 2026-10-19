<?php
$meta = $report['meta'] ?? [];
$rows = $report['rows'] ?? [];
$totals = $report['totals'] ?? ['pagu' => 0, 'periode' => 0, 'akumulasi' => 0, 'sisa' => 0];
$isPengajuan = !empty($meta['nomor_pengajuan']);
$statusBadge = [
    'diajukan' => ['Menunggu Cair', 'bg-warning text-dark border-warning'],
    'cair'     => ['Sudah Cair', 'bg-success-subtle text-success border-success-subtle'],
    'ditolak'  => ['Ditolak', 'bg-danger-subtle text-danger border-danger-subtle'],
];
$statusInfo = $statusBadge[$meta['status_pengajuan'] ?? ''] ?? null;

$rupiah = static fn(float $nilai): string => 'Rp' . number_format($nilai, 2, ',', '.');
$tanggal = static function (?string $value): string {
    if ($value === null || $value === '') {
        return '-';
    }
    $time = strtotime($value);
    return $time !== false ? date('d/m/Y', $time) : $value;
};
?>
<style>
.lpj-kop-title { font-size: 1.05rem; font-weight: 700; letter-spacing: .02em; }
.lpj-title { font-size: 1.15rem; font-weight: 800; letter-spacing: .03em; }
.lpj-table { font-size: .82rem; }
.lpj-table thead th {
    background: #1f4e79;
    color: #fff;
    text-align: center;
    vertical-align: middle;
    font-weight: 700;
    border-color: #1f4e79 !important;
}
.lpj-table td { vertical-align: middle; }
.lpj-hier { font-weight: 700; }
.lpj-hier > td { background-color: #ffffff !important; }
.lpj-rek-a > td { background-color: #fce4d6 !important; }
.lpj-rek-b > td { background-color: #f8cbad !important; }
.lpj-num { text-align: right; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; white-space: nowrap; }
.lpj-total { font-weight: 800; }
.lpj-total > td { background-color: #d9e1f2 !important; }
.lpj-total td { border-top: 2px solid #000 !important; border-bottom: 3px double #000 !important; }
@media print {
    #sidebar, .header, .no-print { display: none !important; }
    .main-content { margin-left: 0 !important; width: 100% !important; }
    .container-fluid { padding: 0 !important; }
    .card { border: 0 !important; box-shadow: none !important; }
    .card-body { padding: 0 !important; }
    .lpj-table { font-size: 9pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .lpj-table thead { display: table-header-group; }
    .lpj-table tr { page-break-inside: avoid; }
    @page { size: A4 landscape; margin: 10mm; }
}
</style>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3 no-print">
        <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>Cetak
            </button>
            <a href="<?= htmlspecialchars($excelUrl) ?>" class="btn btn-sm btn-success">
                <i class="bi bi-file-earmark-excel me-1"></i>Unduh Excel
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-4">
            <div class="text-center mb-1">
                <div class="lpj-kop-title">PEMERINTAH PROVINSI JAWA TIMUR</div>
                <div class="lpj-kop-title">DINAS KEHUTANAN</div>
                <div class="lpj-kop-title">CABANG DINAS KEHUTANAN WILAYAH BOJONEGORO</div>
            </div>
            <hr class="my-3">
            <h1 class="lpj-title text-center mb-3"><?= htmlspecialchars($report['judul'] ?? 'LAPORAN PERTANGGUNGJAWABAN LIMPAHAN UANG PERSEDIAAN') ?></h1>

            <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3" style="font-size:.9rem;">
                <div>
                    <?php if ($isPengajuan): ?>
                        <div>Nomor Pengajuan: <strong class="font-monospace"><?= htmlspecialchars((string) $meta['nomor_pengajuan']) ?></strong></div>
                        <div>Tanggal Pengajuan: <strong><?= htmlspecialchars($tanggal($meta['tanggal_pengajuan'] ?? null)) ?></strong></div>
                        <?php if (!empty($meta['keterangan'])): ?>
                            <div>Keterangan: <?= htmlspecialchars((string) $meta['keterangan']) ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div>Periode: <strong><?= htmlspecialchars((string) ($meta['periode_label'] ?? 'Seluruh periode')) ?></strong></div>
                    <?php endif; ?>
                </div>
                <div class="text-md-end">
                    <div>Tahun Anggaran: <strong><?= (int) ($meta['tahun'] ?? date('Y')) ?></strong></div>
                    <div>Akumulasi s.d.: <strong><?= htmlspecialchars($tanggal($meta['cutoff'] ?? null)) ?></strong></div>
                    <?php if ($statusInfo !== null): ?>
                        <div class="mt-1"><span class="badge <?= $statusInfo[1] ?> border px-2 py-1"><?= $statusInfo[0] ?></span></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-2" style="font-size:.8rem;">
                <span class="badge bg-light text-secondary border"><?= (int) ($meta['jumlah_transaksi'] ?? 0) ?> transaksi</span>
                <span class="badge bg-light text-secondary border"><?= (int) ($meta['jumlah_rekening'] ?? 0) ?> rekening</span>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-bordered lpj-table mb-0">
                    <colgroup>
                        <col style="width:12%;">
                        <col style="width:40%;">
                        <col style="width:12%;">
                        <col style="width:12%;">
                        <col style="width:12%;">
                        <col style="width:12%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Kode Rekening</th>
                            <th>Uraian</th>
                            <th>Jumlah Anggaran</th>
                            <th>Belanja Periode Ini</th>
                            <th>Akumulasi Belanja</th>
                            <th>Sisa Anggaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    Belum ada transaksi yang diajukan untuk SPJ GU pada periode ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $rekIndex = 0; ?>
                            <?php foreach ($rows as $node): ?>
                                <?php if (($node['type'] ?? '') !== 'rekening'): ?>
                                    <?php
                                    $label = trim((string) ($node['kode'] ?? '') . ' - ' . (string) ($node['nama'] ?? ''), ' -');
                                    ?>
                                    <tr class="lpj-hier">
                                        <td colspan="6"><?= htmlspecialchars($label) ?></td>
                                    </tr>
                                <?php else: ?>
                                    <?php $rekClass = $rekIndex % 2 === 0 ? 'lpj-rek-a' : 'lpj-rek-b'; $rekIndex++; ?>
                                    <tr class="<?= $rekClass ?>">
                                        <td class="font-monospace"><?= htmlspecialchars((string) ($node['kode'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars((string) ($node['nama'] ?? '')) ?></td>
                                        <td class="lpj-num"><?= $rupiah((float) ($node['pagu'] ?? 0)) ?></td>
                                        <td class="lpj-num"><?= $rupiah((float) ($node['periode'] ?? 0)) ?></td>
                                        <td class="lpj-num"><?= $rupiah((float) ($node['akumulasi'] ?? 0)) ?></td>
                                        <td class="lpj-num"><?= $rupiah((float) ($node['sisa'] ?? 0)) ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="lpj-total">
                            <td colspan="2" class="text-center">TOTAL</td>
                            <td class="lpj-num"><?= $rupiah((float) ($totals['pagu'] ?? 0)) ?></td>
                            <td class="lpj-num"><?= $rupiah((float) ($totals['periode'] ?? 0)) ?></td>
                            <td class="lpj-num"><?= $rupiah((float) ($totals['akumulasi'] ?? 0)) ?></td>
                            <td class="lpj-num"><?= $rupiah((float) ($totals['sisa'] ?? 0)) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="text-muted mt-2" style="font-size:.75rem;">
                Catatan: Sisa Anggaran = Jumlah Anggaran - Akumulasi Belanja. Belanja Periode Ini berasal dari transaksi
                UP terverifikasi yang sudah diajukan ke SPJ GU; Akumulasi Belanja mencakup seluruh transaksi terverifikasi
                (UP/LS) s.d. tanggal cutoff.
            </div>
        </div>
    </div>
</div>
