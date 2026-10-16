<?php
$flash = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$namaBulanMap = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];
$totalConfigured = count($configuredCounts ?? []);
?>
<div class="container-fluid px-4 py-3">
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : ($flashType === 'success' ? 'success' : 'info') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flash) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="bi bi-arrow-left-right text-primary me-2"></i>Transfer Perjalanan Dinas
            </h1>
            <p class="text-muted small mb-0">
                Tentukan penerima transfer dan potongan tiap pegawai pada suatu Surat Tugas.
                Penerimaan diambil otomatis dari transaksi perjalanan dinas; Excel gabungan berisi pivot Rekap + rincian Detail.
            </p>
        </div>
        <div class="d-flex gap-2">
            <form method="GET" action="<?= base_url('transfer-perjadin/export') ?>" class="d-flex align-items-center gap-2">
                <input type="number" name="tahun" class="form-control form-control-sm border" style="width:96px;" value="<?= htmlspecialchars((string) ($tahun ?? date('Y'))) ?>" placeholder="Tahun" title="Filter tahun (kosongkan untuk semua)">
                <button type="submit" class="btn btn-sm btn-success px-3" <?= $totalConfigured === 0 ? 'disabled' : '' ?>>
                    <i class="bi bi-file-earmark-excel me-1"></i>Unduh Excel
                </button>
            </form>
        </div>
    </div>

    <div class="alert alert-light border shadow-sm rounded-3 py-2 px-3 mb-4 d-flex align-items-center gap-2 flex-wrap" style="font-size:0.85rem;">
        <i class="bi bi-info-circle-fill text-primary"></i>
        <span>Surat Tugas ditampilkan: <strong><?= count($suratTugasList) ?></strong></span>
        <span class="text-muted">·</span>
        <span>Sudah dikonfigurasi transfer: <strong><?= $totalConfigured ?> Surat Tugas</strong></span>
    </div>

    <form method="GET" action="<?= base_url('transfer-perjadin') ?>" class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label small text-muted mb-1">Tahun</label>
                <input type="number" name="tahun" class="form-control form-control-sm" style="width:110px;" value="<?= htmlspecialchars((string) ($tahun ?? date('Y'))) ?>">
            </div>
            <div>
                <label class="form-label small text-muted mb-1">Bulan</label>
                <select name="bulan" class="form-select form-select-sm" style="width:150px;">
                    <option value="">Semua</option>
                    <?php foreach ($namaBulanMap as $num => $nm): ?>
                        <option value="<?= $num ?>" <?= (($bulan ?? null) === $num) ? 'selected' : '' ?>><?= $nm ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-grow-1" style="min-width:200px;">
                <label class="form-label small text-muted mb-1">Cari</label>
                <input type="text" name="q" class="form-control form-control-sm" value="<?= htmlspecialchars((string) ($q ?? '')) ?>" placeholder="Nomor surat atau maksud">
            </div>
            <button type="submit" class="btn btn-sm btn-primary px-3"><i class="bi bi-filter me-1"></i>Tampilkan</button>
            <a href="<?= base_url('transfer-perjadin') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>

    <?php if (!empty($stError)): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($stError) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white py-3 px-4 border-bottom">
            <h5 class="card-title fw-bold mb-0 text-dark"><i class="bi bi-list-check text-success me-2"></i>Daftar Surat Tugas</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($suratTugasList)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox display-6 d-block mb-2 opacity-50"></i>
                    Tidak ada Surat Tugas pada filter ini.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">No. Surat</th>
                                <th>Tanggal</th>
                                <th>Untuk</th>
                                <th class="text-center">Pegawai</th>
                                <th class="text-center">Status Transfer</th>
                                <th class="text-end pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suratTugasList as $st):
                                $stId = (int) $st['id'];
                                $jmlPegawai = (int) ($pegawaiCounts[$stId] ?? 0);
                                $configured = (int) ($configuredCounts[$stId] ?? 0);
                                ?>
                                <tr>
                                    <td class="ps-4 fw-semibold text-dark font-monospace"><?= htmlspecialchars((string) $st['nomor_surat']) ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars((string) ($st['tanggal_mulai'] ?? $st['tanggal_surat'] ?? '-')) ?></td>
                                    <td class="text-dark" style="max-width:320px;">
                                        <span class="d-inline-block text-truncate" style="max-width:100%;" title="<?= htmlspecialchars((string) ($st['untuk'] ?? '')) ?>">
                                            <?= htmlspecialchars((string) ($st['untuk'] ?? '-')) ?>
                                        </span>
                                    </td>
                                    <td class="text-center"><span class="badge bg-secondary-subtle text-secondary border"><?= $jmlPegawai ?></span></td>
                                    <td class="text-center">
                                        <?php if ($configured > 0): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>Sudah diatur (<?= $configured ?>)</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Belum diatur</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="<?= base_url('transfer-perjadin/atur/' . $stId) ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-sliders me-1"></i><?= $configured > 0 ? 'Ubah' : 'Atur' ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
