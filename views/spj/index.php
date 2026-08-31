<?php
/**
 * View: SPJ Perjalanan Dinas — Daftar Surat Tugas
 * Variabel dari controller: $suratTugasList, $stError
 */
$suratTugasList = $suratTugasList ?? [];
$stError        = $stError        ?? null;

$q     = htmlspecialchars($_GET['q']     ?? '');
$bulan = $_GET['bulan'] ?? '';
$tahun = $_GET['tahun'] ?? date('Y');

$namaBulanMap = [
    1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',
    5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',
    9=>'September',10=>'Oktober',11=>'November',12=>'Desember',
];

$flash    = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>

<div class="container-fluid py-4">

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : ($flashType === 'success' ? 'success' : 'info') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flash) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="page-header mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="mb-1"><i class="bi bi-briefcase text-primary me-2"></i>SPJ Perjalanan Dinas</h2>
                <p class="text-muted mb-0">Pilih Surat Tugas untuk mengisi rincian biaya perjalanan dinas per pegawai.</p>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body py-3">
            <form method="GET" action="<?= base_url('spj') ?>" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label fw-semibold mb-1"><i class="bi bi-calendar-month me-1 text-primary"></i>Bulan</label>
                    <select name="bulan" class="form-select" style="min-width:150px;">
                        <option value="">-- Semua --</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= (string)$bulan === (string)$m ? 'selected' : '' ?>><?= $namaBulanMap[$m] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label fw-semibold mb-1"><i class="bi bi-calendar-year me-1 text-primary"></i>Tahun</label>
                    <select name="tahun" class="form-select" style="min-width:110px;">
                        <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 4; $y--): ?>
                            <option value="<?= $y ?>" <?= (string)$tahun === (string)$y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label fw-semibold mb-1"><i class="bi bi-search me-1 text-primary"></i>Cari</label>
                    <input type="text" name="q" class="form-control" placeholder="Nomor / tujuan ST…" value="<?= $q ?>" style="min-width:220px;">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
                    <a href="<?= base_url('spj') ?>" class="btn btn-outline-secondary ms-1"><i class="bi bi-x-circle me-1"></i>Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Error dari db_surat_tugas -->
    <?php if ($stError): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($stError) ?></div>
    <?php endif; ?>

    <!-- Tabel Surat Tugas -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($suratTugasList)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox" style="font-size:2.5rem;"></i>
                    <p class="mt-2 mb-0">Tidak ada Surat Tugas ditemukan untuk filter ini.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="4%">No</th>
                                <th width="15%">Nomor ST</th>
                                <th width="10%">Tgl Surat</th>
                                <th width="10%">Pelaksanaan</th>
                                <th>Tujuan / Untuk</th>
                                <th width="12%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suratTugasList as $i => $st): ?>
                                <tr>
                                    <td class="text-muted" style="font-size:.8rem;"><?= $i + 1 ?></td>
                                    <td>
                                        <span class="fw-semibold" style="font-size:.85rem;"><?= htmlspecialchars($st['nomor_surat']) ?></span>
                                        <?php if (!empty($st['dasar_surat'])): ?>
                                            <div class="text-muted" style="font-size:.75rem;">Dasar: <?= htmlspecialchars($st['dasar_surat']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:.85rem;"><?= $st['tanggal_surat'] ? date('d/m/Y', strtotime($st['tanggal_surat'])) : '-' ?></td>
                                    <td style="font-size:.82rem;">
                                        <?= $st['tanggal_mulai'] ? date('d/m/Y', strtotime($st['tanggal_mulai'])) : '-' ?>
                                        <?php if (!empty($st['tanggal_selesai']) && $st['tanggal_selesai'] !== $st['tanggal_mulai']): ?>
                                            <span class="text-muted">–<br><?= date('d/m/Y', strtotime($st['tanggal_selesai'])) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:.85rem;"><?= htmlspecialchars($st['untuk'] ?? '-') ?></td>
                                    <td class="text-center">
                                        <a href="<?= base_url('spj/detail/' . $st['id']) ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-people me-1"></i>Lihat Pegawai
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
