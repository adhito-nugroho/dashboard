<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$bulanNames = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
$rakPersen = $stats['total_pagu'] > 0 ? ($stats['total_rak'] / $stats['total_pagu']) * 100 : 0;
$sisaPersen = $stats['total_pagu'] > 0 ? ($stats['sisa_anggaran'] / $stats['total_pagu']) * 100 : 0;
$bulanBerjalan = $stats['tahun'] == (int) date('Y') ? (int) date('n') : 12;
$bulanBerjalanDetail = $bulanBerjalan; // alias dipakai di tabel detail & chart JS
$targetRakBulanBerjalan = 0;
$serapanBulanBerjalan = 0;
for ($i = 1; $i <= $bulanBerjalan; $i++) {
    $targetRakBulanBerjalan += (float) ($monthlyData['rak'][$i] ?? 0);
    $serapanBulanBerjalan += (float) ($monthlyData['realisasi'][$i] ?? 0);
}
$capaianRakBulanBerjalan = $targetRakBulanBerjalan > 0 ? ($serapanBulanBerjalan / $targetRakBulanBerjalan) * 100 : 0;
$deviasiRakBulanBerjalan = $serapanBulanBerjalan - $targetRakBulanBerjalan;
$komparasiRakColor = $targetRakBulanBerjalan <= 0 ? 'secondary' : ($capaianRakBulanBerjalan > 110 ? 'primary' : ($capaianRakBulanBerjalan >= 90 ? 'success' : ($capaianRakBulanBerjalan >= 50 ? 'warning' : 'danger')));
?>

<!-- Sticky Section Navigation (3 Logical Groups) -->
<nav id="stickySecNav" class="sticky-sec-nav" aria-label="Navigasi seksi halaman">
    <div class="sticky-sec-nav__inner">
        <!-- Grup 1: Ringkasan -->
        <div class="sticky-sec-nav__group">
            <span class="sticky-sec-nav__group-tag">Ringkasan</span>
            <a href="#section-ringkasan" class="sticky-sec-nav__link active"><i class="bi bi-speedometer2"></i> Ringkasan</a>
            <a href="#section-grafik" class="sticky-sec-nav__link"><i class="bi bi-graph-up"></i> Grafik Bulanan</a>
            <a href="#section-komposisi" class="sticky-sec-nav__link"><i class="bi bi-pie-chart"></i> Komposisi</a>
        </div>

        <span class="sticky-sec-nav__divider" aria-hidden="true"></span>

        <!-- Grup 2: Analisis -->
        <div class="sticky-sec-nav__group">
            <span class="sticky-sec-nav__group-tag">Analisis</span>
            <a href="#section-detail-bulan" class="sticky-sec-nav__link"><i class="bi bi-calendar3"></i> Detail per Bulan</a>
            <a href="#section-serapan-rekening" class="sticky-sec-nav__link"><i class="bi bi-grid-3x3-gap"></i> Serapan Bulanan</a>
            <a href="#section-sisa-semester" class="sticky-sec-nav__link"><i class="bi bi-wallet2"></i> Sisa Semester</a>
            <a href="#section-breakdown" class="sticky-sec-nav__link"><i class="bi bi-bar-chart-steps"></i> Breakdown Seksi</a>
        </div>

        <span class="sticky-sec-nav__divider" aria-hidden="true"></span>

        <!-- Grup 3: Perencanaan -->
        <div class="sticky-sec-nav__group">
            <span class="sticky-sec-nav__group-tag">Perencanaan</span>
            <a href="#section-struktur" class="sticky-sec-nav__link"><i class="bi bi-diagram-3"></i> Struktur Anggaran</a>
            <a href="#section-deviasi" class="sticky-sec-nav__link"><i class="bi bi-exclamation-triangle"></i> Deviasi RAK</a>
        </div>
    </div>
</nav>

<style>
:root {
    --status-success: #059669;
    --status-success-bg: #ecfdf5;
    --status-success-border: #bbf7d0;
    --status-warning: #d97706;
    --status-warning-bg: #fffbeb;
    --status-warning-border: #fde68a;
    --status-danger: #dc2626;
    --status-danger-bg: #fef2f2;
    --status-danger-border: #fecaca;
    --status-neutral: #94a3b8;
    --status-neutral-bg: #f1f5f9;
    --status-neutral-border: #e2e8f0;

    --fs-xs: 0.68rem;
    --fs-sm: 0.75rem;
    --fs-base: 0.85rem;
    --fs-md: 0.95rem;
    --fs-lg: 1.1rem;
    --fs-xl: 1.35rem;
}
</style>
<div class="container-fluid" style="padding: 1.75rem 1.5rem;">
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 animate-fade-in-up">
        <div>
            <h2 style="font-weight:800;letter-spacing:-0.03em;color:var(--gray-900);font-size:var(--fs-xl);margin-bottom:0.2rem;">
                <i class="bi bi-speedometer2 me-2" style="color:var(--primary);"></i>Dashboard
            </h2>
            <p style="color:var(--gray-400);font-size:var(--fs-base);margin:0;">Monitoring Realisasi Anggaran Cabang Dinas Kehutanan Wilayah Bojonegoro &mdash; Tahun <strong style="color:var(--gray-600);"><?= $stats['tahun'] ?></strong></p>
        </div>
        <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
        <form method="GET" action="<?= base_url() ?>" class="" style="background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-full);padding:0.35rem 0.5rem 0.35rem 1rem;display:inline-flex;align-items:center;gap:0.5rem;box-shadow:var(--shadow-xs);">
            <i class="bi bi-calendar3" style="color:var(--primary);font-size:var(--fs-md);"></i>
            <select class="form-select form-select-sm border-0 bg-transparent fw-bold py-0" id="tahun" name="tahun" onchange="this.form.submit()" style="width:auto;cursor:pointer;box-shadow:none;color:var(--primary);font-size:var(--fs-base);">
                <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                <option value="<?= $y ?>" <?= $stats['tahun'] == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
        <button type="button" class="btn btn-sm fw-semibold btn-export-global"
           data-bs-toggle="modal" data-bs-target="#modalExportExcel"
           data-bs-toggle="tooltip" data-bs-placement="bottom" title="Export laporan lengkap semua section ke Excel"
           style="background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;border-radius:var(--radius-full);padding:0.4rem 1.1rem;font-size:var(--fs-sm, 0.75rem);box-shadow:0 2px 8px rgba(22,163,74,.3);display:inline-flex;align-items:center;gap:0.4rem;border:none;cursor:pointer;">
            <i class="bi bi-file-earmark-excel-fill"></i> Export Excel
        </button>
        </div>
    </div>

    <!-- Insight Banner -->
    <?php
    // Hitung jumlah bulan under-RAK & over-RAK untuk bulan yang sudah berjalan
    $underAlertsCount = 0;
    $overAlertsCount = 0;
    if (!empty($monthlyData['alerts'])) {
        $underAlertsCount = count(array_filter($monthlyData['alerts'], fn($a) =>
            $a['type'] === 'under' && $a['bulan'] <= $bulanBerjalan
        ));
        $overAlertsCount = count(array_filter($monthlyData['alerts'], fn($a) =>
            $a['type'] === 'over' && $a['bulan'] <= $bulanBerjalan
        ));
    }

    // Cek apakah ada over-RAK signifikan (selisih > 20% dari RAK bulan tsb) dalam 2 bulan terakhir
    $hasSignificantOverLast2Months = false;
    $startCheckMonth = max(1, $bulanBerjalan - 1);
    for ($m = $startCheckMonth; $m <= $bulanBerjalan; $m++) {
        $mRak = (float)($monthlyData['rak'][$m] ?? 0);
        $mReal = (float)($monthlyData['realisasi'][$m] ?? 0);
        if ($mRak > 0 && (($mReal - $mRak) / $mRak) > 0.20) {
            $hasSignificantOverLast2Months = true;
            break;
        }
    }

    // Tentukan pesan dan styling banner berdasarkan capaian terhadap target RAK kumulatif
    $iTextColor = '#ffffff';
    $iIconBg = 'rgba(255,255,255,0.2)';

    if ($stats['percentage'] > 100) {
        $iIcon = 'exclamation-octagon-fill';
        $iText = 'Perhatian! Realisasi anggaran telah <strong>melebihi pagu</strong>.';
        $iBg = 'linear-gradient(135deg, #dc2626 0%, #ef4444 100%)';
        $iBorder = '#dc2626';
    } elseif ($targetRakBulanBerjalan > 0) {
        // Jika ada target RAK s/d bulan berjalan
        if ($capaianRakBulanBerjalan >= 90 && $underAlertsCount < 3) {
            $iIcon = 'check-circle-fill';
            $iText = 'Realisasi anggaran berjalan sesuai target.';
            $iBg = 'linear-gradient(135deg, #059669 0%, #10b981 100%)';
            $iBorder = '#059669';
        } elseif ($capaianRakBulanBerjalan >= 70 && $underAlertsCount < 3) {
            $iIcon = 'exclamation-triangle-fill';
            $iText = 'Realisasi anggaran sedikit di bawah target, perlu percepatan.';
            $iBg = 'linear-gradient(135deg, #d97706 0%, #f59e0b 100%)';
            $iBorder = '#d97706';
        } elseif ($underAlertsCount >= 3 && $capaianRakBulanBerjalan >= 70) {
            $iIcon = 'exclamation-triangle-fill';
            $iText = 'Realisasi anggaran sedikit di bawah target, perlu percepatan.';
            $iBg = 'linear-gradient(135deg, #d97706 0%, #f59e0b 100%)';
            $iBorder = '#d97706';
        } else {
            $iIcon = 'exclamation-octagon-fill';
            $iText = 'Realisasi anggaran tertinggal signifikan dari target RAK.';
            $iBg = 'linear-gradient(135deg, #dc2626 0%, #ef4444 100%)';
            $iBorder = '#dc2626';
        }
    } else {
        // Fallback jika belum ada konfigurasi RAK bulan berjalan
        if ($stats['percentage'] >= 90) {
            $iIcon = 'exclamation-triangle-fill';
            $iText = 'Realisasi mendekati pagu total! Harap tinjau pengeluaran.';
            $iBg = 'linear-gradient(135deg, #d97706 0%, #f59e0b 100%)';
            $iBorder = '#d97706';
        } elseif ($stats['percentage'] < 20 && $bulanBerjalan > 3) {
            $iIcon = 'exclamation-octagon-fill';
            $iText = 'Realisasi anggaran tertinggal signifikan dari target.';
            $iBg = 'linear-gradient(135deg, #dc2626 0%, #ef4444 100%)';
            $iBorder = '#dc2626';
        } else {
            $iIcon = 'check-circle-fill';
            $iText = 'Realisasi anggaran berjalan normal.';
            $iBg = 'linear-gradient(135deg, #059669 0%, #10b981 100%)';
            $iBorder = '#059669';
        }
    }

    // Tambahkan catatan jika ada bulan over-RAK signifikan dalam 2 bulan terakhir
    if ($hasSignificantOverLast2Months && $stats['percentage'] <= 100) {
        $iText = rtrim($iText, '.') . ', beberapa bulan terakhir realisasi melebihi RAK.';
    }
    ?>
    <div class="insight-banner mb-4 animate-fade-in-up delay-1" style="background:<?= $iBg ?>;border-color:<?= $iBorder ?>;color:<?= $iTextColor ?>;">
        <div class="insight-icon" style="background:<?= $iIconBg ?>;color:#fff;border:1.5px solid rgba(255,255,255,0.3);"><i class="bi bi-<?= $iIcon ?>"></i></div>
        <div style="flex:1;">
            <strong>Insight:</strong> <?= $iText ?>
            <span class="d-none d-md-inline ms-2" style="opacity:0.9;">&mdash; Serapan saat ini <strong style="font-size:var(--fs-md);"><?= number_format($stats['percentage'], 2) ?>%</strong></span>
        </div>
        <button type="button" class="btn-close btn-close-white" style="font-size:var(--fs-xs);opacity:0.8;" onclick="this.closest('.insight-banner').remove()"></button>
    </div>

    <!-- Filter Panel -->
    <?php
        $activeFilterCount = (!empty($filters['seksi_id'])?1:0) + (!empty($filters['program_id'])?1:0) + (!empty($filters['kegiatan_id'])?1:0) + (!empty($filters['sub_kegiatan_id'])?1:0);
        $hasActiveFilter = $activeFilterCount > 0;
    ?>
    <div class="d-md-none mb-3"><button class="btn btn-primary w-100 rounded-3" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse"><i class="bi bi-sliders me-2"></i>Filter Data<?php if($hasActiveFilter): ?> <span style="background:#fff;color:#3b82f6;font-size:var(--fs-xs);font-weight:700;padding:0.1rem 0.45rem;border-radius:999px;margin-left:0.4rem;"><?= $activeFilterCount ?></span><?php endif; ?></button></div>
    <div class="collapse d-md-block mb-4 animate-fade-in-up delay-2" id="filterCollapse">
        <div class="card filter-card rounded-4" style="box-shadow:var(--shadow-sm);<?= $hasActiveFilter?'border:1.5px solid #3b82f6;':'' ?>">
            <div class="card-body" style="padding:1.25rem 1.5rem;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-sliders" style="color:var(--primary);font-size:var(--fs-md);"></i>
                        <span style="font-weight:700;font-size:var(--fs-base);color:var(--gray-700);">Filter Data</span>
                        <?php if($hasActiveFilter): ?>
                        <span style="font-size:var(--fs-xs);font-weight:700;padding:0.2rem 0.6rem;border-radius:999px;background:#3b82f6;color:#fff;">Filter Aktif: <?= $activeFilterCount ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if($hasActiveFilter): ?>
                    <button class="btn btn-sm" style="background:#fef2f2;color:#dc2626;font-size:var(--fs-sm);font-weight:600;border:1px solid #fecaca;border-radius:999px;padding:0.25rem 0.75rem;" onclick="resetFilters()"><i class="bi bi-x-circle-fill me-1"></i>Reset Filter</button>
                    <?php else: ?>
                    <button class="btn btn-sm" style="color:var(--gray-400);font-size:var(--fs-sm);" onclick="resetFilters()"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
                    <?php endif; ?>
                </div>
                <form method="GET" action="<?= base_url() ?>" id="mainFilterForm">
                    <input type="hidden" name="tahun" value="<?= $stats['tahun'] ?>">
                    <div class="row g-2">
                        <div class="col-md-3"><label for="seksi_id" class="form-label">Seksi</label><select class="form-select<?= !empty($filters['seksi_id'])?' border-primary':'' ?>" id="seksi_id" name="seksi_id" onchange="this.form.submit()" style="<?= !empty($filters['seksi_id'])?'border-color:#3b82f6;background-color:#eff6ff;box-shadow:0 0 0 1px #3b82f6;':'' ?>"><option value="">Semua Seksi</option><?php foreach($filterOptions['seksi'] as $s): ?><option value="<?= $s['id'] ?>" <?= ($filters['seksi_id']??'')==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['nama_seksi']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-3"><label for="program_id" class="form-label">Program</label><select class="form-select" id="program_id" name="program_id" onchange="this.form.submit()" style="<?= !empty($filters['program_id'])?'border-color:#3b82f6;background-color:#eff6ff;box-shadow:0 0 0 1px #3b82f6;':'' ?>"><option value="">Semua Program</option><?php foreach($filterOptions['program'] as $p): ?><option value="<?= $p['id'] ?>" <?= ($filters['program_id']??'')==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['kode_program'].' - '.$p['nama_program']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-3"><label for="kegiatan_id" class="form-label">Kegiatan</label><select class="form-select" id="kegiatan_id" name="kegiatan_id" onchange="this.form.submit()" style="<?= !empty($filters['kegiatan_id'])?'border-color:#3b82f6;background-color:#eff6ff;box-shadow:0 0 0 1px #3b82f6;':'' ?>"><option value="">Semua Kegiatan</option><?php foreach($filterOptions['kegiatan'] as $k): ?><option value="<?= $k['id'] ?>" <?= ($filters['kegiatan_id']??'')==$k['id']?'selected':'' ?>><?= htmlspecialchars($k['kode_kegiatan'].' - '.$k['nama_kegiatan']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-3"><label for="sub_kegiatan_id" class="form-label">Sub Kegiatan</label><select class="form-select" id="sub_kegiatan_id" name="sub_kegiatan_id" onchange="this.form.submit()" style="<?= !empty($filters['sub_kegiatan_id'])?'border-color:#3b82f6;background-color:#eff6ff;box-shadow:0 0 0 1px #3b82f6;':'' ?>"><option value="">Semua Sub Kegiatan</option><?php foreach($filterOptions['sub_kegiatan'] as $sk): ?><option value="<?= $sk['id'] ?>" <?= ($filters['sub_kegiatan_id']??'')==$sk['id']?'selected':'' ?>><?= htmlspecialchars($sk['kode_sub_kegiatan'].' - '.$sk['nama_sub_kegiatan']) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <?php if($hasActiveFilter): ?>
                    <div class="mt-2 d-flex flex-wrap gap-1 align-items-center">
                        <span style="font-size:var(--fs-xs);color:#475569;font-weight:600;margin-right:0.25rem;">Menampilkan data untuk:</span>
                        <?php if(!empty($filters['seksi_id'])): ?><span class="filter-chip" style="background:#eff6ff;border-color:#bfdbfe;color:#1e40af;"><i class="bi bi-building"></i>Seksi</span><?php endif; ?>
                        <?php if(!empty($filters['program_id'])): ?><span class="filter-chip" style="background:#eff6ff;border-color:#bfdbfe;color:#1e40af;"><i class="bi bi-folder"></i>Program</span><?php endif; ?>
                        <?php if(!empty($filters['kegiatan_id'])): ?><span class="filter-chip" style="background:#eff6ff;border-color:#bfdbfe;color:#1e40af;"><i class="bi bi-list-task"></i>Kegiatan</span><?php endif; ?>
                        <?php if(!empty($filters['sub_kegiatan_id'])): ?><span class="filter-chip" style="background:#eff6ff;border-color:#bfdbfe;color:#1e40af;"><i class="bi bi-list-nested"></i>Sub Kegiatan</span><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row mb-4 g-3" id="section-ringkasan">
        <div class="col-xl-3 col-md-6 animate-fade-in-up delay-1">
            <div class="card kpi-card kpi-border-primary h-100" data-bs-toggle="tooltip" title="Total anggaran yang dialokasikan tahun <?= $stats['tahun'] ?>">
                <div class="card-body" style="padding:1.25rem;">
                    <div class="d-flex align-items-start gap-3">
                        <div class="kpi-icon" style="background:var(--primary-50);color:var(--primary);"><i class="bi bi-cash-stack"></i></div>
                        <div style="flex:1;min-width:0;">
                            <div class="kpi-label">Total Pagu</div>
                            <div class="kpi-value">Rp <?= number_format($stats['total_pagu'], 0, ',', '.') ?></div>
                            <div class="kpi-sublabel mt-1"><i class="bi bi-calendar-event me-1"></i>Anggaran <?= $stats['tahun'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 animate-fade-in-up delay-2">
            <div class="card kpi-card kpi-border-info h-100" data-bs-toggle="tooltip" title="Komparasi serapan kumulatif dengan target RAK s/d bulan berjalan">
                <div class="card-body" style="padding:1.25rem;">
                    <div class="d-flex align-items-start gap-3">
                        <div class="kpi-icon" style="background:#ecfeff;color:var(--info);"><i class="bi bi-calendar-check"></i></div>
                        <div style="flex:1;min-width:0;">
                            <div class="kpi-label">Serapan vs RAK <small style="font-size:0.7em;font-weight:600;color:var(--gray-400);">s/d <?= strtoupper($bulanNames[$bulanBerjalan]) ?></small></div>
                            <div class="kpi-value" style="color:var(--<?= $komparasiRakColor ?>);"><?= number_format($capaianRakBulanBerjalan, 2) ?>%</div>
                            <div class="mt-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height:5px;border-radius:3px;background:var(--gray-100);">
                                        <div class="progress-bar bg-<?= $komparasiRakColor ?>" style="width:<?= min($capaianRakBulanBerjalan,100) ?>%;border-radius:3px;"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="kpi-sublabel mt-1">
                                <?php if ($deviasiRakBulanBerjalan > 0): ?>
                                <i class="bi bi-arrow-up-circle" style="color:#2563eb;"></i>
                                <span style="color:#2563eb;font-weight:600;">+Rp <?= number_format(abs($deviasiRakBulanBerjalan),0,',','.') ?> (Over RAK)</span>
                                <?php elseif ($deviasiRakBulanBerjalan < 0): ?>
                                <i class="bi bi-arrow-down-circle" style="color:#dc2626;"></i>
                                <span style="color:#dc2626;font-weight:600;">-Rp <?= number_format(abs($deviasiRakBulanBerjalan),0,',','.') ?> (Under RAK)</span>
                                <?php else: ?>
                                <i class="bi bi-dash-circle" style="color:var(--gray-500);"></i>
                                <span style="color:var(--gray-500);font-weight:600;">Sesuai Target RAK</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 animate-fade-in-up delay-3">
            <?php $pctColor = $stats['percentage'] > 100 ? 'danger' : ($stats['percentage'] > 80 ? 'warning' : 'success'); ?>
            <div class="card kpi-card kpi-border-success h-100" data-bs-toggle="tooltip" title="Total realisasi anggaran">
                <div class="card-body" style="padding:1.25rem;">
                    <div class="d-flex align-items-start gap-3">
                        <div class="kpi-icon" style="background:#ecfdf5;color:var(--success);"><i class="bi bi-receipt-cutoff"></i></div>
                        <div style="flex:1;min-width:0;">
                            <div class="kpi-label">Realisasi</div>
                            <div class="kpi-value">Rp <?= number_format($stats['total_realisasi'], 0, ',', '.') ?></div>
                            <div class="mt-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height:5px;border-radius:3px;background:var(--gray-100);">
                                        <div class="progress-bar bg-<?= $pctColor ?>" style="width:<?= min($stats['percentage'],100) ?>%;border-radius:3px;"></div>
                                    </div>
                                    <span style="font-size:var(--fs-xs);font-weight:700;color:var(--<?= $pctColor ?>);"><?= number_format($stats['percentage'], 2) ?>%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 animate-fade-in-up delay-4">
            <?php $sisaColor = $stats['sisa_anggaran'] < 0 ? 'danger' : 'warning'; ?>
            <div class="card kpi-card kpi-border-<?= $sisaColor ?> h-100" data-bs-toggle="tooltip" title="Sisa dana tersedia">
                <div class="card-body" style="padding:1.25rem;">
                    <div class="d-flex align-items-start gap-3">
                        <div class="kpi-icon" style="background:<?= $stats['sisa_anggaran']<0?'#fef2f2':'#fffbeb' ?>;color:var(--<?= $sisaColor ?>);"><i class="bi bi-wallet2"></i></div>
                        <div style="flex:1;min-width:0;">
                            <div class="kpi-label">Sisa Anggaran</div>
                            <div class="kpi-value" style="color:var(--<?= $sisaColor ?>);">Rp <?= number_format(abs($stats['sisa_anggaran']), 0, ',', '.') ?></div>
                            <div class="kpi-sublabel mt-1"><?= $stats['sisa_anggaran']<0?'<i class="bi bi-exclamation-triangle me-1" style="color:var(--danger);"></i><span style="color:var(--danger);font-weight:600;">Over Budget</span>':'<i class="bi bi-shield-check me-1" style="color:var(--success);"></i><span style="color:var(--success);font-weight:600;">Tersedia</span>' ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Proyeksi Akhir Tahun -->
    <?php
        // Hitung proyeksi: rata-rata realisasi per bulan (bulan yang sudah ada data) × 12
        $bulanDenganData = 0;
        $totalRealisasiProyeksi = 0;
        for ($pb = 1; $pb <= 12; $pb++) {
            $mReal = $monthlyData['realisasi'][$pb] ?? 0;
            if ($mReal > 0) {
                $bulanDenganData++;
                $totalRealisasiProyeksi += $mReal;
            }
        }
        $rataRataPerBulan = $bulanDenganData > 0 ? $totalRealisasiProyeksi / $bulanDenganData : 0;
        $proyeksiAkhirTahun = $rataRataPerBulan * 12;
        $proyeksiPct = $stats['total_pagu'] > 0 ? ($proyeksiAkhirTahun / $stats['total_pagu']) * 100 : 0;
        $proyeksiStatus = $proyeksiPct >= 90 ? 'On Track' : ($proyeksiPct >= 60 ? 'Perlu Perhatian' : 'Tidak Mencapai Target');
        $proyeksiColor = $proyeksiPct >= 90 ? 'var(--status-success, #059669)' : ($proyeksiPct >= 60 ? 'var(--status-warning, #d97706)' : 'var(--status-danger, #dc2626)');
        $proyeksiBg = $proyeksiPct >= 90 ? 'var(--status-success-bg, #ecfdf5)' : ($proyeksiPct >= 60 ? 'var(--status-warning-bg, #fffbeb)' : 'var(--status-danger-bg, #fef2f2)');
        $proyeksiBorder = $proyeksiPct >= 90 ? 'var(--status-success-border, #bbf7d0)' : ($proyeksiPct >= 60 ? 'var(--status-warning-border, #fde68a)' : 'var(--status-danger-border, #fecaca)');
    ?>
    <?php if ($bulanDenganData > 0): ?>
    <div class="row mb-4 g-3">
        <div class="col-12 animate-fade-in-up delay-5">
            <div class="card rounded-4" style="background:<?= $proyeksiBg ?>;border:1px solid <?= $proyeksiBorder ?>;">
                <div class="card-body" style="padding:1rem 1.5rem;">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div style="width:42px;height:42px;border-radius:var(--radius-md);background:<?= $proyeksiColor ?>;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:var(--fs-lg);">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        <div style="flex:1;min-width:200px;">
                            <div style="font-size:var(--fs-sm);font-weight:700;color:<?= $proyeksiColor ?>;text-transform:uppercase;letter-spacing:0.04em;">Proyeksi Akhir Tahun</div>
                            <div style="font-size:var(--fs-lg);font-weight:800;color:#0f172a;margin-top:0.15rem;">Rp <?= number_format($proyeksiAkhirTahun, 0, ',', '.') ?></div>
                        </div>
                        <div class="d-flex flex-wrap gap-3 align-items-center">
                            <div style="text-align:center;">
                                <div style="font-size:var(--fs-lg);font-weight:800;color:<?= $proyeksiColor ?>;"><?= number_format($proyeksiPct, 2) ?>%</div>
                                <div style="font-size:var(--fs-xs);color:#64748b;">dari Pagu</div>
                            </div>
                            <span style="font-size:var(--fs-sm);font-weight:700;padding:0.3rem 0.75rem;border-radius:999px;background:<?= $proyeksiColor ?>;color:#fff;">
                                <?= $proyeksiStatus ?>
                            </span>
                        </div>
                    </div>
                    <div style="margin-top:0.5rem;font-size:var(--fs-xs);color:#64748b;font-style:italic;">
                        <i class="bi bi-info-circle me-1"></i>Proyeksi berdasarkan rata-rata <?= $bulanDenganData ?> bulan yang sudah ada data realisasi (<?= $bulanNames[1] ?>&ndash;<?= $bulanNames[$bulanDenganData] ?> <?= $stats['tahun'] ?>)
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Alerts Section -->
    <?php if (!empty($monthlyData['alerts']) || $serapan > 90): ?>
    <div class="mb-4 animate-fade-in-up delay-5">
        <div class="card rounded-4" style="background:linear-gradient(135deg,#fffbeb 0%,#fef3c7 100%);border:1px solid #fde68a;">
            <div class="card-body" style="padding:1.25rem 1.5rem;">
                <div class="d-flex align-items-start gap-3">
                    <div style="width:42px;height:42px;border-radius:var(--radius-md);background:var(--warning);color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:var(--fs-lg);"><i class="bi bi-exclamation-diamond-fill"></i></div>
                    <div style="flex:1;">
                        <h6 style="font-weight:700;color:var(--gray-800);margin-bottom:0.5rem;font-size:var(--fs-md);">Perhatian Diperlukan</h6>                        <?php if (!empty($monthlyData['alerts'])): ?>
                        <?php $bulanNamesLong=[1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember']; ?>
                        <?php
                            // Hanya tampilkan bulan yang sudah lewat / sedang berjalan (bukan future months)
                            $bulanBerjalanAlert = $stats['tahun'] == (int)date('Y') ? (int)date('n') : 12;
                            $underAlerts = array_filter($monthlyData['alerts'], fn($a) =>
                                $a['type'] === 'under' && $a['bulan'] <= $bulanBerjalanAlert
                            );
                        ?>
                        <?php if (!empty($underAlerts)): ?>
                        <div style="font-size:var(--fs-sm);font-weight:700;color:#b45309;margin-bottom:0.3rem;"><i class="bi bi-arrow-down-circle-fill me-1"></i>Realisasi KURANG dari RAK (bulan sudah berjalan):</div>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <?php foreach($underAlerts as $alert): ?>
                            <span style="font-size:var(--fs-xs);padding:0.25rem 0.625rem;border-radius:var(--radius-full);background:#fff;border:1px solid #fde68a;color:#b45309;font-weight:600;">
                                <?= $bulanNamesLong[$alert['bulan']] ?> (&minus;Rp <?= number_format($alert['rak']-$alert['realisasi'],0,',','.') ?>)
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($serapan > 90): ?>
                        <div style="font-size:var(--fs-base);color:var(--gray-600);">
                            <i class="bi bi-lightning-fill" style="color:var(--warning);"></i>
                            Serapan <strong><?= number_format($serapan,2) ?>%</strong> &mdash;
                            <?= $serapan>=100?'Anggaran telah habis atau melebihi pagu.':($serapan>=95?'Hampir seluruh anggaran terserap.':'Penyerapan sangat tinggi.') ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Charts Row -->
    <div class="row mb-4 g-3 align-items-start">
        <!-- Monthly Chart -->
        <div class="col-lg-8 animate-fade-in-up delay-3" id="section-grafik">
            <div class="card rounded-4">
                <div class="section-header" style="padding-bottom:0.5rem;">
                    <div>
                        <h5 class="section-title"><i class="bi bi-graph-up me-2" style="color:var(--primary);"></i>Grafik Realisasi Bulanan</h5>
                        <p class="section-subtitle">Perbandingan RAK vs Realisasi per bulan</p>
                    </div>
                    <?php if (!empty($monthlyData['alerts'])): ?>
                    <?php
                        $cntOver  = count(array_filter($monthlyData['alerts'], fn($a) => $a['type']==='over'));
                        $cntUnder = count(array_filter($monthlyData['alerts'], fn($a) => $a['type']==='under'));
                    ?>
                    <?php if ($cntOver > 0): ?>
                    <span style="font-size:var(--fs-xs);font-weight:600;padding:0.3rem 0.75rem;border-radius:var(--radius-full);background:#fef2f2;color:var(--danger);border:1px solid #fecaca;margin-left:0.25rem;"><i class="bi bi-arrow-up-circle-fill me-1"></i><?= $cntOver ?> Over RAK</span>
                    <?php endif; ?>
                    <?php if ($cntUnder > 0): ?>
                    <span style="font-size:var(--fs-xs);font-weight:600;padding:0.3rem 0.75rem;border-radius:var(--radius-full);background:#fffbeb;color:#b45309;border:1px solid #fde68a;margin-left:0.25rem;"><i class="bi bi-arrow-down-circle-fill me-1"></i><?= $cntUnder ?> Under RAK</span>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="card-body" style="padding:0.75rem 1.5rem 1.5rem;">
                    <!-- Toggle Buttons -->
                    <div class="d-flex gap-1 mb-3" id="monthlyChartToggle">
                        <button type="button" class="btn btn-sm monthly-toggle-btn active" data-range="all">Semua Bulan</button>
                        <button type="button" class="btn btn-sm monthly-toggle-btn" data-range="h1">Jan–Jun</button>
                        <button type="button" class="btn btn-sm monthly-toggle-btn" data-range="h2">Jul–Des</button>
                    </div>
                    <div class="chart-wrapper monthly-chart-wrapper"><canvas id="monthlyChart"></canvas></div>
                    <?php
                        // Deteksi anomali (<20% RAK) untuk bulan yang sudah berjalan
                        $anomaliBulan = [];
                        for ($m = 1; $m <= $bulanBerjalan; $m++) {
                            $mRak = (float)($monthlyData['rak'][$m] ?? 0);
                            $mReal = (float)($monthlyData['realisasi'][$m] ?? 0);
                            if ($mRak > 0) {
                                $capPct = ($mReal / $mRak) * 100;
                                if ($capPct < 20) {
                                    $anomaliBulan[] = [
                                        'bulan' => $bulanNames[$m],
                                        'rak' => $mRak,
                                        'realisasi' => $mReal,
                                        'capaian' => $capPct
                                    ];
                                }
                            }
                        }
                    ?>
                    <?php if (!empty($anomaliBulan)): ?>
                    <div class="chart-anomaly-alert mt-2">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size:1.1rem;flex-shrink:0;"></i>
                        <div style="flex:1;">
                            <strong>Perhatian Anomali Capaian:</strong>
                            <?php foreach ($anomaliBulan as $ab): ?>
                            <span>Bulan <strong><?= $ab['bulan'] ?></strong> realisasi hanya Rp <?= number_format($ab['realisasi'], 0, ',', '.') ?> (<strong><?= number_format($ab['capaian'], 1) ?>%</strong> dari target RAK Rp <?= number_format($ab['rak'], 0, ',', '.') ?> &mdash; realisasi jauh di bawah target RAK).</span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($monthlyData['alerts'])): ?>
                    <?php
                        $cntOver2  = count(array_filter($monthlyData['alerts'], fn($a) => $a['type']==='over'));
                        $cntUnder2 = count(array_filter($monthlyData['alerts'], fn($a) => $a['type']==='under'));
                    ?>
                    <div style="margin-top:0.75rem;padding:0.625rem 0.875rem;background:#f8fafc;border-radius:var(--radius-sm);border:1px solid var(--gray-200);font-size:var(--fs-sm);color:var(--gray-600);display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                        <i class="bi bi-info-circle-fill" style="color:var(--primary);flex-shrink:0;"></i>
                        <?php if ($cntOver2 > 0): ?>
                        <span><strong style="color:#2563eb;"><?= $cntOver2 ?> bulan</strong> realisasi melebihi RAK <span style="display:inline-block;width:10px;height:10px;background:#2563eb;border-radius:2px;margin-left:2px;"></span></span>
                        <?php endif; ?>
                        <?php if ($cntUnder2 > 0): ?>
                        <span style="margin-left:0.5rem;"><strong style="color:#dc2626;"><?= $cntUnder2 ?> bulan</strong> realisasi kurang dari RAK <span style="display:inline-block;width:10px;height:10px;background:#dc2626;border-radius:2px;margin-left:2px;"></span></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Komposisi & Serapan -->
        <div class="col-lg-4 animate-fade-in-up delay-4" id="section-komposisi">
            <div class="card rounded-4">
                <div class="section-header" style="padding-bottom:0;">
                    <div>
                        <h5 class="section-title"><i class="bi bi-pie-chart me-2" style="color:var(--primary);"></i>Komposisi</h5>
                        <p class="section-subtitle">Total serapan &amp; distribusi realisasi per unit</p>
                    </div>
                </div>
                <div class="card-body d-flex flex-column" style="padding:1rem 1.5rem 1.5rem;">
                    <!-- Serapan Gauge -->
                    <div style="padding:1rem;background:var(--gray-50);border-radius:var(--radius-md);border:1px solid var(--gray-100);margin-bottom:1rem;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span style="font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--gray-400);">Total Serapan</span>
                            <span style="font-size:var(--fs-lg);font-weight:800;color:var(--<?= $pctColor ?>);letter-spacing:-0.03em;"><?= number_format($stats['percentage'],2) ?>%</span>
                        </div>
                        <div class="progress" style="height:7px;border-radius:4px;background:var(--gray-200);">
                            <div class="progress-bar bg-<?= $pctColor ?>" style="width:<?= min($stats['percentage'],100) ?>%;border-radius:4px;"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <span style="font-size:var(--fs-xs);color:var(--gray-400);">0%</span>
                            <span style="font-size:var(--fs-xs);color:var(--gray-400);">Target 100%</span>
                        </div>
                    </div>

                    <!-- Breakdown Chart -->
                    <div class="flex-grow-1 d-flex flex-column justify-content-center">
                        <div class="chart-wrapper breakdown-chart-wrapper"><canvas id="breakdownChart"></canvas></div>
                        <div class="mt-2 text-center">
                            <span style="font-size:var(--fs-xs);font-weight:600;padding:0.3rem 0.875rem;border-radius:var(--radius-full);background:var(--gray-50);color:var(--gray-500);border:1px solid var(--gray-200);">
                                <i class="bi bi-pie-chart-fill me-1"></i>Distribusi Realisasi <?php echo empty($filters['seksi_id'])?'per Seksi':(empty($filters['program_id'])?'per Program':(empty($filters['kegiatan_id'])?'per Kegiatan':'per Sub Kegiatan')); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Penyerapan per Bulan -->
    <div class="row mb-4" id="section-detail-bulan">
        <div class="col-12 animate-fade-in-up delay-5">
            <div class="card rounded-4 overflow-hidden">
                <div class="section-header" style="padding-bottom:0.75rem;">
                    <div><h5 class="section-title"><i class="bi bi-calendar2-month me-2" style="color:var(--primary);"></i>Detail Penyerapan per Bulan</h5><p class="section-subtitle">Tabel rincian RAK (Target) vs Realisasi tiap bulan</p></div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn-export-section" onclick="exportTableToExcel('table-detail-bulan', 'detail-bulan-<?= $stats['tahun'] ?>.xlsx')" data-bs-toggle="tooltip" data-bs-placement="top" title="Export tabel detail penyerapan bulanan ke Excel">
                            <i class="bi bi-file-earmark-excel-fill text-success"></i> Export XLSX
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-dashboard align-middle mb-0" id="table-detail-bulan">
                            <thead><tr>
                                <th class="ps-4">Bulan</th>
                                <th class="text-end">RAK (Target)</th>
                                <th class="text-end">Realisasi (Serapan)</th>
                                <th class="text-end">Selisih</th>
                                <th class="text-end pe-4">% Capaian thd RAK</th>
                            </tr></thead>
                            <tbody>
                                <?php 
                                $totalRakKumulatif = 0;
                                $totalRealisasiKumulatif = 0;
                                for($i = 1; $i <= 12; $i++): 
                                    $mRak = $monthlyData['rak'][$i] ?? 0;
                                    $mRealisasi = $monthlyData['realisasi'][$i] ?? 0;
                                    $selisih = $mRealisasi - $mRak;
                                    $pct = $mRak > 0 ? ($mRealisasi / $mRak) * 100 : 0;
                                    $isFutureMonth = ($stats['tahun'] == (int) date('Y') && $i > $bulanBerjalanDetail);
                                    
                                    // Tambahkan RAK ke kumulatif untuk semua bulan (termasuk future)
                                    $totalRakKumulatif += $mRak;
                                    $totalRealisasiKumulatif += $mRealisasi;
                                    
                                    // Sembunyikan baris future yang benar-benar kosong (0 RAK dan 0 Realisasi)
                                    if ($mRak == 0 && $mRealisasi == 0 && $isFutureMonth) continue;
                                ?>
                                <?php if ($isFutureMonth): ?>
                                <!-- Baris bulan future: tampilkan netral (abu-abu), bukan merah -->
                                <tr style="opacity:0.55;">
                                    <td class="ps-4" style="font-weight:600;color:#94a3b8;">
                                        <?= $bulanNames[(int)$i] ?>
                                        <span style="font-size:var(--fs-xs);font-weight:700;padding:0.15rem 0.45rem;border-radius:var(--radius-full);background:#f1f5f9;border:1px solid #e2e8f0;color:#94a3b8;margin-left:0.4rem;vertical-align:middle;">Belum Berjalan</span>
                                    </td>
                                    <td class="text-end" style="color:#94a3b8;">Rp <?= number_format($mRak,0,',','.') ?></td>
                                    <td class="text-end" style="color:#94a3b8;">&mdash;</td>
                                    <td class="text-end" style="color:#94a3b8;">&mdash;</td>
                                    <td class="text-end pe-4">
                                        <span class="badge-capaian badge-capaian-neutral">Proyeksi</span>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <tr>
                                    <td class="ps-4" style="font-weight:600;color:var(--gray-700);"><?= $bulanNames[(int)$i] ?></td>
                                    <td class="text-end" style="color:var(--gray-500);">Rp <?= number_format($mRak,0,',','.') ?></td>
                                    <td class="text-end" style="font-weight:700;color:var(--gray-800);">Rp <?= number_format($mRealisasi,0,',','.') ?></td>
                                    <td class="text-end" style="font-weight:600;">
                                        <?php if ($selisih < 0): ?>
                                            <span class="text-selisih-under"><i class="bi bi-arrow-down-short"></i> -Rp <?= number_format(abs($selisih),0,',','.') ?></span>
                                        <?php elseif ($selisih > 0): ?>
                                            <span class="text-selisih-over"><i class="bi bi-arrow-up-short"></i> +Rp <?= number_format(abs($selisih),0,',','.') ?></span>
                                        <?php else: ?>
                                            <span class="text-selisih-neutral">&mdash; Rp 0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <?php
                                            if ($pct > 110) { $badgeCls = 'badge-capaian-info'; $badgeIcon = 'bi-arrow-up-circle-fill'; }
                                            elseif ($pct >= 90) { $badgeCls = 'badge-capaian-success'; $badgeIcon = 'bi-check-circle-fill'; }
                                            elseif ($pct >= 50) { $badgeCls = 'badge-capaian-warning'; $badgeIcon = 'bi-exclamation-circle-fill'; }
                                            else { $badgeCls = 'badge-capaian-danger'; $badgeIcon = 'bi-arrow-down-circle-fill'; }
                                        ?>
                                        <span class="badge-capaian <?= $badgeCls ?>">
                                            <i class="bi <?= $badgeIcon ?>"></i> <?= number_format($pct,2) ?>%
                                        </span>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php endfor; ?>
                            </tbody>
                            <tfoot style="background:var(--gray-50);font-weight:700;">
                                <?php
                                    // Hitung kumulatif hanya dari bulan yang sudah berjalan
                                    $rakKumBerjalan = 0; $realKumBerjalan = 0;
                                    for ($ib = 1; $ib <= $bulanBerjalanDetail; $ib++) {
                                        $rakKumBerjalan += ($monthlyData['rak'][$ib] ?? 0);
                                        $realKumBerjalan += ($monthlyData['realisasi'][$ib] ?? 0);
                                    }
                                    $selisihKum = $realKumBerjalan - $rakKumBerjalan;
                                ?>
                                <tr style="border-top:2px solid #cbd5e1;">
                                    <td class="ps-4">Kumulatif s/d <?= $bulanNames[$bulanBerjalanDetail] ?></td>
                                    <td class="text-end">Rp <?= number_format($rakKumBerjalan,0,',','.') ?></td>
                                    <td class="text-end">Rp <?= number_format($realKumBerjalan,0,',','.') ?></td>
                                    <td class="text-end" style="font-weight:700;">
                                        <?php if ($selisihKum < 0): ?>
                                            <span class="text-selisih-under"><i class="bi bi-arrow-down-short"></i> -Rp <?= number_format(abs($selisihKum),0,',','.') ?></span>
                                        <?php elseif ($selisihKum > 0): ?>
                                            <span class="text-selisih-over"><i class="bi bi-arrow-up-short"></i> +Rp <?= number_format(abs($selisihKum),0,',','.') ?></span>
                                        <?php else: ?>
                                            <span class="text-selisih-neutral">&mdash; Rp 0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <?php
                                            $kumPct = $rakKumBerjalan > 0 ? ($realKumBerjalan / $rakKumBerjalan) * 100 : 0;
                                            if ($kumPct > 110) { $badgeKumCls = 'badge-capaian-info'; }
                                            elseif ($kumPct >= 90) { $badgeKumCls = 'badge-capaian-success'; }
                                            elseif ($kumPct >= 50) { $badgeKumCls = 'badge-capaian-warning'; }
                                            else { $badgeKumCls = 'badge-capaian-danger'; }
                                        ?>
                                        <span class="badge-capaian <?= $badgeKumCls ?>">
                                            <?= number_format($kumPct, 2) ?>%
                                        </span>
                                    </td>
                                </tr>
                                <?php if ($bulanBerjalanDetail < 12): ?>
                                <tr style="opacity:0.6;">
                                    <td class="ps-4" style="font-size:var(--fs-sm);color:#94a3b8;">Total RAK Tahunan (termasuk proyeksi)</td>
                                    <td class="text-end" style="font-size:var(--fs-sm);color:#94a3b8;">Rp <?= number_format($totalRakKumulatif,0,',','.') ?></td>
                                    <td class="text-end" style="font-size:var(--fs-sm);color:#94a3b8;">&mdash;</td>
                                    <td class="text-end" style="font-size:var(--fs-sm);color:#94a3b8;">&mdash;</td>
                                    <td class="text-end pe-4" style="font-size:var(--fs-sm);color:#94a3b8;">&mdash;</td>
                                </tr>
                                <?php endif; ?>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


        <!-- ===== Detail Anggaran & Analisis Tabbed Section ===== -->
    <div class="mb-4 animate-fade-in-up delay-4" id="section-detail-tabs">
        <!-- Tab Navigation Pills -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <ul class="nav nav-pills custom-dashboard-tabs flex-nowrap" id="budgetDetailTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active custom-tab-btn" id="tab-serapan-rekening-btn" data-bs-toggle="tab" data-bs-target="#tab-serapan-rekening" type="button" role="tab" aria-controls="tab-serapan-rekening" aria-selected="true">
                        <i class="bi bi-grid-3x3-gap-fill me-1"></i>Serapan Bulanan
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link custom-tab-btn" id="tab-sisa-semester-btn" data-bs-toggle="tab" data-bs-target="#tab-sisa-semester" type="button" role="tab" aria-controls="tab-sisa-semester" aria-selected="false">
                        <i class="bi bi-wallet-fill me-1"></i>Sisa per Semester
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link custom-tab-btn" id="tab-struktur-btn" data-bs-toggle="tab" data-bs-target="#tab-struktur" type="button" role="tab" aria-controls="tab-struktur" aria-selected="false">
                        <i class="bi bi-diagram-3 me-1"></i>Struktur Anggaran
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link custom-tab-btn" id="tab-deviasi-btn" data-bs-toggle="tab" data-bs-target="#tab-deviasi" type="button" role="tab" aria-controls="tab-deviasi" aria-selected="false">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>Deviasi RAK
                    </button>
                </li>
            </ul>
        </div>

        <!-- Tab Content Panes -->
        <div class="tab-content" id="budgetDetailTabsContent">
            <!-- Tab 1: Serapan Bulanan -->
            <div class="tab-pane fade show active" id="tab-serapan-rekening" role="tabpanel" aria-labelledby="tab-serapan-rekening-btn">
                <!-- Serapan per Sub Kegiatan per Rekening per Bulan -->
    <?php if (!empty($monthlyAbsorptionDetails['sub_kegiatan'])): ?>
    <?php
        $absorptionSubKegiatan = $monthlyAbsorptionDetails['sub_kegiatan'];
        $absorptionTotals = $monthlyAbsorptionDetails['totals'];
        $absorptionPct = $absorptionTotals['pagu'] > 0 ? ($absorptionTotals['realisasi'] / $absorptionTotals['pagu']) * 100 : 0;
        $formatCompactRupiah = function ($value, bool $dashZero = false): string {
            $value = (float) $value;
            $abs = abs($value);
            if ($abs < 0.5) {
                return $dashZero ? '&mdash;' : 'Rp 0';
            }
            $sign = $value < 0 ? '-' : '';
            return ($sign ? '-' : '') . 'Rp ' . number_format($abs, 0, ',', '.');
        };
        $rupiahTooltip = function ($value): string {
            return '';
        };
    ?>
    <div class="row mb-4" id="section-serapan-rekening">
        <div class="col-12 animate-fade-in-up delay-5">
            <div class="card rounded-4 overflow-hidden">
                <div class="section-header" style="padding-bottom:0.75rem;">
                    <div>
                        <h5 class="section-title"><i class="bi bi-grid-3x3-gap-fill me-2" style="color:#0284c7;"></i>Serapan per Sub Kegiatan dan Rekening per Bulan</h5>
                        <p class="section-subtitle">Realisasi bulanan per rekening, dikelompokkan berdasarkan sub kegiatan</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span style="font-size:var(--fs-xs);font-weight:700;padding:0.3rem 0.875rem;border-radius:var(--radius-full);background:#ecfeff;color:#0369a1;border:1px solid #bae6fd;white-space:nowrap;">
                            <i class="bi bi-wallet2 me-1"></i><?= (int) $absorptionTotals['rekening_count'] ?> Rekening
                        </span>
                        <?php
                            $exportParams = http_build_query(array_filter([
                                'tahun'           => $stats['tahun'],
                                'seksi_id'        => $filters['seksi_id']        ?? '',
                                'program_id'      => $filters['program_id']      ?? '',
                                'kegiatan_id'     => $filters['kegiatan_id']     ?? '',
                                'sub_kegiatan_id' => $filters['sub_kegiatan_id'] ?? '',
                            ], fn($v) => $v !== '' && $v !== null));
                        ?>
                        <a href="<?= base_url('export/serapan-bulanan') ?>?<?= $exportParams ?>"
                           class="btn btn-sm btn-export-section"
                           id="btn-export-serapan-bulanan"
                           data-bs-toggle="tooltip" data-bs-placement="top" title="Export rincian serapan per rekening ini saja ke Excel">
                            <i class="bi bi-file-earmark-excel-fill me-1"></i>Export Excel
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="d-flex flex-wrap align-items-center gap-2 px-4 py-3" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                        <span style="font-size:var(--fs-sm);font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.04em;">Total Serapan</span>
                        <span style="font-size:var(--fs-base);font-weight:800;color:#0f172a;">Rp <?= number_format($absorptionTotals['realisasi'], 0, ',', '.') ?></span>
                        <span style="font-size:var(--fs-sm);color:#64748b;">dari pagu Rp <?= number_format($absorptionTotals['pagu'], 0, ',', '.') ?></span>
                        <span style="font-size:var(--fs-xs);font-weight:800;padding:0.2rem 0.6rem;border-radius:999px;background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;">
                            <?= number_format($absorptionPct, 2) ?>%
                        </span>
                    </div>
                    <div class="d-md-none px-3 py-2 bg-light border-bottom d-flex align-items-center justify-content-between">
    <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" id="toggleMonthlyDetailMobile" style="font-size:var(--fs-xs);font-weight:600;">
        <i class="bi bi-calendar3 me-1"></i><span id="toggleMonthlyDetailText">Lihat Semua Bulan (12 Bln)</span>
    </button>
    <span style="font-size:var(--fs-xs);color:var(--gray-500);"><i class="bi bi-info-circle me-1"></i>Ringkas di HP</span>
</div>
<div class="monthly-absorption-scroll-hint">
                        <span><i class="bi bi-arrows"></i> Geser tabel ke kanan/kiri untuk melihat semua bulan. Kolom Sub Kegiatan, Pagu, Total, dan % tetap terlihat.</span>
                    </div>
                    <div class="table-responsive monthly-absorption-wrap">
                        <table class="table table-hover table-dashboard align-middle mb-0 monthly-absorption-table">
                            <thead>
                                <tr>
                                    <th class="ps-4 monthly-absorption-name monthly-sticky-name">Sub Kegiatan / Rekening</th>
                                    <th class="text-end monthly-absorption-pagu monthly-sticky-pagu">Pagu</th>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <th class="text-end monthly-absorption-month"><?= $bulanNames[$m] ?></th>
                                    <?php endfor; ?>
                                    <th class="text-end monthly-absorption-total monthly-sticky-total">Total</th>
                                    <th class="text-end pe-4 monthly-absorption-percent monthly-sticky-percent">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($absorptionSubKegiatan as $skid => $sk): ?>
                                    <?php
                                        $skPct = $sk['pagu'] > 0 ? ($sk['realisasi'] / $sk['pagu']) * 100 : 0;
                                        $skRowKey = 'absorption-sk-' . (int) $skid;
                                    ?>
                                    <tr class="monthly-absorption-group is-collapsed" data-absorption-toggle="<?= htmlspecialchars($skRowKey) ?>" role="button" tabindex="0" aria-expanded="false">
                                        <td class="ps-4 monthly-absorption-name monthly-sticky-name">
                                            <div class="monthly-absorption-title">
                                                <button type="button" class="monthly-absorption-toggle-btn" aria-label="Buka rincian rekening" aria-expanded="false" data-absorption-toggle-button="<?= htmlspecialchars($skRowKey) ?>">
                                                    <i class="bi bi-chevron-right"></i>
                                                </button>
                                                <div>
                                                    <div class="monthly-absorption-sk-name"><?= htmlspecialchars($sk['nama']) ?></div>
                                                    <div class="monthly-absorption-sk-meta"><?= htmlspecialchars($sk['kode']) ?> &bull; <?= count($sk['rekening']) ?> rekening</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end monthly-absorption-pagu monthly-sticky-pagu"><?= $formatCompactRupiah($sk['pagu']) ?></td>
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <?php $monthValue = $sk['months'][$m] ?? 0; ?>
                                            <td class="text-end monthly-absorption-month <?= $monthValue > 0 ? 'monthly-absorption-filled' : 'monthly-absorption-zero' ?>"><?= $formatCompactRupiah($monthValue, true) ?></td>
                                        <?php endfor; ?>
                                        <td class="text-end monthly-absorption-total monthly-sticky-total"><?= $formatCompactRupiah($sk['realisasi']) ?></td>
                                        <td class="text-end pe-4 monthly-absorption-percent monthly-sticky-percent">
                                            <span class="monthly-absorption-pct <?= $skPct > 100 ? 'is-over' : ($skPct >= 80 ? 'is-high' : ($skPct > 0 ? 'is-mid' : 'is-empty')) ?>">
                                                <?= number_format($skPct, 2) ?>%
                                            </span>
                                        </td>
                                    </tr>
                                    <?php foreach ($sk['rekening'] as $rek): ?>
                                        <?php $rekPct = $rek['pagu'] > 0 ? ($rek['realisasi'] / $rek['pagu']) * 100 : 0; ?>
                                        <tr class="monthly-absorption-detail" data-absorption-child="<?= htmlspecialchars($skRowKey) ?>" hidden>
                                            <td class="ps-4 monthly-absorption-name monthly-sticky-name">
                                                <div style="font-weight:700;color:#334155;"><?= htmlspecialchars($rek['nama']) ?></div>
                                                <div style="font-size:var(--fs-xs);color:#94a3b8;font-weight:600;">Kode rekening: <?= htmlspecialchars($rek['kode']) ?></div>
                                            </td>
                                            <td class="text-end monthly-absorption-pagu monthly-sticky-pagu"><?= $formatCompactRupiah($rek['pagu']) ?></td>
                                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                                <?php $monthValue = $rek['months'][$m] ?? 0; ?>
                                                <td class="text-end monthly-absorption-month <?= $monthValue > 0 ? 'monthly-absorption-filled' : 'monthly-absorption-zero' ?>"><?= $formatCompactRupiah($monthValue, true) ?></td>
                                            <?php endfor; ?>
                                            <td class="text-end monthly-absorption-total monthly-sticky-total"><?= $formatCompactRupiah($rek['realisasi']) ?></td>
                                            <td class="text-end pe-4 monthly-absorption-percent monthly-sticky-percent">
                                                <span class="monthly-absorption-pct <?= $rekPct > 100 ? 'is-over' : ($rekPct >= 80 ? 'is-high' : ($rekPct > 0 ? 'is-mid' : 'is-empty')) ?>">
                                                    <?= number_format($rekPct, 2) ?>%
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td class="ps-4 monthly-absorption-name monthly-sticky-name">Total</td>
                                    <td class="text-end monthly-absorption-pagu monthly-sticky-pagu"><?= $formatCompactRupiah($absorptionTotals['pagu']) ?></td>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <?php $monthValue = $absorptionTotals['months'][$m] ?? 0; ?>
                                        <td class="text-end monthly-absorption-month <?= $monthValue > 0 ? 'monthly-absorption-filled' : 'monthly-absorption-zero' ?>"><?= $formatCompactRupiah($monthValue, true) ?></td>
                                    <?php endfor; ?>
                                    <td class="text-end monthly-absorption-total monthly-sticky-total"><?= $formatCompactRupiah($absorptionTotals['realisasi']) ?></td>
                                    <td class="text-end pe-4 monthly-absorption-percent monthly-sticky-percent"><?= number_format($absorptionPct, 2) ?>%</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
            </div>

            <!-- Tab 2: Sisa per Semester -->
            <div class="tab-pane fade" id="tab-sisa-semester" role="tabpanel" aria-labelledby="tab-sisa-semester-btn">
                <!-- ===== Sisa Dana per Semester ===== -->
    <?php if (!empty($semesterRekap['sub_kegiatan'])): ?>
    <?php
        $semT = $semesterRekap['totals'];
        $semSK = $semesterRekap['sub_kegiatan'];
        $bulanBerjalanNow = (int) date('n');
        $tahunNow = (int) date('Y');
        // Active semester: jika tahun yg dilihat = tahun ini, tentukan semester berjalan
        $semesterAktif = 0;
        if ($tahunNow == $stats['tahun']) {
            $semesterAktif = $bulanBerjalanNow <= 6 ? 1 : 2;
        }
    ?>
    <div class="row mb-4" id="section-sisa-semester">
        <div class="col-12 animate-fade-in-up delay-5">
            <div class="card rounded-4">
                <div class="section-header" style="padding-bottom:0.75rem;">
                    <div>
                        <h5 class="section-title"><i class="bi bi-wallet-fill me-2" style="color:#0284c7;"></i>Sisa Dana per Semester</h5>
                        <p class="section-subtitle">Saldo yang masih bisa ditarik tiap rekening per semester (RAK semester &minus; Realisasi semester)</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span style="font-size:var(--fs-xs);font-weight:700;padding:0.3rem 0.875rem;border-radius:var(--radius-full);background:#ecfeff;color:#0369a1;border:1px solid #bae6fd;">
                            <i class="bi bi-bank me-1"></i><?= $semT['total_rekening'] ?> Rekening
                        </span>
                        <?php
                            $exportSisaParams = http_build_query(array_filter([
                                'tahun'           => $stats['tahun'],
                                'seksi_id'        => $filters['seksi_id']        ?? '',
                                'program_id'      => $filters['program_id']      ?? '',
                                'kegiatan_id'     => $filters['kegiatan_id']     ?? '',
                                'sub_kegiatan_id' => $filters['sub_kegiatan_id'] ?? '',
                            ], fn($v) => $v !== '' && $v !== null));
                        ?>
                        <a href="<?= base_url('export/sisa-semester') ?>?<?= $exportSisaParams ?>"
                           class="btn btn-sm btn-export-section"
                           id="btn-export-sisa-semester"
                           data-bs-toggle="tooltip" data-bs-placement="top" title="Export rekap sisa dana semester ini saja ke Excel">
                            <i class="bi bi-file-earmark-excel-fill me-1"></i>Export Excel
                        </a>
                    </div>
                </div>

                <!-- Summary Cards Sisa Semester -->
                <div style="padding:0 1.5rem;">
                    <div class="row g-3 mb-3">
                        <!-- Sisa Semester 1 -->
                        <?php $s1Inactive = $semesterAktif != 0 && $semesterAktif != 1; ?>
                        <div class="col-md-4 col-12">
                            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:var(--radius-md,10px);padding:1rem 1.25rem;<?= $semesterAktif==1?'box-shadow:0 0 0 2px #3b82f6;border-color:#3b82f6;':'' ?>">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.4rem;gap:0.5rem;">
                                    <span style="font-size:var(--fs-sm);font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:0.04em;">Sisa Semester 1 <small style="font-weight:600;color:#64748b;">(Jan&ndash;Jun)</small></span>
                                    <?php if ($semesterAktif == 1): ?>
                                    <span title="Semester ini sedang aktif berjalan (Jan&ndash;Jun)" style="font-size:var(--fs-xs);font-weight:700;padding:0.2rem 0.55rem;border-radius:var(--radius-full);background:#3b82f6;color:#fff;white-space:nowrap;cursor:help;">
                                        <i class="bi bi-circle-fill" style="font-size:0.45rem;vertical-align:middle;animation:smPulse 2s ease-in-out infinite;"></i> Periode berjalan
                                    </span>
                                    <?php elseif ($s1Inactive): ?>
                                    <span title="Semester 1 telah berakhir" style="font-size:var(--fs-xs);font-weight:600;padding:0.15rem 0.5rem;border-radius:var(--radius-full);background:#e2e8f0;color:#64748b;white-space:nowrap;">
                                        <i class="bi bi-check-circle-fill"></i> Selesai
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:var(--fs-xl);font-weight:800;color:<?= $semT['sisa_s1']>=0?'#0f172a':'#dc2626' ?>;letter-spacing:-0.02em;line-height:1.1;<?= $s1Inactive?'opacity:0.6;':'' ?>">Rp <?= number_format($semT['sisa_s1'], 0, ',', '.') ?></div>
                                <?php if ($s1Inactive): ?>
                                <div style="margin-top:0.2rem;font-size:var(--fs-xs);color:#94a3b8;font-weight:500;font-style:italic;">Periode telah berakhir</div>
                                <?php endif; ?>
                                <div style="margin-top:0.5rem;font-size:var(--fs-xs);color:#475569;<?= $s1Inactive?'opacity:0.7;':'' ?>">
                                    <span><strong><?= $semT['rekening_dengan_sisa_s1'] ?></strong> rekening masih ada sisa</span>
                                </div>
                                <div style="margin-top:0.4rem;font-size:var(--fs-xs);color:#64748b;display:flex;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;<?= $s1Inactive?'opacity:0.7;':'' ?>">
                                    <span>RAK: Rp <?= number_format($semT['rak_s1'], 0, ',', '.') ?></span>
                                    <span>Realisasi: Rp <?= number_format($semT['real_s1'], 0, ',', '.') ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Sisa Semester 2 -->
                        <?php $s2Inactive = $semesterAktif == 1; // S2 belum berjalan saat S1 aktif ?>
                        <?php $carryOverTotal = $semT['carry_over'] ?? 0; ?>
                        <div class="col-md-4 col-12">
                            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:var(--radius-md,10px);padding:1rem 1.25rem;<?= $semesterAktif==2?'box-shadow:0 0 0 2px #3b82f6;border-color:#3b82f6;':'' ?>">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.4rem;gap:0.5rem;">
                                    <span style="font-size:var(--fs-sm);font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:0.04em;">Sisa Semester 2 <small style="font-weight:600;color:#64748b;">(Jul&ndash;Des)</small></span>
                                    <?php if ($semesterAktif == 2): ?>
                                    <span title="Semester ini sedang aktif berjalan (Jul&ndash;Des)" style="font-size:var(--fs-xs);font-weight:700;padding:0.2rem 0.55rem;border-radius:var(--radius-full);background:#3b82f6;color:#fff;white-space:nowrap;cursor:help;">
                                        <i class="bi bi-circle-fill" style="font-size:0.45rem;vertical-align:middle;animation:smPulse 2s ease-in-out infinite;"></i> Periode berjalan
                                    </span>
                                    <?php elseif ($s2Inactive): ?>
                                    <span title="Semester 2 belum berjalan (Jul&ndash;Des)" style="font-size:var(--fs-xs);font-weight:600;padding:0.15rem 0.5rem;border-radius:var(--radius-full);background:#e2e8f0;color:#64748b;white-space:nowrap;">
                                        <i class="bi bi-clock"></i> Belum berjalan
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:var(--fs-xl);font-weight:800;color:<?= $semT['sisa_s2']>=0?'#0f172a':'#dc2626' ?>;letter-spacing:-0.02em;line-height:1.1;<?= $s2Inactive?'opacity:0.6;':'' ?>">Rp <?= number_format($semT['sisa_s2'], 0, ',', '.') ?></div>
                                <?php if ($carryOverTotal > 0): ?>
                                <div style="margin-top:0.3rem;font-size:var(--fs-xs);display:inline-flex;align-items:center;gap:0.3rem;padding:0.2rem 0.55rem;border-radius:999px;background:#ecfdf5;border:1px solid #a7f3d0;color:#059669;font-weight:600;">
                                    <i class="bi bi-arrow-right-circle-fill" style="font-size:var(--fs-xs);"></i>
                                    Termasuk sisa sem 1: Rp <?= number_format($carryOverTotal, 0, ',', '.') ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($s2Inactive): ?>
                                <div style="margin-top:0.2rem;font-size:var(--fs-xs);color:#94a3b8;font-weight:500;font-style:italic;">Belum berjalan</div>
                                <?php endif; ?>
                                <div style="margin-top:0.5rem;font-size:var(--fs-xs);color:#475569;<?= $s2Inactive?'opacity:0.7;':'' ?>">
                                    <span><strong><?= $semT['rekening_dengan_sisa_s2'] ?></strong> rekening masih ada sisa</span>
                                </div>
                                <div style="margin-top:0.4rem;font-size:var(--fs-xs);color:#64748b;display:flex;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;<?= $s2Inactive?'opacity:0.7;':'' ?>">
                                    <span>RAK S2: Rp <?= number_format($semT['rak_s2'], 0, ',', '.') ?></span>
                                    <span>Realisasi: Rp <?= number_format($semT['real_s2'], 0, ',', '.') ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Total Sisa Tahun -->
                        <div class="col-md-4 col-12">
                            <?php $totalSisaTahun = $semT['sisa_s1'] + $semT['sisa_s2']; ?>
                            <div style="background:linear-gradient(135deg,#1e293b 0%,#334155 100%);border:1px solid #1e293b;border-radius:var(--radius-md,10px);padding:1rem 1.25rem;color:#fff;position:relative;overflow:hidden;">
                                <div style="position:absolute;right:-15px;top:-15px;width:80px;height:80px;background:rgba(59,130,246,0.15);border-radius:50%;"></div>
                                <div style="position:relative;z-index:1;">
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.4rem;">
                                        <span style="font-size:var(--fs-xs);font-weight:700;color:#cbd5e1;text-transform:uppercase;letter-spacing:0.06em;display:inline-flex;align-items:center;gap:0.4rem;">
                                            <i class="bi bi-pie-chart-fill" style="color:#60a5fa;font-size:var(--fs-base);"></i>
                                            Total Tahun
                                        </span>
                                        <span style="font-size:var(--fs-xs);font-weight:600;padding:0.15rem 0.5rem;border-radius:var(--radius-full);background:rgba(59,130,246,0.25);color:#bfdbfe;">RINGKASAN</span>
                                    </div>
                                    <div style="font-size:var(--fs-xl);font-weight:800;color:<?= $totalSisaTahun>=0?'#fff':'#fca5a5' ?>;letter-spacing:-0.02em;line-height:1.1;">Rp <?= number_format($totalSisaTahun, 0, ',', '.') ?></div>
                                    <div style="margin-top:0.5rem;font-size:var(--fs-xs);color:#cbd5e1;">
                                        Akumulasi sisa <strong style="color:#fff;">S1 + S2</strong> dari semua rekening
                                    </div>
                                    <div style="margin-top:0.4rem;font-size:var(--fs-xs);color:#94a3b8;border-top:1px solid rgba(255,255,255,0.1);padding-top:0.4rem;">
                                        Total RAK Tahun: Rp <?= number_format($semT['rak_s1']+$semT['rak_s2'], 0, ',', '.') ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Semester Toggle -->
                <div style="padding:0 1.5rem 0;">
                    <div class="sm-tab-group" role="tablist" aria-label="Pilih semester">
                        <button type="button" class="sm-tab-btn <?= $semesterAktif!=2?'active':'' ?>" data-semester="1" role="tab" aria-selected="<?= $semesterAktif!=2?'true':'false' ?>">
                            <i class="bi bi-1-square-fill me-1"></i>Semester 1 <small style="opacity:0.75;font-weight:500;">Jan&ndash;Jun</small>
                            <?php if ($semesterAktif == 1): ?><span class="sm-tab-dot" title="Periode berjalan"></span><?php endif; ?>
                        </button>
                        <button type="button" class="sm-tab-btn <?= $semesterAktif==2?'active':'' ?>" data-semester="2" role="tab" aria-selected="<?= $semesterAktif==2?'true':'false' ?>">
                            <i class="bi bi-2-square-fill me-1"></i>Semester 2 <small style="opacity:0.75;font-weight:500;">Jul&ndash;Des</small>
                            <?php if ($semesterAktif == 2): ?><span class="sm-tab-dot" title="Periode berjalan"></span><?php endif; ?>
                        </button>
                    </div>
                </div>

                <!-- Filter & Search -->
                <div style="padding:1rem 1.5rem 0.75rem;">
                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-2">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <div class="position-relative" style="min-width:240px;">
                                <i class="bi bi-search" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:var(--gray-400);font-size:var(--fs-base);"></i>
                                <input type="text" id="semesterSearchInput" class="form-control form-control-sm" placeholder="Cari rekening atau sub kegiatan..." style="padding-left:2rem;font-size:var(--fs-base);">
                            </div>
                            <div class="btn-group" role="group" aria-label="Filter sisa">
                                <button type="button" class="btn btn-sm semester-filter-btn active" data-filter="all">Semua</button>
                                <button type="button" class="btn btn-sm semester-filter-btn" data-filter="sisa">Ada Sisa</button>
                                <button type="button" class="btn btn-sm semester-filter-btn" data-filter="negatif">Over RAK</button>
                            </div>
                            <span id="semesterFilterContext" style="font-size:var(--fs-xs);font-weight:600;color:#64748b;padding:0.2rem 0.55rem;border-radius:var(--radius-full,999px);background:#eff6ff;border:1px solid #bfdbfe;display:inline-flex;align-items:center;gap:0.3rem;">
                                <i class="bi bi-funnel-fill" style="font-size:var(--fs-xs);color:#3b82f6;"></i>
                                Filter berlaku untuk <strong style="color:#1e40af;" id="semesterFilterContextLabel">S<?= $semesterAktif==2?'2':'1' ?></strong>
                            </span>
                        </div>
                        <div style="font-size:var(--fs-sm);color:var(--gray-500);">
                            <i class="bi bi-info-circle me-1"></i>Klik baris sub kegiatan untuk lihat rekening
                        </div>
                    </div>
                </div>

                <!-- Tabel Sub Kegiatan & Rekening -->
                <div class="card-body" style="padding:0 1.5rem 1.5rem;">
                    <style>
                        @keyframes smPulse {
                            0%,100% { opacity:1; }
                            50% { opacity:0.4; }
                        }

                        /* Tab semester */
                        .sm-tab-group { display:flex; gap:0.5rem; border-bottom:2px solid #e2e8f0; padding-bottom:0; margin-bottom:0; }
                        .sm-tab-btn { position:relative; background:transparent; border:none; padding:0.65rem 1.25rem; font-size:var(--fs-base); font-weight:600; color:#64748b; cursor:pointer; border-bottom:3px solid transparent; margin-bottom:-2px; transition:all .2s; display:inline-flex; align-items:center; gap:0.4rem; border-radius:6px 6px 0 0; }
                        .sm-tab-btn:hover { color:#1e293b; background:#f8fafc; }
                        .sm-tab-btn.active { color:#0284c7; border-bottom-color:#0284c7; background:transparent; }
                        .sm-tab-dot { display:inline-block; width:8px; height:8px; border-radius:50%; background:#3b82f6; margin-left:0.4rem; animation:smPulse 2s ease-in-out infinite; }

                        /* Table base */
                        .sm-tbl { width:100%; border-collapse:separate; border-spacing:0; font-size:var(--fs-base); min-width:680px; font-variant-numeric:tabular-nums; table-layout:fixed; }
                        .sm-tbl thead th { position:sticky; top:0; z-index:2; background:#f1f5f9; padding:0.7rem 0.6rem; font-size:var(--fs-xs); font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.04em; border-bottom:2px solid #cbd5e1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; text-align:right; }
                        .sm-tbl thead th:first-child { text-align:left; padding-left:0.85rem; }
                        .sm-tbl thead th.sm-col-sisa { background:#dbeafe; color:#1e40af; }
                        .sm-tbl thead th.sm-col-total { background:#dbeafe; color:#1e3a8a; border-left:2px solid #93c5fd; }
                        .sm-tbl tbody td { padding:0.65rem 0.85rem; border-bottom:1px solid #f1f5f9; vertical-align:middle; white-space:nowrap; text-align:right; overflow:hidden; }
                        .sm-tbl tbody td:first-child { text-align:left; white-space:normal; word-break:break-word; overflow-wrap:anywhere; line-height:1.35; vertical-align:top; padding-top:0.75rem; }
                        .sm-tbl tbody td.sm-col-total { background:#f8fafc; border-left:2px solid #e2e8f0; }

                        /* Sub kegiatan row (parent) */
                        .sm-tbl tbody tr.sm-sk-row { cursor:pointer; }
                        .sm-tbl tbody tr.sm-sk-row td { background:#f1f5f9; font-weight:500; border-top:1px solid #cbd5e1; }
                        .sm-tbl tbody tr.sm-sk-row td.sm-col-total { background:#e2e8f0; }
                        .sm-tbl tbody tr.sm-sk-row:hover td { background:#e2e8f0; }
                        .sm-tbl tbody tr.sm-sk-row:hover td.sm-col-total { background:#cbd5e1; }

                        /* Rekening row (child) */
                        .sm-tbl tbody tr.sm-rek-row td { background:#fff; font-weight:400; }
                        .sm-tbl tbody tr.sm-rek-row td:first-child { padding-left:2.25rem; border-left:3px solid #E0E0E0; }
                        .sm-tbl tbody tr.sm-rek-row:hover td { background:#fafbfc; }
                        .sm-tbl tbody tr.sm-rek-row:hover td:first-child { border-left-color:#0284c7; }

                        .sm-collapsed + .sm-rek-row { display:none; }
                        .sm-toggle-icon { display:inline-block; transition:transform .2s ease; color:#0f172a; font-size:18px; vertical-align:middle; margin-right:0.4rem; }
                        .sm-collapsed .sm-toggle-icon { transform:rotate(-90deg); }

                        /* Sisa values */
                        .sm-sisa { font-weight:700; font-size:var(--fs-base); letter-spacing:-0.01em; }
                        .sm-sisa-pos { color:#059669; }
                        .sm-sisa-zero { color:#94a3b8; }
                        .sm-sisa-neg { color:#dc2626; }

                        /* RAK / Realisasi muted text */
                        .sm-amount { font-size:var(--fs-base); color:#334155; font-weight:500; }
                        .sm-amount-muted { color:#64748b; }

                        /* Sub kegiatan title */
                        .sm-sk-title { display:flex; align-items:flex-start; gap:0.4rem; flex-wrap:wrap; }
                        .sm-sk-title .sm-toggle-icon { flex-shrink:0; margin-top:1px; }
                        .sm-sk-name { font-weight:600; color:#0f172a; font-size:var(--fs-base); flex:1; min-width:0; word-break:break-word; overflow-wrap:anywhere; line-height:1.35; }
                        .sm-sk-meta { font-size:var(--fs-xs); color:#64748b; font-weight:500; margin-top:0.15rem; margin-left:1.55rem; letter-spacing:0.01em; }

                        /* Rekening cell */
                        .sm-rek-name { font-weight:500; color:#1e293b; font-size:var(--fs-base); word-break:break-word; overflow-wrap:anywhere; line-height:1.35; }
                        .sm-rek-kode { font-size:var(--fs-xs); color:#94a3b8; font-weight:500; margin-top:0.1rem; }

                        /* Status pill */
                        .sm-status-pill { display:inline-block; font-size:var(--fs-xs); font-weight:700; padding:0.15rem 0.5rem; border-radius:var(--radius-full,999px); margin-left:0.4rem; vertical-align:middle; }
                        .sm-pill-neg { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }

                        /* Filter buttons */
                        .semester-filter-btn { font-size:var(--fs-sm); font-weight:600; padding:0.3rem 0.7rem; border:1px solid #e2e8f0; background:#fff; color:#64748b; }
                        .semester-filter-btn:hover { background:#f1f5f9; color:#334155; }
                        .semester-filter-btn.active { background:#0284c7; color:#fff; border-color:#0284c7; }
                        .semester-filter-btn:not(:last-child) { border-right:none; }
                        #semesterRekapEmpty { display:none; padding:2rem; text-align:center; color:var(--gray-400); font-size:var(--fs-base); }

                        /* Hide inactive semester columns */
                        .sm-tbl[data-active-sem="1"] .sm-col-s2 { display:none; }
                        .sm-tbl[data-active-sem="2"] .sm-col-s1 { display:none; }

                        /* Mobile responsive: hide RAK & Realisasi columns, only show Sisa + Total Sisa */
                        @media (max-width: 767.98px) {
                            .sm-tbl { min-width:0; font-size:var(--fs-sm); }
                            .sm-tbl thead th, .sm-tbl tbody td { padding:0.5rem 0.55rem; }
                            .sm-tbl .sm-col-rak, .sm-tbl .sm-col-real { display:none; }
                            .sm-sisa { font-size:var(--fs-base); }
                            .sm-tab-btn { padding:0.55rem 0.85rem; font-size:var(--fs-sm); }
                            .sm-tab-btn small { display:none; }
                        }
                    
/* Mobile collapse for monthly absorption table */
@media (max-width: 767.98px) {
    .monthly-absorption-table:not(.is-expanded-months) {
        min-width: 0 !important;
        width: 100% !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-absorption-month {
        display: none !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-absorption-name {
        width: auto !important;
        min-width: 140px !important;
        max-width: 180px !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-sticky-pagu {
        left: auto !important;
        position: static !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-sticky-total {
        right: auto !important;
        position: static !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-sticky-percent {
        right: auto !important;
        position: static !important;
    }
}
</style>

                    <?php
                    // Helper: format rupiah singkat (16,4 Jt / 1,2 M / 0 Jt). Zero ditampilkan sebagai "—".
                    if (!function_exists('fmtRpKompak')) {
                        function fmtRpKompak($v) {
                            $v = (float) $v;
                            if ($v == 0) return '—';
                            return number_format($v, 0, ',', '.');
                        }
                    }
                    if (!function_exists('fmtRpFull')) {
                        function fmtRpFull($v) {
                            return 'Rp ' . number_format((float)$v, 0, ',', '.');
                        }
                    }
                    ?>

                    <div style="overflow-x:auto;border:1px solid #e2e8f0;border-radius:var(--radius-md,10px);max-height:600px;overflow-y:auto;">
                    <table class="sm-tbl" id="semesterRekapTable" data-active-sem="<?= $semesterAktif==2?'2':'1' ?>">
                        <colgroup>
                            <col style="width:42%;">
                            <col class="sm-col-s1 sm-col-rak" style="width:14%;">
                            <col class="sm-col-s1 sm-col-real" style="width:14%;">
                            <col class="sm-col-s1 sm-col-sisa" style="width:14%;">
                            <col class="sm-col-s2 sm-col-rak" style="width:14%;">
                            <col class="sm-col-s2 sm-col-real" style="width:14%;">
                            <col class="sm-col-s2 sm-col-sisa" style="width:14%;">
                            <col class="sm-col-total" style="width:16%;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Sub Kegiatan / Rekening</th>
                                <th class="sm-col-s1 sm-col-rak" title="RAK Semester 1">RAK</th>
                                <th class="sm-col-s1 sm-col-real" title="Realisasi Semester 1">Realisasi</th>
                                <th class="sm-col-s1 sm-col-sisa" title="Sisa Semester 1">Sisa</th>
                                <th class="sm-col-s2 sm-col-rak" title="RAK Semester 2">RAK</th>
                                <th class="sm-col-s2 sm-col-real" title="Realisasi Semester 2">Realisasi</th>
                                <th class="sm-col-s2 sm-col-sisa" title="Sisa Semester 2 (termasuk sisa sem 1)">Sisa</th>
                                <th class="sm-col-total" title="Total Sisa S1 + S2">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($semSK as $skid => $sk): ?>
                            <?php
                                $skSisaTotal = $sk['sisa_s1'] + $sk['sisa_s2'];
                                $skHasS1 = $sk['sisa_s1'] > 0;
                                $skHasS2 = $sk['sisa_s2'] > 0;
                                $skHasNegS1 = $sk['sisa_s1'] < 0;
                                $skHasNegS2 = $sk['sisa_s2'] < 0;
                                $skHasNeg = $skHasNegS1 || $skHasNegS2;
                            ?>
                            <tr class="sm-sk-row sm-collapsed"
                                data-search="<?= htmlspecialchars(strtolower($sk['nama'].' '.$sk['kode'])) ?>"
                                data-has-s1="<?= $skHasS1?'1':'0' ?>"
                                data-has-s2="<?= $skHasS2?'1':'0' ?>"
                                data-has-neg-s1="<?= $skHasNegS1?'1':'0' ?>"
                                data-has-neg-s2="<?= $skHasNegS2?'1':'0' ?>"
                                data-has-neg="<?= $skHasNeg?'1':'0' ?>"
                                onclick="this.classList.toggle('sm-collapsed'); toggleSemRekRows(this);">
                                <td>
                                    <div class="sm-sk-title">
                                        <i class="bi bi-chevron-down sm-toggle-icon"></i>
                                        <span class="sm-sk-name"><?= htmlspecialchars($sk['nama']) ?></span>
                                        <?php if ($skHasNeg): ?>
                                            <span class="sm-status-pill sm-pill-neg"><i class="bi bi-exclamation-triangle-fill"></i> Over</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="sm-sk-meta"><?= htmlspecialchars($sk['kode']) ?> &bull; <?= count($sk['rekening']) ?> rekening</div>
                                </td>
                                <td class="sm-col-s1 sm-col-rak sm-amount sm-amount-muted"><?= fmtRpKompak($sk['rak_s1']) ?></td>
                                <td class="sm-col-s1 sm-col-real sm-amount"><?= fmtRpKompak($sk['real_s1']) ?></td>
                                <td class="sm-col-s1 sm-col-sisa">
                                    <span class="sm-sisa <?= $sk['sisa_s1']>0?'sm-sisa-pos':($sk['sisa_s1']<0?'sm-sisa-neg':'sm-sisa-zero') ?>"><?= fmtRpKompak($sk['sisa_s1']) ?></span>
                                </td>
                                <td class="sm-col-s2 sm-col-rak sm-amount sm-amount-muted"><?= fmtRpKompak($sk['rak_s2']) ?></td>
                                <td class="sm-col-s2 sm-col-real sm-amount"><?= fmtRpKompak($sk['real_s2']) ?></td>
                                <td class="sm-col-s2 sm-col-sisa">
                                    <span class="sm-sisa <?= $sk['sisa_s2']>0?'sm-sisa-pos':($sk['sisa_s2']<0?'sm-sisa-neg':'sm-sisa-zero') ?>"><?= fmtRpKompak($sk['sisa_s2']) ?></span>
                                </td>
                                <td class="sm-col-total">
                                    <span class="sm-sisa <?= $skSisaTotal>0?'sm-sisa-pos':($skSisaTotal<0?'sm-sisa-neg':'sm-sisa-zero') ?>"><?= fmtRpKompak($skSisaTotal) ?></span>
                                </td>
                            </tr>
                            <?php foreach ($sk['rekening'] as $rid => $rek): ?>
                                <?php
                                    $rekHasS1    = $rek['sisa_s1'] > 0;
                                    $rekHasS2    = $rek['sisa_s2'] > 0;
                                    $rekHasNegS1 = $rek['sisa_s1'] < 0;
                                    $rekHasNegS2 = $rek['sisa_s2'] < 0;
                                    $rekHasNeg   = $rekHasNegS1 || $rekHasNegS2;
                                    $rekCarry    = $rek['carry_over'] ?? 0;
                                ?>
                                <tr class="sm-rek-row"
                                    data-sk="<?= $skid ?>"
                                    data-search="<?= htmlspecialchars(strtolower($rek['nama'].' '.$rek['kode'].' '.$sk['nama'])) ?>"
                                    data-has-s1="<?= $rekHasS1?'1':'0' ?>"
                                    data-has-s2="<?= $rekHasS2?'1':'0' ?>"
                                    data-has-neg-s1="<?= $rekHasNegS1?'1':'0' ?>"
                                    data-has-neg-s2="<?= $rekHasNegS2?'1':'0' ?>"
                                    data-has-neg="<?= $rekHasNeg?'1':'0' ?>">
                                    <td>
                                        <div class="sm-rek-name"><?= htmlspecialchars($rek['nama']) ?></div>
                                        <div class="sm-rek-kode"><?= htmlspecialchars($rek['kode']) ?></div>
                                    </td>
                                    <td class="sm-col-s1 sm-col-rak sm-amount sm-amount-muted"><?= fmtRpKompak($rek['rak_s1']) ?></td>
                                    <td class="sm-col-s1 sm-col-real sm-amount"><?= fmtRpKompak($rek['real_s1']) ?></td>
                                    <td class="sm-col-s1 sm-col-sisa">
                                        <span class="sm-sisa <?= $rek['sisa_s1']>0?'sm-sisa-pos':($rek['sisa_s1']<0?'sm-sisa-neg':'sm-sisa-zero') ?>"><?= fmtRpKompak($rek['sisa_s1']) ?></span>
                                    </td>
                                    <td class="sm-col-s2 sm-col-rak sm-amount sm-amount-muted"><?= fmtRpKompak($rek['rak_s2']) ?></td>
                                    <td class="sm-col-s2 sm-col-real sm-amount"><?= fmtRpKompak($rek['real_s2']) ?></td>
                                    <td class="sm-col-s2 sm-col-sisa">
                                        <span class="sm-sisa <?= $rek['sisa_s2']>0?'sm-sisa-pos':($rek['sisa_s2']<0?'sm-sisa-neg':'sm-sisa-zero') ?>"><?= fmtRpKompak($rek['sisa_s2']) ?></span>
                                        <?php if ($rekCarry > 0): ?>
                                        <div style="font-size:var(--fs-xs);color:#059669;font-weight:600;margin-top:0.1rem;white-space:normal;line-height:1.2;">+<?= fmtRpKompak($rekCarry) ?> sem 1</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="sm-col-total">
                                        <span class="sm-sisa <?= $rek['sisa_total']>0?'sm-sisa-pos':($rek['sisa_total']<0?'sm-sisa-neg':'sm-sisa-zero') ?>"><?= fmtRpKompak($rek['sisa_total']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <div id="semesterRekapEmpty"><i class="bi bi-search me-1"></i>Tidak ada hasil yang cocok</div>

                    <!-- Legend -->
                    <div style="margin-top:0.75rem;padding:0.625rem 0.875rem;background:#f8fafc;border-radius:var(--radius-sm,8px);border:1px solid var(--gray-200);font-size:var(--fs-xs);color:var(--gray-600);display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
                        <i class="bi bi-info-circle-fill" style="color:#0284c7;"></i>
                        <span><strong style="color:#059669;">Hijau</strong> = ada sisa (bisa ditarik)</span>
                        <span><strong style="color:#94a3b8;">Abu</strong> = sudah pas (tidak ada sisa)</span>
                        <span><strong style="color:#dc2626;">Merah</strong> = realisasi melebihi RAK semester</span>
                        <span><strong style="color:#059669;">+X sem 1</strong> = sisa S1 yang ditambahkan ke kuota S2</span>
                        <span style="margin-left:auto;color:#94a3b8;font-size:var(--fs-xs);">Sisa S2 = RAK S2 + Sisa Sem 1 &minus; Realisasi S2</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var table = document.getElementById('semesterRekapTable');
        if (!table) return;

        // Toggle rekening rows in semester rekap table
        window.toggleSemRekRows = function(skRow) {
            var next = skRow.nextElementSibling;
            while (next && next.classList.contains('sm-rek-row')) {
                if (skRow.classList.contains('sm-collapsed')) {
                    next.style.display = 'none';
                } else {
                    next.style.display = '';
                }
                next = next.nextElementSibling;
            }
        };

        // Initial: collapse all rekening rows
        document.querySelectorAll('#semesterRekapTable .sm-sk-row').forEach(function(row) {
            window.toggleSemRekRows(row);
        });

        // Tab semester switching
        var activeSemester = table.dataset.activeSem || '1';
        var contextLabel = document.getElementById('semesterFilterContextLabel');

        function updateFilterContextLabel() {
            if (contextLabel) contextLabel.textContent = 'S' + activeSemester;
        }

        document.querySelectorAll('.sm-tab-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.sm-tab-btn').forEach(function(b) {
                    b.classList.remove('active');
                    b.setAttribute('aria-selected', 'false');
                });
                this.classList.add('active');
                this.setAttribute('aria-selected', 'true');
                activeSemester = this.dataset.semester;
                table.dataset.activeSem = activeSemester;
                updateFilterContextLabel();
                applyFilters();
            });
        });

        // Search & filter
        var searchInput = document.getElementById('semesterSearchInput');
        var emptyMsg = document.getElementById('semesterRekapEmpty');
        var currentFilter = 'all';

        function applyFilters() {
            var q = (searchInput.value || '').toLowerCase().trim();
            var skRows = document.querySelectorAll('#semesterRekapTable .sm-sk-row');
            var anyVisible = false;

            // Active semester determines which has-flag to use for context-aware filters
            var sisaKey = activeSemester === '2' ? 'hasS2' : 'hasS1';
            var negKey  = activeSemester === '2' ? 'hasNegS2' : 'hasNegS1';

            skRows.forEach(function(skRow) {
                var rekRows = [];
                var n = skRow.nextElementSibling;
                while (n && n.classList.contains('sm-rek-row')) {
                    rekRows.push(n);
                    n = n.nextElementSibling;
                }

                var matchedRekenings = rekRows.filter(function(rek) {
                    var matchSearch = !q || rek.dataset.search.indexOf(q) >= 0;
                    var matchFilter = true;
                    if (currentFilter === 'sisa') matchFilter = rek.dataset[sisaKey] === '1';
                    else if (currentFilter === 'negatif') matchFilter = rek.dataset[negKey] === '1';
                    return matchSearch && matchFilter;
                });

                var skMatchSearch = !q || skRow.dataset.search.indexOf(q) >= 0;

                if (matchedRekenings.length > 0 || (skMatchSearch && currentFilter === 'all' && !q)) {
                    skRow.style.display = '';
                    anyVisible = true;
                    if (q || currentFilter !== 'all') {
                        skRow.classList.remove('sm-collapsed');
                    }
                    rekRows.forEach(function(rek) {
                        if (matchedRekenings.indexOf(rek) >= 0 && !skRow.classList.contains('sm-collapsed')) {
                            rek.style.display = '';
                        } else {
                            rek.style.display = 'none';
                        }
                    });
                } else {
                    skRow.style.display = 'none';
                    rekRows.forEach(function(rek) { rek.style.display = 'none'; });
                }
            });

            emptyMsg.style.display = anyVisible ? 'none' : 'block';
        }

        if (searchInput) {
            searchInput.addEventListener('input', applyFilters);
        }

        document.querySelectorAll('.semester-filter-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.semester-filter-btn').forEach(function(b) { b.classList.remove('active'); });
                this.classList.add('active');
                currentFilter = this.dataset.filter;
                applyFilters();
            });
        });
    })();
    </script>
    <?php endif; ?>
            </div>

            <!-- Tab 3: Struktur Anggaran -->
            <div class="tab-pane fade" id="tab-struktur" role="tabpanel" aria-labelledby="tab-struktur-btn">
                <!-- Hierarchical Budget Structure -->
    <?php if (!empty($hierarchicalData)): ?>
    <div class="row mb-4" id="section-struktur">
        <div class="col-12 animate-fade-in-up delay-6">
            <div class="card rounded-4">
                <div class="section-header">
                    <div>
                        <h5 class="section-title"><i class="bi bi-diagram-3 me-2" style="color:var(--primary);"></i>Struktur Anggaran & Realisasi</h5>
                        <p class="section-subtitle">Hierarki program hingga rekening belanja</p>
                    </div>
                </div>
                <div class="card-body" style="padding:1.5rem; overflow-x:auto;">
                    <style>
                        .h-tree { position: relative; padding-left: 1.5rem; font-family: 'Inter', sans-serif; min-width: 900px; }
                        .h-node { position: relative; padding: 0.75rem 0; }
                        .h-node::before { content: ''; position: absolute; left: -1.5rem; top: 2.25rem; bottom: -0.75rem; width: 2px; background: var(--gray-200); }
                        .h-node:last-child::before { display: none; }
                        .h-line-h { position: absolute; left: -1.5rem; top: 1.5rem; width: 1.5rem; height: 2px; background: var(--gray-200); }
                        
                        .h-item { display: flex; align-items: center; justify-content: space-between; background: #fff; padding: 0.75rem 1rem; border-radius: var(--radius-md); transition: background 0.2s; border: 1px solid transparent; cursor: pointer; }
                        .h-item:hover { background: rgba(0,0,0,0.03); border-color: var(--gray-200); }
                        .h-item.has-children { cursor: pointer; }
                        .h-toggle { width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; transition: transform 0.2s ease, background 0.2s; color: #334155; font-size: 20px; margin-right: 0.35rem; }
                        .h-item.has-children:hover .h-toggle { background: var(--gray-200); color: #1e293b; }
                        .tree-collapsed > .h-tree { display: none !important; }
                        .tree-collapsed > .h-item .h-toggle { transform: rotate(-90deg); }
                        
                        .h-item-left { display: flex; align-items: flex-start; gap: 0.5rem; flex: 1; }
                        .h-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--primary); margin-top: 0.35rem; position: relative; z-index: 2; box-shadow: 0 0 0 3px #fff; flex-shrink: 0; }
                        
                        .h-badge { font-size:var(--fs-xs); font-weight: 700; padding: 0.2rem 0.6rem; border-radius: var(--radius-full); margin-right: 0.5rem; color:#fff; }
                        .badge-seksi { background: #020617; }
                        .badge-program { background: #0f172a; }
                        .badge-kegiatan { background: #1e293b; }
                        .badge-sub { background: #334155; }
                        .badge-belanja { background: #475569; }
                        
                        .h-title { font-size:var(--fs-base); font-weight: 700; color: var(--gray-800); margin-bottom: 0.25rem; }
                        .h-code { font-size:var(--fs-xs); color: var(--gray-500); }
                        
                        .h-card-sm { display: flex; align-items: center; gap: 0.75rem; min-width: 160px; }
                        .h-icon-box { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size:var(--fs-lg); flex-shrink: 0; }
                        .icon-box-anggaran { background: #ecfeff; color: #06b6d4; }
                        .icon-box-realisasi { background: #f1f5f9; color: #f59e0b; }
                        
                        .h-val-title { font-size:var(--fs-base); font-weight: 700; color: var(--gray-800); margin-bottom:0.1rem; }
                        .h-val-sub { font-size:var(--fs-xs); color: var(--gray-500); }
                        
                        .h-progress-wrap { display: flex; align-items: center; gap: 0.75rem; min-width: 150px; justify-content:flex-end; }
                        .h-progress-bar { width: 60px; height: 4px; border-radius: 2px; background: var(--gray-200); position: relative; }
                        .h-progress-fill { position: absolute; left: 0; top: 0; bottom: 0; border-radius: 2px; }
                        .progress-fill-safe { background: var(--primary); }
                    
/* Mobile collapse for monthly absorption table */
@media (max-width: 767.98px) {
    .monthly-absorption-table:not(.is-expanded-months) {
        min-width: 0 !important;
        width: 100% !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-absorption-month {
        display: none !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-absorption-name {
        width: auto !important;
        min-width: 140px !important;
        max-width: 180px !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-sticky-pagu {
        left: auto !important;
        position: static !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-sticky-total {
        right: auto !important;
        position: static !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-sticky-percent {
        right: auto !important;
        position: static !important;
    }
}
</style>

                    <?php
                    function renderTreeNodes($nodes, $level = 0) {
                        $badges = [
                            0 => ['name'=>'Seksi','class'=>'badge-seksi'], 
                            1 => ['name'=>'Program','class'=>'badge-program'], 
                            2 => ['name'=>'Kegiatan','class'=>'badge-kegiatan'], 
                            3 => ['name'=>'Sub Kegiatan','class'=>'badge-sub'], 
                            4 => ['name'=>'Belanja','class'=>'badge-belanja']
                        ];
                        $badge = $badges[$level] ?? $badges[4];
                        
                        foreach ($nodes as $node) {
                            $pct = $node['pagu'] > 0 ? ($node['realisasi'] / $node['pagu']) * 100 : 0;
                            $paguStr = number_format($node['pagu'], 0, ',', '.');
                            $realStr = number_format($node['realisasi'], 0, ',', '.');
                            
                            $hasChildren = !empty($node['children']);
                            $itemClass = 'h-item' . ($hasChildren ? ' has-children' : '');
                            $nodeClass = 'h-node pt-0 pb-0' . ($hasChildren ? ' tree-collapsed' : '');
                            $attrs = $hasChildren ? ' role="button" tabindex="0" aria-expanded="false" onclick="toggleTreeNode(this)" onkeydown="handleTreeKey(event, this)"' : '';
                            
                            echo '<div class="' . $nodeClass . '">';
                            if ($level > 0) echo '<div class="h-line-h"></div>';
                            
                            echo '<div class="' . $itemClass . '"' . $attrs . '>';
                            // Left Section (Toggle, Dot, Title, Code)
                            echo '<div class="h-item-left align-items-center">';
                            if ($hasChildren) {
                                echo '<div class="h-toggle"><i class="bi bi-chevron-down"></i></div>';
                            } else {
                                echo '<div style="width: 24px; margin-right: 0.25rem; flex-shrink: 0;"></div>';
                            }
                            
                            if ($level > 0) echo '<div class="h-dot" style="margin-top:0; background: '.($level==4?'var(--gray-400)':'var(--primary-light)').';"></div>';
                            else echo '<div class="h-dot" style="margin-top:0; background: var(--primary);"></div>';
                            
                            echo '<div class="ms-1" style="flex:1;">';
                            echo '<div class="h-title" style="'.($level==4?'font-weight:600;color:var(--gray-700);':'').'">' . htmlspecialchars($node['nama']) . '</div>';
                            echo '<div class="h-code"><span class="h-badge ' . $badge['class'] . '">' . $badge['name'] . '</span> Kode: ' . $node['kode'] . '</div>';
                            echo '</div>';
                            echo '</div>'; // End left
                            
                            // Center Section (Anggaran)
                            echo '<div class="h-card-sm ms-3 d-none d-lg-flex">';
                            echo '<div class="h-icon-box icon-box-anggaran"><i class="bi bi-calculator"></i></div>';
                            echo '<div><div class="h-val-title">Rp ' . $paguStr . '</div><div class="h-val-sub">Anggaran</div></div>';
                            echo '</div>';
                            
                            // Center Section (% Realisasi)
                            echo '<div class="h-progress-wrap ms-3 d-none d-md-flex">';
                            echo '<span style="font-size:var(--fs-base); font-weight:700; color:var(--primary);">' . number_format($pct, 2) . '%</span>';
                            echo '<div class="h-progress-bar"><div class="h-progress-fill progress-fill-safe" style="width:' . min($pct, 100) . '%;"></div></div>';
                            echo '</div>';
                            
                            // Right Section (Realisasi)
                            echo '<div class="h-card-sm ms-3 ps-3 d-none d-sm-flex" style="border-left: 1px dashed var(--gray-200);">';
                            echo '<div class="h-icon-box icon-box-realisasi"><i class="bi bi-wallet2"></i></div>';
                            echo '<div><div class="h-val-title">Rp ' . $realStr . '</div><div class="h-val-sub">Realisasi Riil</div></div>';
                            echo '</div>';
                            
                            echo '</div>'; // End item
                            
                            if ($hasChildren) {
                                echo '<div class="h-tree">';
                                renderTreeNodes($node['children'], $level + 1);
                                echo '</div>';
                            }
                            
                            echo '</div>'; // End node
                        }
                    }
                    ?>

                    <div class="h-tree" style="padding-left:0;">
                        <?php renderTreeNodes($hierarchicalData); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
            </div>

            <!-- Tab 4: Deviasi RAK -->
            <div class="tab-pane fade" id="tab-deviasi" role="tabpanel" aria-labelledby="tab-deviasi-btn">
                <!-- ===== Rincian Deviasi RAK — collapsible tree ===== -->
    <?php if (!empty($deviationDetails)): ?>
    <?php
    // Calculate summary metrics for deviation section
    $totalNilaiUnder = 0;
    $totalSubKegiatanDeviasi = count($deviationDetails);
    $totalRekeningDeviasi = 0;
    $riskCounts = ['kritis' => 0, 'sedang' => 0, 'rendah' => 0];
    foreach ($deviationDetails as $sk) {
        $totalRekeningDeviasi += count($sk['rekening']);
        // Calculate risk level per sub kegiatan
        $skTotalRak = 0; $skTotalDeviasi = 0;
        foreach ($sk['rekening'] as $rek) {
            foreach ($rek['deviations'] as $dev) {
                if ($dev['type'] === 'under') {
                    $totalNilaiUnder += abs($dev['selisih']);
                    $skTotalDeviasi += abs($dev['selisih']);
                }
                $skTotalRak += $dev['rak'];
            }
        }
        $deviasiPct = $skTotalRak > 0 ? ($skTotalDeviasi / $skTotalRak) * 100 : 0;
        if ($deviasiPct > 50) $riskCounts['kritis']++;
        elseif ($deviasiPct >= 20) $riskCounts['sedang']++;
        else $riskCounts['rendah']++;
    }
    ?>
    <div class="row mb-4" id="section-deviasi">
        <div class="col-12 animate-fade-in-up delay-5">
            <div class="card rounded-4">
                <div class="section-header" style="padding-bottom:0.75rem;">
                    <div>
                        <h5 class="section-title"><i class="bi bi-exclamation-triangle-fill me-2" style="color:#d97706;"></i>Rincian Deviasi dari RAK</h5>
                        <p class="section-subtitle">Sub kegiatan &amp; rekening yang realisasinya tidak sesuai rencana RAK</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span style="font-size:var(--fs-xs);font-weight:700;padding:0.3rem 0.875rem;border-radius:var(--radius-full);background:#fffbeb;color:#b45309;border:1px solid #fde68a;">
                            <i class="bi bi-diagram-3 me-1"></i><?= $totalSubKegiatanDeviasi ?> Sub Kegiatan
                        </span>
                        <button type="button" class="btn-export-section" onclick="exportDeviasiToExcel('deviasi-rak-<?= $stats['tahun'] ?>.xlsx')" data-bs-toggle="tooltip" data-bs-placement="top" title="Export seluruh daftar deviasi RAK ke Excel">
                            <i class="bi bi-file-earmark-excel-fill text-success"></i> Export XLSX
                        </button>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div style="padding:0 1.5rem;">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:var(--radius-md,10px);padding:1rem 1.25rem;">
                                <div style="font-size:var(--fs-sm);font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.35rem;">Total Nilai Under RAK</div>
                                <div style="font-size:var(--fs-lg);font-weight:800;color:#dc2626;letter-spacing:-0.02em;">Rp <?= number_format($totalNilaiUnder, 0, ',', '.') ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:var(--radius-md,10px);padding:1rem 1.25rem;">
                                <div style="font-size:var(--fs-sm);font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.35rem;">Sub Kegiatan Berdeviasi</div>
                                <div style="font-size:var(--fs-lg);font-weight:800;color:#b45309;letter-spacing:-0.02em;"><?= $totalSubKegiatanDeviasi ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:var(--radius-md,10px);padding:1rem 1.25rem;">
                                <div style="font-size:var(--fs-sm);font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.35rem;">Total Rekening Berdeviasi</div>
                                <div style="font-size:var(--fs-lg);font-weight:800;color:#334155;letter-spacing:-0.02em;"><?= $totalRekeningDeviasi ?></div>
                            </div>
                        </div>
                    </div>
                    <p style="font-size:var(--fs-sm);color:var(--gray-500);margin-bottom:0.75rem;"><i class="bi bi-cursor-fill me-1"></i>Klik baris sub kegiatan untuk melihat rincian rekening</p>
                    <!-- Risk Level Filter -->
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                        <span style="font-size:var(--fs-sm);font-weight:600;color:#475569;">Tingkat Risiko:</span>
                        <button type="button" class="btn btn-sm deviasi-risk-btn active" data-risk="all" onclick="filterDeviasiRisk('all',this)">Semua <span style="opacity:0.7;">(<?= $totalSubKegiatanDeviasi ?>)</span></button>
                        <button type="button" class="btn btn-sm deviasi-risk-btn" data-risk="kritis" onclick="filterDeviasiRisk('kritis',this)" style="--btn-active-bg:#dc2626;">Kritis <span style="opacity:0.7;">(<?= $riskCounts['kritis'] ?>)</span></button>
                        <button type="button" class="btn btn-sm deviasi-risk-btn" data-risk="sedang" onclick="filterDeviasiRisk('sedang',this)" style="--btn-active-bg:#d97706;">Sedang <span style="opacity:0.7;">(<?= $riskCounts['sedang'] ?>)</span></button>
                        <button type="button" class="btn btn-sm deviasi-risk-btn" data-risk="rendah" onclick="filterDeviasiRisk('rendah',this)" style="--btn-active-bg:#d97706;">Rendah <span style="opacity:0.7;">(<?= $riskCounts['rendah'] ?>)</span></button>
                    </div>
                    <style>
                        .deviasi-risk-btn { font-size:var(--fs-sm); font-weight:600; padding:0.3rem 0.7rem; border:1px solid #e2e8f0; background:#fff; color:#64748b; border-radius:999px; }
                        .deviasi-risk-btn:hover { background:#f1f5f9; color:#334155; }
                        .deviasi-risk-btn.active { background:var(--btn-active-bg,#0284c7); color:#fff; border-color:var(--btn-active-bg,#0284c7); }
                    
/* Mobile collapse for monthly absorption table */
@media (max-width: 767.98px) {
    .monthly-absorption-table:not(.is-expanded-months) {
        min-width: 0 !important;
        width: 100% !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-absorption-month {
        display: none !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-absorption-name {
        width: auto !important;
        min-width: 140px !important;
        max-width: 180px !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-sticky-pagu {
        left: auto !important;
        position: static !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-sticky-total {
        right: auto !important;
        position: static !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-sticky-percent {
        right: auto !important;
        position: static !important;
    }
}
</style>
                </div>
                <div class="card-body" style="padding:1.5rem;overflow-x:auto;">
                <style>
                    /* Deviation collapsible tree — prefix .d- agar tidak konflik .h- */
                    .d-tree          { position:relative; padding-left:1.5rem; font-family:'Inter',sans-serif; min-width:700px; }
                    .d-node          { position:relative; padding:0.4rem 0; }
                    .d-node::before  { content:''; position:absolute; left:-1.5rem; top:2rem; bottom:-0.4rem; width:2px; background:var(--gray-200); }
                    .d-node:last-child::before { display:none; }
                    .d-line-h        { position:absolute; left:-1.5rem; top:1.25rem; width:1.5rem; height:2px; background:var(--gray-200); }

                    .d-item          { display:flex; align-items:center; justify-content:space-between; background:#fff; padding:0.7rem 1rem; border-radius:var(--radius-md); transition:background .2s; border:1px solid transparent; cursor:pointer; }
                    .d-item:hover    { background:rgba(0,0,0,0.03); border-color:var(--gray-200); }
                    .d-toggle        { width:28px; height:28px; display:inline-flex; align-items:center; justify-content:center; border-radius:6px; transition:transform .2s ease,background .2s; color:#334155; font-size:20px; margin-right:.35rem; flex-shrink:0; }
                    .d-item:hover .d-toggle { background:var(--gray-200); color:#1e293b; }

                    /* collapsed = hide child .d-tree + rotate chevron */
                    .d-collapsed > .d-tree  { display:none !important; }
                    .d-collapsed > .d-item .d-toggle { transform:rotate(-90deg); }

                    .d-dot-sk        { width:10px;height:10px;border-radius:50%;background:#d97706;position:relative;z-index:2;box-shadow:0 0 0 3px #fff;flex-shrink:0; }
                    .d-dot-rek       { width:8px;height:8px;border-radius:50%;background:#94a3b8;position:relative;z-index:2;box-shadow:0 0 0 3px #fff;flex-shrink:0; }

                    .d-badge-sk      { font-size:var(--fs-xs);font-weight:700;padding:.15rem .55rem;border-radius:var(--radius-full);background:#92400e;color:#fff;margin-right:.4rem; }
                    .d-badge-rek     { font-size:var(--fs-xs);font-weight:700;padding:.15rem .55rem;border-radius:var(--radius-full);background:#475569;color:#fff;margin-right:.4rem; }
                    .d-title         { font-size:var(--fs-base);font-weight:700;color:var(--gray-800); }
                    .d-sub           { font-size:var(--fs-xs);color:var(--gray-500);margin-top:.1rem; }

                    .d-pill-over     { font-size:var(--fs-xs);font-weight:700;padding:.2rem .55rem;border-radius:var(--radius-full);background:#fef2f2;border:1px solid #fecaca;color:var(--danger);white-space:nowrap; }
                    .d-pill-under    { font-size:var(--fs-xs);font-weight:700;padding:.2rem .55rem;border-radius:var(--radius-full);background:#fffbeb;border:1px solid #fde68a;color:#b45309;white-space:nowrap; }

                    .d-dev-tbl       { width:100%;border-collapse:collapse;font-size:var(--fs-sm); }
                    .d-dev-tbl th    { padding:.35rem .75rem;font-size:var(--fs-xs);font-weight:600;color:var(--gray-500);background:var(--gray-50);border-bottom:1px solid var(--gray-200); }
                    .d-dev-tbl td    { padding:.35rem .75rem;border-bottom:1px solid var(--gray-100);color:var(--gray-700); }
                    .d-dev-tbl tr:last-child td { border-bottom:none; }
                
/* Mobile collapse for monthly absorption table */
@media (max-width: 767.98px) {
    .monthly-absorption-table:not(.is-expanded-months) {
        min-width: 0 !important;
        width: 100% !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-absorption-month {
        display: none !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-absorption-name {
        width: auto !important;
        min-width: 140px !important;
        max-width: 180px !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-sticky-pagu {
        left: auto !important;
        position: static !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-sticky-total {
        right: auto !important;
        position: static !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-sticky-percent {
        right: auto !important;
        position: static !important;
    }
}
</style>

                <?php
                $bulanNamesLongDev = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                                      7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
                ?>
                <div class="d-tree" style="padding-left:0;">
                <?php foreach ($deviationDetails as $skid => $sk):
                    /* hitung total over/under untuk sub kegiatan */
                    $skOver = 0; $skUnder = 0;
                    $skTotalRakDev = 0; $skTotalDeviasiAbs = 0;
                    foreach ($sk['rekening'] as $r) {
                        foreach ($r['deviations'] as $d) {
                            $d['type']==='over' ? $skOver++ : $skUnder++;
                            $skTotalRakDev += $d['rak'];
                            $skTotalDeviasiAbs += abs($d['selisih']);
                        }
                    }
                    $skDevPct = $skTotalRakDev > 0 ? ($skTotalDeviasiAbs / $skTotalRakDev) * 100 : 0;
                    $skRiskLevel = $skDevPct > 50 ? 'kritis' : ($skDevPct >= 20 ? 'sedang' : 'rendah');
                    $skRiskLabel = $skRiskLevel === 'kritis' ? 'Kritis' : ($skRiskLevel === 'sedang' ? 'Sedang' : 'Rendah');
                    $skRiskColor = $skRiskLevel === 'kritis' ? 'var(--status-danger, #dc2626)' : 'var(--status-warning, #d97706)';
                    $skRiskBg = $skRiskLevel === 'kritis' ? 'var(--status-danger-bg, #fef2f2)' : 'var(--status-warning-bg, #fffbeb)';
                    $skRiskBorder = $skRiskLevel === 'kritis' ? 'var(--status-danger-border, #fecaca)' : 'var(--status-warning-border, #fde68a)';
                ?>
                    <!-- ── Sub Kegiatan node (default: COLLAPSED) ── -->
                    <div class="d-node d-collapsed" data-risk="<?= $skRiskLevel ?>" style="padding-top:0;">
                        <div class="d-item" role="button" tabindex="0" aria-expanded="false" onclick="toggleDeviasiNode(this)" onkeydown="handleTreeKey(event, this)">
                            <div style="display:flex;align-items:flex-start;gap:.5rem;flex:1;">
                                <div class="d-toggle"><i class="bi bi-chevron-down"></i></div>
                                <div class="d-dot-sk" style="margin-top:.35rem;"></div>
                                <div style="flex:1;min-width:0;">
                                    <div class="d-title">
                                        <span class="d-badge-sk">Sub Kegiatan</span><?= htmlspecialchars($sk['nama']) ?>
                                        <span class="badge-risk badge-risk-<?= $skRiskLevel ?>" style="margin-left:.4rem;vertical-align:middle;"><?= $skRiskLabel ?></span>
                                    </div>
                                    <div class="d-sub">Kode: <?= htmlspecialchars($sk['kode']) ?> &bull; <?= count($sk['rekening']) ?> rekening berdeviasi &bull; Deviasi <?= number_format($skDevPct,2) ?>% dari RAK</div>
                                </div>
                            </div>
                            <div class="d-none d-md-flex align-items-center me-2" onclick="event.stopPropagation();" style="max-width:240px;flex-shrink:0;">
                                <input type="text" class="deviasi-catatan-input" data-sk-id="<?= $skid ?>" placeholder="Tulis catatan tindak lanjut..." maxlength="100" onchange="saveDeviasiNote(this)" onclick="event.stopPropagation()" title="Catatan tindak lanjut deviasi (tersimpan otomatis)">
                            </div>
                            <div style="display:flex;gap:.4rem;flex-shrink:0;margin-left:0.5rem;">
                                <?php if ($skOver  > 0): ?><span class="d-pill-over" style="background:#eff6ff;border-color:#bfdbfe;color:#2563eb;"><i class="bi bi-arrow-up-circle-fill me-1"></i><?= $skOver  ?> Over</span><?php endif; ?>
                                <?php if ($skUnder > 0): ?><span class="d-pill-under" style="background:#fef2f2;border-color:#fecaca;color:#dc2626;"><i class="bi bi-arrow-down-circle-fill me-1"></i><?= $skUnder ?> Under</span><?php endif; ?>
                            </div>
                        </div>

                        <!-- child tree: rekening list -->
                        <div class="d-tree">
                        <?php foreach ($sk['rekening'] as $rid => $rek):
                            $rOver  = count(array_filter($rek['deviations'], fn($d)=>$d['type']==='over'));
                            $rUnder = count(array_filter($rek['deviations'], fn($d)=>$d['type']==='under'));
                        ?>
                            <!-- ── Rekening node (default: COLLAPSED) ── -->
                            <div class="d-node d-collapsed">
                                <div class="d-line-h"></div>
                                <div class="d-item" role="button" tabindex="0" aria-expanded="false" onclick="toggleDeviasiNode(this)" onkeydown="handleTreeKey(event, this)">
                                    <div style="display:flex;align-items:flex-start;gap:.5rem;flex:1;">
                                        <div class="d-toggle"><i class="bi bi-chevron-down"></i></div>
                                        <div class="d-dot-rek" style="margin-top:.35rem;"></div>
                                        <div style="flex:1;">
                                            <div class="d-title" style="font-weight:600;color:var(--gray-700);"><span class="d-badge-rek">Rekening</span><?= htmlspecialchars($rek['nama']) ?></div>
                                            <div class="d-sub">Kode: <?= htmlspecialchars($rek['kode']) ?> &bull; <?= count($rek['deviations']) ?> bulan berdeviasi</div>
                                        </div>
                                    </div>
                                    <div style="display:flex;gap:.4rem;flex-shrink:0;margin-left:1rem;">
                                        <?php if ($rOver  > 0): ?><span class="d-pill-over" style="background:#eff6ff;border-color:#bfdbfe;color:#2563eb;"><i class="bi bi-arrow-up-circle-fill me-1"></i><?= $rOver  ?> bln</span><?php endif; ?>
                                        <?php if ($rUnder > 0): ?><span class="d-pill-under" style="background:#fef2f2;border-color:#fecaca;color:#dc2626;"><i class="bi bi-arrow-down-circle-fill me-1"></i><?= $rUnder ?> bln</span><?php endif; ?>
                                    </div>
                                </div>

                                <!-- child tree: tabel bulan -->
                                <div class="d-tree" style="padding-left:1.5rem;">
                                    <div style="margin:.25rem 0 .5rem .5rem;overflow-x:auto;">
                                        <table class="d-dev-tbl">
                                            <thead>
                                                <tr>
                                                    <th>Bulan</th>
                                                    <th class="text-end">RAK (Rencana)</th>
                                                    <th class="text-end">Realisasi</th>
                                                    <th class="text-end">Selisih</th>
                                                    <th class="text-center">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($rek['deviations'] as $dev):
                                                $isOver     = $dev['type'] === 'over';
                                                $selAbs     = abs($dev['selisih']);
                                                $selColor   = $isOver ? '#2563eb' : '#dc2626';
                                                $rowBg      = $isOver ? 'rgba(239,246,255,.45)' : 'rgba(254,242,242,.45)';
                                                $sBg        = $isOver ? '#eff6ff' : '#fef2f2';
                                                $sBorder    = $isOver ? '#bfdbfe' : '#fecaca';
                                                $sColor     = $isOver ? '#2563eb' : '#dc2626';
                                                $sIcon      = $isOver ? 'bi-arrow-up-circle-fill' : 'bi-arrow-down-circle-fill';
                                                $sLabel     = $isOver ? 'Over RAK' : 'Under RAK';
                                            ?>
                                            <tr style="background:<?= $rowBg ?>;">
                                                <td style="font-weight:600;"><?= $bulanNamesLongDev[$dev['bulan']] ?></td>
                                                <td class="text-end" style="color:var(--gray-600);">Rp <?= number_format($dev['rak'],0,',','.') ?></td>
                                                <td class="text-end" style="font-weight:700;color:var(--gray-800);">Rp <?= number_format($dev['realisasi'],0,',','.') ?></td>
                                                <td class="text-end" style="font-weight:700;color:<?= $selColor ?>;"><?= $isOver ? '+' : '&minus;' ?>Rp <?= number_format($selAbs,0,',','.') ?></td>
                                                <td class="text-center">
                                                    <span style="font-size:var(--fs-xs);font-weight:700;padding:.2rem .55rem;border-radius:var(--radius-full);background:<?= $sBg ?>;border:1px solid <?= $sBorder ?>;color:<?= $sColor ?>;white-space:nowrap;">
                                                        <i class="bi <?= $sIcon ?> me-1"></i><?= $sLabel ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>

                </div>
            </div>
        </div>
    </div>
    <script>
    function filterDeviasiRisk(level, btn) {
        document.querySelectorAll('.deviasi-risk-btn').forEach(function(b) { b.classList.remove('active'); });
        btn.classList.add('active');
        var nodes = document.querySelectorAll('#section-deviasi .d-node[data-risk]');
        nodes.forEach(function(node) {
            if (level === 'all' || node.dataset.risk === level) {
                node.style.display = '';
            } else {
                node.style.display = 'none';
            }
        });
    }
    </script>
    <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Detail Breakdown — Compact Summary (tidak duplikat tree di bawah) -->
    <?php if (!empty($breakdownData)): ?>
    <div class="row mb-4" id="section-breakdown">
        <div class="col-12 animate-fade-in-up delay-5">
            <div class="card rounded-4 overflow-hidden">
                <div class="section-header" style="padding-bottom:0.75rem;">
                    <div>
                        <h5 class="section-title"><i class="bi bi-bar-chart-steps me-2" style="color:var(--primary);"></i>Ringkasan Serapan per <?= empty($filters['seksi_id'])?'Seksi':(empty($filters['program_id'])?'Program':(empty($filters['kegiatan_id'])?'Kegiatan':'Sub Kegiatan')) ?></h5>
                        <p class="section-subtitle">Ikhtisar pagu &amp; realisasi per unit — klik baris untuk detail di Struktur Anggaran</p>
                    </div>
                    <button type="button" class="btn-export-section" onclick="exportBreakdownToExcel('breakdown-seksi-<?= $stats['tahun'] ?>.xlsx')" data-bs-toggle="tooltip" data-bs-placement="top" title="Export ringkasan per unit ke Excel">
                        <i class="bi bi-file-earmark-excel-fill text-success"></i> Export XLSX
                    </button>
                </div>
                <div class="card-body" style="padding:0 1.5rem 1.5rem;">
                    <div class="row g-2">
                    <?php foreach($breakdownData as $name => $data):
                        $sisa  = $data['pagu'] - $data['realisasi'];
                        $pct   = $data['pagu'] > 0 ? ($data['realisasi'] / $data['pagu']) * 100 : 0;
                        $rakKum = (float) ($data['rak_kumulatif'] ?? 0);

                        // Evaluasi status serapan seksi terhadap target RAK kumulatif seksi itu sendiri
                        if ($rakKum > 0) {
                            $capaianRakSeksi = ($data['realisasi'] / $rakKum) * 100;
                            if ($capaianRakSeksi >= 90) {
                                $clr  = 'var(--status-success, #059669)';
                                $bg   = 'var(--status-success-bg, #ecfdf5)';
                                $bdr  = 'var(--status-success-border, #bbf7d0)';
                                $lbl  = 'Aman';
                                $icon = 'bi-check-circle-fill';
                            } elseif ($capaianRakSeksi >= 70) {
                                $clr  = 'var(--status-warning, #d97706)';
                                $bg   = 'var(--status-warning-bg, #fffbeb)';
                                $bdr  = 'var(--status-warning-border, #fde68a)';
                                $lbl  = 'Perlu Dipacu';
                                $icon = 'bi-exclamation-triangle-fill';
                            } else {
                                $clr  = 'var(--status-danger, #dc2626)';
                                $bg   = 'var(--status-danger-bg, #fef2f2)';
                                $bdr  = 'var(--status-danger-border, #fecaca)';
                                $lbl  = 'Tertinggal';
                                $icon = 'bi-exclamation-octagon-fill';
                            }
                        } else {
                            // Fallback jika belum ada data RAK
                            if ($pct >= 80) {
                                $clr  = 'var(--status-success, #059669)';
                                $bg   = 'var(--status-success-bg, #ecfdf5)';
                                $bdr  = 'var(--status-success-border, #bbf7d0)';
                                $lbl  = 'Aman';
                                $icon = 'bi-check-circle-fill';
                            } elseif ($pct >= 50) {
                                $clr  = 'var(--status-warning, #d97706)';
                                $bg   = 'var(--status-warning-bg, #fffbeb)';
                                $bdr  = 'var(--status-warning-border, #fde68a)';
                                $lbl  = 'Perlu Dipacu';
                                $icon = 'bi-exclamation-triangle-fill';
                            } else {
                                $clr  = 'var(--status-danger, #dc2626)';
                                $bg   = 'var(--status-danger-bg, #fef2f2)';
                                $bdr  = 'var(--status-danger-border, #fecaca)';
                                $lbl  = 'Tertinggal';
                                $icon = 'bi-exclamation-octagon-fill';
                            }
                        }
                    ?>
                    <div class="col-md-4">
                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:var(--radius-md,10px);padding:1rem 1.25rem;position:relative;overflow:hidden;">
                            <!-- Progress strip di bagian bawah card -->
                            <div style="position:absolute;bottom:0;left:0;height:3px;width:<?= min($pct,100) ?>%;background:<?= $clr ?>;border-radius:0 0 0 var(--radius-md,10px);"></div>
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:0.5rem;">
                                <div style="font-size:var(--fs-sm);font-weight:700;color:#334155;line-height:1.3;flex:1;padding-right:0.5rem;"><?= htmlspecialchars($name) ?></div>
                                <span style="font-size:var(--fs-xs);font-weight:700;padding:0.2rem 0.55rem;border-radius:999px;background:<?= $bg ?>;border:1px solid <?= $bdr ?>;color:<?= $clr ?>;white-space:nowrap;flex-shrink:0;">
                                    <i class="bi <?= $icon ?> me-1"></i><?= $lbl ?>
                                </span>
                            </div>
                            <div style="font-size:var(--fs-xl);font-weight:800;color:<?= $clr ?>;letter-spacing:-0.02em;line-height:1;"><?= number_format($pct,1) ?>%</div>
                            <div style="font-size:var(--fs-xs);color:#64748b;margin-top:0.35rem;">Realisasi <strong style="color:#334155;">Rp <?= number_format($data['realisasi'],0,',','.') ?></strong> dari Rp <?= number_format($data['pagu'],0,',','.') ?></div>
                            <div style="font-size:var(--fs-xs);color:<?= $sisa < 0 ? '#dc2626' : '#94a3b8' ?>;margin-top:0.15rem;">Sisa: <?= $sisa < 0 ? '-' : '' ?>Rp <?= number_format(abs($sisa),0,',','.') ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    </div>
                    <div style="margin-top:0.75rem;font-size:var(--fs-xs);color:#64748b;"><i class="bi bi-info-circle me-1"></i>Detail rekening hingga sub kegiatan tersedia di <a href="#section-struktur" style="color:var(--primary);font-weight:600;">Struktur Anggaran</a> di bawah.</div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- ========== Modal Export Excel ========== -->
<div class="modal fade" id="modalExportExcel" tabindex="-1" aria-labelledby="modalExportLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4" style="border:none;box-shadow:0 20px 60px rgba(0,0,0,.15);">

      <!-- Header -->
      <div class="modal-header border-0 pb-0" style="background:linear-gradient(135deg,#16a34a,#15803d);border-radius:1rem 1rem 0 0;padding:1.25rem 1.5rem;">
        <div class="d-flex align-items-center gap-2">
          <div style="width:38px;height:38px;background:rgba(255,255,255,.2);border-radius:8px;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-file-earmark-excel-fill" style="color:#fff;font-size:var(--fs-lg);"></i>
          </div>
          <div>
            <h5 class="modal-title mb-0" id="modalExportLabel" style="color:#fff;font-weight:700;font-size:var(--fs-md);">Export Laporan Excel</h5>
            <p class="mb-0" style="color:rgba(255,255,255,.8);font-size:var(--fs-sm);">Pilih periode realisasi yang akan diekspor</p>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="opacity:.8;"></button>
      </div>

      <!-- Body -->
      <div class="modal-body" style="padding:1.5rem;">
        <form id="formExportExcel">
          <div class="row g-3">

            <!-- Tahun -->
            <div class="col-6">
              <label class="form-label fw-semibold" style="font-size:var(--fs-base);color:#374151;"><i class="bi bi-calendar-year me-1" style="color:#16a34a;"></i>Tahun</label>
              <select class="form-select" id="exportTahun" name="tahun">
                <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                <option value="<?= $y ?>" <?= $stats['tahun'] == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
              </select>
            </div>

            <!-- Bulan Sampai -->
            <div class="col-6">
              <label class="form-label fw-semibold" style="font-size:var(--fs-base);color:#374151;"><i class="bi bi-calendar-month me-1" style="color:#16a34a;"></i>Realisasi s/d Bulan</label>
              <select class="form-select" id="exportBulan" name="bulan">
                <?php
                $bulanList = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                              7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
                foreach ($bulanList as $num => $nama):
                ?>
                <option value="<?= $num ?>" <?= $num == (int)date('n') ? 'selected' : '' ?>><?= $nama ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Info box -->
            <div class="col-12">
              <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:0.75rem 1rem;font-size:var(--fs-sm);color:#166534;">
                <i class="bi bi-info-circle-fill me-1"></i>
                File Excel akan berisi data realisasi <strong>s/d bulan yang dipilih</strong> untuk tahun yang dipilih, dengan struktur hierarki Seksi &rarr; Program &rarr; Kegiatan &rarr; Sub Kegiatan &rarr; Rekening.
              </div>
            </div>
          </div>
        </form>
      </div>

      <!-- Footer -->
      <div class="modal-footer border-0 pt-0" style="padding:0 1.5rem 1.5rem;">
        <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal" style="font-size:var(--fs-base);">Batal</button>
        <button type="button" class="btn fw-semibold rounded-3" id="btnDownloadExcel"
          style="background:var(--status-success);color:#fff;font-size:var(--fs-base);padding:0.5rem 1.5rem;">
          <i class="bi bi-download me-1"></i> Download Excel
        </button>
      </div>

    </div>
  </div>
</div>

<script>
document.getElementById('btnDownloadExcel').addEventListener('click', function() {
    var tahun = document.getElementById('exportTahun').value;
    var bulan = document.getElementById('exportBulan').value;
    var url   = '<?= base_url('export/laporan') ?>?tahun=' + tahun + '&bulan=' + bulan;
    window.location.href = url;
    // Close modal after short delay
    setTimeout(function() {
        var modal = bootstrap.Modal.getInstance(document.getElementById('modalExportExcel'));
        if (modal) modal.hide();
    }, 500);
});
</script>



<script>
document.addEventListener('DOMContentLoaded', function () {
    const absorptionTable = document.querySelector('.monthly-absorption-table');
    if (!absorptionTable) return;

    function setAbsorptionGroupState(row, expanded) {
        const key = row.getAttribute('data-absorption-toggle');
        if (!key) return;

        row.classList.toggle('is-expanded', expanded);
        row.classList.toggle('is-collapsed', !expanded);
        row.setAttribute('aria-expanded', expanded ? 'true' : 'false');

        const button = row.querySelector('[data-absorption-toggle-button]');
        if (button) {
            button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            button.setAttribute('aria-label', expanded ? 'Tutup rincian rekening' : 'Buka rincian rekening');
        }

        absorptionTable.querySelectorAll('[data-absorption-child="' + key + '"]').forEach(function (childRow) {
            childRow.hidden = !expanded;
        });
    }

    absorptionTable.querySelectorAll('[data-absorption-toggle]').forEach(function (row) {
        row.addEventListener('click', function (event) {
            if (event.target.closest('a')) return;
            setAbsorptionGroupState(row, row.getAttribute('aria-expanded') !== 'true');
        });

        row.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                setAbsorptionGroupState(row, row.getAttribute('aria-expanded') !== 'true');
            }
        });
    });
});
</script>

<footer style="text-align:center;padding:1.5rem 1rem;color:var(--gray-400);font-size:var(--fs-sm);border-top:1px solid var(--gray-100);">
    <p style="margin:0;">&copy; <?= date('Y') ?> Sistem Informasi Monitoring Anggaran Cabang Dinas Kehutanan Wilayah Bojonegoro</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
function resetFilters() { window.location.href = '<?= base_url() ?>?tahun=<?= $stats['tahun'] ?>'; }

// Helper: Export HTML Table to Excel using SheetJS
function exportTableToExcel(tableId, filename) {
    var tbl = document.getElementById(tableId);
    if (!tbl) {
        alert('Tabel data tidak ditemukan.');
        return;
    }
    if (typeof XLSX !== 'undefined') {
        var wb = XLSX.utils.table_to_book(tbl, { sheet: "Data" });
        XLSX.writeFile(wb, filename || 'export.xlsx');
    } else {
        alert('Library Excel sedang dimuat, silakan coba lagi.');
    }
}

// Helper: Export Breakdown Cards to Excel
function exportBreakdownToExcel(filename) {
    var data = [
        ["Nama Unit / Seksi", "Pagu (Rp)", "Realisasi (Rp)", "Sisa (Rp)", "% Capaian"]
    ];
    <?php if (!empty($breakdownData)): ?>
    <?php foreach($breakdownData as $name => $data): ?>
    data.push([
        <?= json_encode($name) ?>,
        <?= (float)$data['pagu'] ?>,
        <?= (float)$data['realisasi'] ?>,
        <?= (float)($data['pagu'] - $data['realisasi']) ?>,
        <?= $data['pagu'] > 0 ? round(($data['realisasi'] / $data['pagu']) * 100, 2) : 0 ?>
    ]);
    <?php endforeach; ?>
    <?php endif; ?>
    if (typeof XLSX !== 'undefined') {
        var ws = XLSX.utils.aoa_to_sheet(data);
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Breakdown");
        XLSX.writeFile(wb, filename || 'breakdown-seksi.xlsx');
    }
}

// Helper: Export Deviasi RAK Tree to Excel
function exportDeviasiToExcel(filename) {
    var data = [
        ["Sub Kegiatan", "Rekening", "Kode Rekening", "Bulan", "RAK Target (Rp)", "Realisasi (Rp)", "Selisih (Rp)", "Status", "Tingkat Risiko", "Catatan"]
    ];
    var tahun = "<?= $stats['tahun'] ?>";
    <?php if (!empty($deviationDetails)): ?>
    <?php foreach ($deviationDetails as $skid => $sk): ?>
        var note = localStorage.getItem("cdk_deviasi_note_" + tahun + "_<?= $skid ?>") || "";
        <?php foreach ($sk['rekening'] as $rek): ?>
            <?php foreach ($rek['deviations'] as $dev): ?>
            data.push([
                <?= json_encode($sk['nama']) ?>,
                <?= json_encode($rek['nama']) ?>,
                <?= json_encode($rek['kode']) ?>,
                <?= json_encode($bulanNamesLongDev[$dev['bulan']] ?? $dev['bulan']) ?>,
                <?= (float)$dev['rak'] ?>,
                <?= (float)$dev['realisasi'] ?>,
                <?= (float)$dev['selisih'] ?>,
                <?= json_encode($dev['type'] === 'over' ? 'Over RAK' : 'Under RAK') ?>,
                <?= json_encode($skRiskLabel ?? 'Sedang') ?>,
                note
            ]);
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endforeach; ?>
    <?php endif; ?>
    if (typeof XLSX !== 'undefined') {
        var ws = XLSX.utils.aoa_to_sheet(data);
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Deviasi_RAK");
        XLSX.writeFile(wb, filename || 'deviasi-rak.xlsx');
    }
}

// Helper: Save & Load Deviasi Notes
function saveDeviasiNote(input) {
    var skId = input.dataset.skId;
    var val = input.value.trim();
    var tahun = "<?= $stats['tahun'] ?>";
    var key = "cdk_deviasi_note_" + tahun + "_" + skId;
    if (val) {
        localStorage.setItem(key, val);
    } else {
        localStorage.removeItem(key);
    }
}

function loadDeviasiNotes() {
    var tahun = "<?= $stats['tahun'] ?>";
    document.querySelectorAll('.deviasi-catatan-input').forEach(function(input) {
        var skId = input.dataset.skId;
        var key = "cdk_deviasi_note_" + tahun + "_" + skId;
        var saved = localStorage.getItem(key);
        if (saved) {
            input.value = saved;
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Load notes
    loadDeviasiNotes();

    // Tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

    // ================================================================
    // Chart global defaults — font lebih besar & kontras
    // ================================================================
    Chart.defaults.font.family = "'Inter', 'Segoe UI', sans-serif";
    Chart.defaults.font.size   = 12;
    Chart.defaults.color       = '#475569';

    // ================================================================
    // Monthly Chart (Line + Area — interactive)
    // ================================================================
    const mData = {
        rak:       <?= json_encode(array_values($monthlyData['rak'])) ?>,
        realisasi: <?= json_encode(array_values($monthlyData['realisasi'])) ?>,
        labels:    <?= json_encode(array_values($bulanNames)) ?>
    };
    const alertM = <?= json_encode(array_column($monthlyData['alerts']??[], 'bulan')) ?>;
    const alertTypes = {};
    <?php if (!empty($monthlyData['alerts'])): ?>
    <?php foreach($monthlyData['alerts'] as $a): ?>
    alertTypes[<?= $a['bulan'] ?>] = '<?= $a['type'] ?>';
    <?php endforeach; ?>
    <?php endif; ?>

    // Bulan berjalan (1–12). Future months = index >= bulanBerjalan
    const bulanBerjalan = <?= $bulanBerjalanDetail ?? 12 ?>;
    const isTahunIni = <?= ($stats['tahun'] == (int)date('Y')) ? 'true' : 'false' ?>;

    // Split RAK dataset: actual (s/d bulan berjalan) vs proyeksi (future)
    const rakAktual    = mData.rak.map((v, i) => (i < bulanBerjalan || !isTahunIni) ? v : null);
    const rakProyeksi  = mData.rak.map((v, i) => (i >= bulanBerjalan - 1 && isTahunIni) ? v : null);

    // Determine point colors for Realisasi based on alert status & anomaly
    const realisasiPointBg = mData.realisasi.map((v, i) => {
        const bulan = i + 1;
        const rak = mData.rak[i] || 0;
        const pct = rak > 0 ? (v / rak) * 100 : 100;
        if (pct < 20 && rak > 0 && (i < bulanBerjalan || !isTahunIni)) return '#dc2626'; // Anomali ekstrem
        if (alertTypes[bulan] === 'over') return '#2563eb';
        if (alertTypes[bulan] === 'under') return '#dc2626';
        return '#059669';
    });
    const realisasiPointRadius = mData.realisasi.map((v, i) => {
        const rak = mData.rak[i] || 0;
        const pct = rak > 0 ? (v / rak) * 100 : 100;
        if (pct < 20 && rak > 0 && (i < bulanBerjalan || !isTahunIni)) return 9; // Extreme anomaly
        return alertM.includes(i + 1) ? 7 : 4;
    });
    const realisasiPointBorder = mData.realisasi.map((v, i) => {
        const bulan = i + 1;
        const rak = mData.rak[i] || 0;
        const pct = rak > 0 ? (v / rak) * 100 : 100;
        if (pct < 20 && rak > 0 && (i < bulanBerjalan || !isTahunIni)) return '#fecaca';
        if (alertTypes[bulan] === 'over') return '#bfdbfe';
        if (alertTypes[bulan] === 'under') return '#fecaca';
        return '#bbf7d0';
    });

    // Format rupiah helper
    function fmtRp(v) {
        if (v >= 1e9) return 'Rp ' + (v/1e9).toFixed(1) + ' M';
        if (v >= 1e6) return 'Rp ' + (v/1e6).toFixed(0) + ' jt';
        if (v >= 1e3) return 'Rp ' + (v/1e3).toFixed(0) + ' rb';
        return 'Rp ' + v;
    }

    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');

    // Gradient fill for realisasi area
    const areaGradient = monthlyCtx.createLinearGradient(0, 0, 0, 320);
    areaGradient.addColorStop(0, 'rgba(5,150,105,0.25)');
    areaGradient.addColorStop(1, 'rgba(5,150,105,0.02)');

    const monthlyChartInstance = new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: mData.labels,
            datasets: [{
                label: 'RAK (Target)',
                data: rakAktual,
                borderColor: '#94a3b8',
                borderWidth: 2,
                borderDash: [6, 4],
                pointBackgroundColor: '#94a3b8',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 6,
                fill: false,
                tension: 0.3,
                order: 2,
                spanGaps: false,
            },{
                label: 'RAK (Proyeksi)',
                data: rakProyeksi,
                borderColor: 'rgba(148,163,184,0.35)',
                borderWidth: 1.5,
                borderDash: [3, 6],
                pointBackgroundColor: 'rgba(148,163,184,0.35)',
                pointBorderColor: '#fff',
                pointBorderWidth: 1,
                pointRadius: 2,
                pointHoverRadius: 4,
                fill: false,
                tension: 0.3,
                order: 3,
                spanGaps: false,
            },{
                label: 'Realisasi',
                data: mData.realisasi,
                borderColor: '#059669',
                borderWidth: 2.5,
                pointBackgroundColor: realisasiPointBg,
                pointBorderColor: realisasiPointBorder,
                pointBorderWidth: 2,
                pointRadius: realisasiPointRadius,
                pointHoverRadius: 8,
                fill: true,
                backgroundColor: areaGradient,
                tension: 0.3,
                order: 1,
                spanGaps: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 20,
                        font: { size: 12, weight: '600' },
                        color: '#1e293b',
                    }
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleFont: { size: 13, weight: '700' },
                    bodyFont: { size: 12 },
                    titleColor: '#f1f5f9',
                    bodyColor: '#cbd5e1',
                    padding: 14,
                    cornerRadius: 10,
                    boxPadding: 5,
                    callbacks: {
                        title: function(items) {
                            return items[0].label;
                        },
                        label: function(ctx) {
                            return '  ' + ctx.dataset.label + ': Rp ' + ctx.parsed.y.toLocaleString('id-ID');
                        },
                        afterBody: function(items) {
                            const idx = items[0].dataIndex;
                            const bulan = idx + 1;
                            // Jika bulan future (belum berjalan), jangan hitung deviasi
                            if (isTahunIni && bulan > bulanBerjalan) {
                                return ['', '  📅 Belum Berjalan (Proyeksi RAK)'];
                            }
                            const rak = mData.rak[idx] || 0;
                            const real = mData.realisasi[idx] || 0;
                            const selisih = real - rak;
                            const pct = rak > 0 ? ((real / rak) * 100).toFixed(2) : '0.00';
                            const sign = selisih >= 0 ? '+' : '';
                            const lines = [
                                '',
                                '  RAK Target : Rp ' + Number(rak).toLocaleString('id-ID'),
                                '  Realisasi  : Rp ' + Number(real).toLocaleString('id-ID'),
                                '  Selisih    : ' + sign + 'Rp ' + Math.abs(selisih).toLocaleString('id-ID'),
                                '  Capaian    : ' + pct + '%'
                            ];
                            if (rak > 0 && parseFloat(pct) < 20) {
                                lines.push('  🚨 ⚠️ Realisasi jauh di bawah target RAK');
                            } else if (alertTypes[bulan] === 'over') {
                                lines.push('  ℹ️ Over RAK');
                            } else if (alertTypes[bulan] === 'under') {
                                lines.push('  ⚠️ Under RAK');
                            }
                            return lines;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(71,85,105,0.09)', drawBorder: false },
                    border: { display: false },
                    ticks: {
                        font: { size: 11, weight: '500' },
                        color: '#475569',
                        padding: 8,
                        maxTicksLimit: 6,
                        callback: function(v) {
                            if (v >= 1e9) return 'Rp ' + (v/1e9).toFixed(1) + ' M';
                            if (v >= 1e6) return 'Rp ' + (v/1e6).toFixed(0) + ' jt';
                            if (v >= 1e3) return 'Rp ' + (v/1e3).toFixed(0) + ' rb';
                            return 'Rp ' + v;
                        }
                    }
                },
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        font: { size: 12, weight: '600' },
                        color: '#334155',
                        padding: 6,
                    }
                }
            },
            layout: {
                padding: { top: 4, bottom: 0, left: 0, right: 8 }
            }
        }
    });

    // Toggle buttons for semester zoom
    document.querySelectorAll('#monthlyChartToggle .monthly-toggle-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('#monthlyChartToggle .monthly-toggle-btn').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            const range = this.dataset.range;
            let labels, rak, real, ptBg, ptRadius, ptBorder;
            if (range === 'h1') {
                labels = mData.labels.slice(0, 6);
                rak = mData.rak.slice(0, 6);
                real = mData.realisasi.slice(0, 6);
                ptBg = realisasiPointBg.slice(0, 6);
                ptRadius = realisasiPointRadius.slice(0, 6);
                ptBorder = realisasiPointBorder.slice(0, 6);
            } else if (range === 'h2') {
                labels = mData.labels.slice(6, 12);
                rak = mData.rak.slice(6, 12);
                real = mData.realisasi.slice(6, 12);
                ptBg = realisasiPointBg.slice(6, 12);
                ptRadius = realisasiPointRadius.slice(6, 12);
                ptBorder = realisasiPointBorder.slice(6, 12);
            } else {
                labels = mData.labels;
                rak = mData.rak;
                real = mData.realisasi;
                ptBg = realisasiPointBg;
                ptRadius = realisasiPointRadius;
                ptBorder = realisasiPointBorder;
            }
            monthlyChartInstance.data.labels = labels;
            monthlyChartInstance.data.datasets[0].data = rak;
            monthlyChartInstance.data.datasets[1].data = real;
            monthlyChartInstance.data.datasets[1].pointBackgroundColor = ptBg;
            monthlyChartInstance.data.datasets[1].pointRadius = ptRadius;
            monthlyChartInstance.data.datasets[1].pointBorderColor = ptBorder;
            monthlyChartInstance.update();
        });
    });

    // ================================================================
    // Breakdown Doughnut
    // ================================================================
    const bdData    = <?= json_encode($breakdownData) ?>;
    // Donut chart: distribusi REALISASI per unit (outer ring) + Sisa (inner ring)
    const bdEntries = Object.entries(bdData);
    const bdLabels  = bdEntries.map(([l]) => l.length > 22 ? l.substring(0,22)+'…' : l);
    const bdReal    = bdEntries.map(([,i]) => i.realisasi);
    const bdPagu    = bdEntries.map(([,i]) => i.pagu);
    const bdSisa    = bdEntries.map(([,i]) => Math.max(0, i.pagu - i.realisasi));


    const dColorsReal = [
        'rgba(67,56,202,0.90)','rgba(2,132,199,0.90)','rgba(5,150,105,0.90)',
        'rgba(217,119,6,0.90)','rgba(220,38,38,0.90)','rgba(147,51,234,0.90)',
        'rgba(219,39,119,0.90)','rgba(20,184,166,0.90)','rgba(234,88,12,0.90)',
        'rgba(71,85,105,0.90)'
    ];
    const dColorsSisa = dColorsReal.map(c => c.replace('0.90','0.22'));

    new Chart(document.getElementById('breakdownChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: bdLabels,
            datasets: [{
                // Outer ring: Realisasi per unit
                label: 'Realisasi',
                data: bdReal,
                backgroundColor: dColorsReal,
                borderColor: '#fff',
                borderWidth: 2,
                hoverBorderWidth: 0,
                hoverOffset: 8,
                weight: 2,
            },{
                // Inner ring: Sisa per unit (faded)
                label: 'Sisa',
                data: bdSisa,
                backgroundColor: dColorsSisa,
                borderColor: '#fff',
                borderWidth: 1,
                hoverBorderWidth: 0,
                hoverOffset: 4,
                weight: 1,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '52%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 10,
                        padding: 8,
                        font: { size: 10, weight: '600' },
                        color: '#334155',
                        usePointStyle: true,
                        pointStyle: 'circle',
                        // Hanya tampilkan legend dari dataset pertama (outer ring)
                        filter: (item) => item.datasetIndex === 0,
                    }
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleFont: { size: 12, weight: '700' },
                    bodyFont: { size: 11 },
                    titleColor: '#f1f5f9',
                    bodyColor: '#cbd5e1',
                    padding: 12,
                    cornerRadius: 10,
                    callbacks: {
                        label: ctx => {
                            const dsLabel = ctx.datasetIndex === 0 ? 'Realisasi' : 'Sisa';
                            const total = bdPagu[ctx.dataIndex] || 1;
                            const pct = ((ctx.parsed / total) * 100).toFixed(1);
                            return ` ${dsLabel}: Rp ${ctx.parsed.toLocaleString('id-ID')}  (${pct}% dari pagu)`;
                        }
                    }
                }
            }
        }
    });
});


</script>

<!-- Sticky Section Nav: Styles -->
<style>
.sticky-sec-nav {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1040;
    background: #fff;
    border-bottom: 1px solid #e5e7eb;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
    transform: translateY(-100%);
    opacity: 0;
    transition: transform .25s ease, opacity .25s ease;
    pointer-events: none;
}
.sticky-sec-nav.visible {
    transform: translateY(0);
    opacity: 1;
    pointer-events: auto;
}
.sticky-sec-nav__inner {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.5rem 1.5rem;
    overflow-x: auto;
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.sticky-sec-nav__inner::-webkit-scrollbar {
    display: none;
}
.sticky-sec-nav__link {
    white-space: nowrap;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    text-decoration: none;
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    transition: color .2s, background .2s;
    border-bottom: 2px solid transparent;
}
.sticky-sec-nav__link:hover {
    color: #1e293b;
    background: #f1f5f9;
}
.sticky-sec-nav__link.active {
    color: #4338ca;
    border-bottom-color: #4338ca;
    background: #eef2ff;
}
.sticky-sec-nav__divider {
    width: 1px;
    height: 24px;
    background: #cbd5e1;
    align-self: center;
    margin: 0 0.5rem;
    flex-shrink: 0;
}
/* Monthly Chart Toggle Buttons */
.monthly-toggle-btn {
    font-size:var(--fs-sm);
    font-weight: 600;
    padding: 0.3rem 0.85rem;
    border-radius: var(--radius-full, 999px);
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #64748b;
    cursor: pointer;
    transition: all .2s;
}
.monthly-toggle-btn:hover {
    background: #f1f5f9;
    color: #334155;
    border-color: #cbd5e1;
}
.monthly-toggle-btn.active {
    background: #4338ca;
    color: #fff;
    border-color: #4338ca;
    box-shadow: 0 2px 6px rgba(67,56,202,.25);
}
/* Export per section button */
.btn-export-section {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size:var(--fs-xs);
    font-weight: 600;
    padding: 0.3rem 0.7rem;
    border-radius: 6px;
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
    text-decoration: none;
    transition: all .2s;
    white-space: nowrap;
}
.btn-export-section:hover {
    background: #059669;
    color: #fff;
    border-color: #059669;
}
.monthly-absorption-scroll-hint {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 1.5rem;
    background: linear-gradient(90deg, #eff6ff, #f8fafc);
    border-bottom: 1px solid #dbeafe;
    color: #1e40af;
    font-size:var(--fs-sm);
    font-weight: 700;
}
.monthly-absorption-scroll-hint i {
    margin-right: 0.25rem;
}
.monthly-absorption-wrap {
    max-height: 640px;
    overflow: auto;
    position: relative;
    scrollbar-color: #94a3b8 #e2e8f0;
    scrollbar-width: thin;
}
.monthly-absorption-wrap::-webkit-scrollbar {
    width: 10px;
    height: 12px;
}
.monthly-absorption-wrap::-webkit-scrollbar-track {
    background: #e2e8f0;
    border-radius: 999px;
}
.monthly-absorption-wrap::-webkit-scrollbar-thumb {
    background: #94a3b8;
    border-radius: 999px;
    border: 2px solid #e2e8f0;
}
.monthly-absorption-table {
    --ma-name-width: 360px;
    --ma-pagu-width: 150px;
    --ma-month-width: 118px;
    --ma-total-width: 160px;
    --ma-pct-width: 84px;
    min-width: 2170px;
    border-collapse: separate;
    border-spacing: 0;
    font-variant-numeric: tabular-nums;
}
.monthly-absorption-table th,
.monthly-absorption-table td {
    padding: 0.72rem 0.9rem;
    white-space: nowrap;
}
.monthly-absorption-table thead th {
    position: sticky;
    top: 0;
    z-index: 12;
    background: #f8fafc;
    color: #475569;
    box-shadow: inset 0 -1px 0 #cbd5e1;
}
.monthly-absorption-table thead .monthly-absorption-name {
    background: #e2e8f0;
    color: #0f172a;
}
.monthly-absorption-table thead .monthly-absorption-pagu {
    background: #ecfeff;
    color: #155e75;
}
.monthly-absorption-table thead .monthly-absorption-month {
    background: #eef2ff;
    color: #3730a3;
}
.monthly-absorption-table thead .monthly-absorption-total,
.monthly-absorption-table thead .monthly-absorption-percent {
    background: #ecfdf5;
    color: #047857;
}
.monthly-absorption-table .monthly-absorption-name {
    width: var(--ma-name-width);
    min-width: var(--ma-name-width);
    max-width: var(--ma-name-width);
    white-space: normal;
}
.monthly-absorption-table .monthly-absorption-pagu {
    width: var(--ma-pagu-width);
    min-width: var(--ma-pagu-width);
}
.monthly-absorption-table .monthly-absorption-month {
    width: var(--ma-month-width);
    min-width: var(--ma-month-width);
}
.monthly-absorption-table .monthly-absorption-total {
    width: var(--ma-total-width);
    min-width: var(--ma-total-width);
    font-weight: 800;
    color: #0f172a;
}
.monthly-absorption-table .monthly-absorption-percent {
    width: var(--ma-pct-width);
    min-width: var(--ma-pct-width);
}
/* ── STICKY COLUMNS BASE ─────────────────────────────────────
   PENTING: background harus warna solid eksplisit — JANGAN inherit
   karena tr/tbody computed background = transparent di browser.
   z-index tiers:
   thead sticky+sticky   = 25  (corner: top & left/right)
   thead sticky (top)    = 15
   tfoot sticky+sticky   = 22  (corner: bottom & left/right)
   tfoot sticky (bottom) = 13
   tbody sticky          = 5
   ─────────────────────────────────────────────────────────── */
.monthly-sticky-name,
.monthly-sticky-pagu,
.monthly-sticky-total,
.monthly-sticky-percent {
    position: sticky;
    z-index: 5;
    background: #fff; /* fallback — akan ditimpa oleh aturan spesifik di bawah */
}
.monthly-sticky-name {
    left: 0;
    /* Shadow kanan sebagai separator visual antara area sticky dan area scroll */
    box-shadow: 3px 0 6px -2px rgba(0,0,0,0.12), 1px 0 0 #cbd5e1;
}
.monthly-sticky-pagu {
    left: var(--ma-name-width);
    /* Shadow kanan yang lebih tipis */
    box-shadow: 3px 0 6px -3px rgba(0,0,0,0.08), 1px 0 0 #e2e8f0;
}
.monthly-sticky-total {
    right: var(--ma-pct-width);
    box-shadow: -3px 0 6px -2px rgba(0,0,0,0.10), -1px 0 0 #cbd5e1;
}
.monthly-sticky-percent {
    right: 0;
    box-shadow: -1px 0 0 #e2e8f0;
}
.monthly-absorption-title {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}
.monthly-absorption-toggle-btn {
    width: 28px;
    height: 28px;
    border: 0;
    background: transparent;
    color: #0f172a;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    flex: 0 0 28px;
    cursor: pointer;
}
.monthly-absorption-toggle-btn i {
    font-size:var(--fs-md);
    line-height: 1;
    transition: transform 160ms ease;
}
.monthly-absorption-group.is-expanded .monthly-absorption-toggle-btn i {
    transform: rotate(90deg);
}
.monthly-absorption-sk-name {
    color: #111827;
    font-size:var(--fs-md);
    font-weight: 800;
    line-height: 1.35;
}
.monthly-absorption-sk-meta {
    color: #64748b;
    font-size:var(--fs-sm);
    font-weight: 700;
    margin-top: 0.2rem;
}
/* thead: semua sel header punya z-index sticky bawaan = 12 (dari .monthly-absorption-table thead th) */
/* thead corner cells (sticky kiri+kanan) perlu z-index lebih tinggi */
.monthly-absorption-table thead .monthly-sticky-name,
.monthly-absorption-table thead .monthly-sticky-pagu,
.monthly-absorption-table thead .monthly-sticky-total,
.monthly-absorption-table thead .monthly-sticky-percent {
    z-index: 25;
}
/* tfoot: sel bulan (bukan sticky) juga harus solid agar tidak "tembus" */
.monthly-absorption-table tfoot .monthly-absorption-month,
.monthly-absorption-table tfoot .monthly-absorption-total,
.monthly-absorption-table tfoot .monthly-absorption-percent {
    background: #f8fafc;
}
/* ── GROUP ROW (Sub Kegiatan header) ──────────────────────── */
.monthly-absorption-group td {
    background: #eef3f8;
    border-top: 1px solid #cbd5e1;
    border-bottom: 1px solid #cbd5e1;
    font-weight: 800;
    padding-top: 1rem;
    padding-bottom: 1rem;
    cursor: pointer;
    transition: background-color 160ms ease;
}
.monthly-absorption-group:hover td {
    background: #e6edf5;
}
/* Group: sticky left */
.monthly-absorption-group .monthly-sticky-name,
.monthly-absorption-group .monthly-sticky-pagu {
    background: #eef3f8 !important;
}
.monthly-absorption-group:hover .monthly-sticky-name,
.monthly-absorption-group:hover .monthly-sticky-pagu {
    background: #e6edf5 !important;
}
/* Group: sticky right (lebih gelap agar beda dari area bulan) */
.monthly-absorption-group .monthly-sticky-total,
.monthly-absorption-group .monthly-sticky-percent,
.monthly-absorption-group:hover .monthly-sticky-total,
.monthly-absorption-group:hover .monthly-sticky-percent {
    background: #dbe4ee !important;
}
/* Group: sel bulan harus solid — sama dengan background baris */
.monthly-absorption-group .monthly-absorption-month {
    background: #eef3f8;
}
.monthly-absorption-group:hover .monthly-absorption-month {
    background: #e6edf5;
}
/* Group: warna angka zero/filled (background dari parent td) */
.monthly-absorption-zero {
    color: #94a3b8 !important;
    font-weight: 600;
}
.monthly-absorption-filled {
    color: #009b72 !important;
    font-weight: 800;
}

/* ── DETAIL ROW (Rekening) ─────────────────────────────────── */
.monthly-absorption-detail td {
    background: #fff;
    padding-top: 0.68rem;
    padding-bottom: 0.68rem;
    border-bottom: 1px solid #f1f5f9;
}
/* Detail: sticky left */
.monthly-absorption-detail .monthly-sticky-name,
.monthly-absorption-detail .monthly-sticky-pagu {
    background: #fff !important;
}
/* Detail: sticky right */
.monthly-absorption-detail .monthly-sticky-total,
.monthly-absorption-detail .monthly-sticky-percent {
    background: #f8fafc !important;
}
/* Detail: sel bulan solid background */
.monthly-absorption-detail .monthly-absorption-month {
    background: #fff;
}
.monthly-absorption-detail .monthly-absorption-name {
    padding-left: 3.6rem !important;
}
.monthly-absorption-detail .monthly-absorption-pagu {
    color: #64748b;
    font-weight: 700;
}
.monthly-absorption-table tfoot td {
    position: sticky;
    bottom: 0;
    z-index: 13;
    background: #f8fafc;
    color: #0f172a;
    font-weight: 800;
    border-top: 2px solid #94a3b8;
    box-shadow: inset 0 1px 0 #cbd5e1;
}
/* tfoot sticky + left/right = tingkat z-index tertinggi di tfoot */
.monthly-absorption-table tfoot .monthly-sticky-name,
.monthly-absorption-table tfoot .monthly-sticky-pagu,
.monthly-absorption-table tfoot .monthly-sticky-total,
.monthly-absorption-table tfoot .monthly-sticky-percent {
    z-index: 22;
    background: #f8fafc !important;
}
.monthly-absorption-pct {
    display: inline-flex;
    min-width: 58px;
    justify-content: center;
    font-size:var(--fs-xs);
    font-weight: 800;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    background: #f1f5f9;
    color: #64748b;
    border: 1px solid #e2e8f0;
}
.monthly-absorption-pct.is-mid {
    background: #fffbeb;
    color: #b45309;
    border-color: #fde68a;
}
.monthly-absorption-pct.is-high {
    background: #ecfdf5;
    color: #047857;
    border-color: #bbf7d0;
}
.monthly-absorption-pct.is-over {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fecaca;
}
@media (max-width: 767.98px) {
    .sticky-sec-nav__inner {
        padding: 0.4rem 1rem;
        gap: 0.15rem;
    }
    .sticky-sec-nav__link {
        font-size: 12px;
        padding: 0.3rem 0.6rem;
    }
    .sticky-sec-nav__divider {
        margin: 0 0.25rem;
        height: 18px;
    }
}

/* Mobile collapse for monthly absorption table */
@media (max-width: 767.98px) {
    .monthly-absorption-table:not(.is-expanded-months) {
        min-width: 0 !important;
        width: 100% !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-absorption-month {
        display: none !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-absorption-name {
        width: auto !important;
        min-width: 140px !important;
        max-width: 180px !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-sticky-pagu {
        left: auto !important;
        position: static !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-sticky-total {
        right: auto !important;
        position: static !important;
    }
    .monthly-absorption-table:not(.is-expanded-months) .monthly-sticky-percent {
        right: auto !important;
        position: static !important;
    }
}
</style>

<!-- Sticky Section Nav & Tab Navigation: Behavior -->
<script>
(function() {
    const nav = document.getElementById('stickySecNav');
    if (!nav) return;

    const SCROLL_THRESHOLD = 80;
    const links = nav.querySelectorAll('.sticky-sec-nav__link');
    
    // Mapping between section anchor IDs and tab button IDs
    const tabTargetMap = {
        'section-serapan-rekening': 'tab-serapan-rekening-btn',
        'section-sisa-semester': 'tab-sisa-semester-btn',
        'section-struktur': 'tab-struktur-btn',
        'section-deviasi': 'tab-deviasi-btn'
    };

    const tabSectionMap = {
        '#tab-serapan-rekening': 'section-serapan-rekening',
        '#tab-sisa-semester': 'section-sisa-semester',
        '#tab-struktur': 'section-struktur',
        '#tab-deviasi': 'section-deviasi'
    };

    // Show/hide nav based on scroll position
    let ticking = false;
    function onScroll() {
        if (!ticking) {
            window.requestAnimationFrame(function() {
                if (window.scrollY > SCROLL_THRESHOLD) {
                    nav.classList.add('visible');
                } else {
                    nav.classList.remove('visible');
                }
                ticking = false;
            });
            ticking = true;
        }
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    // Helper: switch to tab and scroll to tab container
    function activateTabAndScroll(targetId) {
        if (tabTargetMap[targetId]) {
            var tabBtn = document.getElementById(tabTargetMap[targetId]);
            if (tabBtn && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                var tabInstance = bootstrap.Tab.getOrCreateInstance(tabBtn);
                tabInstance.show();
            }
        }
        var target = document.getElementById(targetId) || document.getElementById('section-detail-tabs');
        if (target) {
            var navHeight = nav.offsetHeight || 48;
            var top = target.getBoundingClientRect().top + window.pageYOffset - navHeight - 12;
            window.scrollTo({ top: top, behavior: 'smooth' });
        }
    }

    // Smooth scroll & tab trigger on sticky nav link click
    links.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var targetId = this.getAttribute('href').substring(1);
            activateTabAndScroll(targetId);
        });
    });

    // Handle any in-page link pointing to tabbed sections (e.g. from breakdown section)
    document.querySelectorAll('a[href="#section-struktur"], a[href="#section-deviasi"], a[href="#section-serapan-rekening"], a[href="#section-sisa-semester"]').forEach(function(a) {
        if (a.classList.contains('sticky-sec-nav__link')) return;
        a.addEventListener('click', function(e) {
            e.preventDefault();
            var targetId = this.getAttribute('href').substring(1);
            activateTabAndScroll(targetId);
        });
    });

    // Sync sticky nav active state when user manually switches tab via tab pills
    document.querySelectorAll('#budgetDetailTabs button[data-bs-toggle="tab"]').forEach(function(btn) {
        btn.addEventListener('shown.bs.tab', function() {
            var targetPaneId = this.getAttribute('data-bs-target');
            var secId = tabSectionMap[targetPaneId];
            if (secId) {
                var tabSection = document.getElementById('section-detail-tabs');
                if (tabSection) {
                    var rect = tabSection.getBoundingClientRect();
                    if (rect.top < window.innerHeight && rect.bottom > 0) {
                        links.forEach(function(l) { l.classList.remove('active'); });
                        var activeLink = nav.querySelector('a[href="#' + secId + '"]');
                        if (activeLink) {
                            activeLink.classList.add('active');
                            activeLink.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
                        }
                    }
                }
            }
        });
    });

    // IntersectionObserver to highlight active link
    var observerOptions = {
        root: null,
        rootMargin: '-80px 0px -60% 0px',
        threshold: 0
    };

    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                var id = entry.target.id;
                if (id === 'section-detail-tabs') {
                    var activeTabBtn = document.querySelector('#budgetDetailTabs .nav-link.active');
                    if (activeTabBtn) {
                        var targetPaneId = activeTabBtn.getAttribute('data-bs-target');
                        var secId = tabSectionMap[targetPaneId];
                        if (secId) {
                            links.forEach(function(l) { l.classList.remove('active'); });
                            var activeLink = nav.querySelector('a[href="#' + secId + '"]');
                            if (activeLink) {
                                activeLink.classList.add('active');
                                activeLink.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
                            }
                        }
                    }
                } else {
                    links.forEach(function(l) { l.classList.remove('active'); });
                    var activeLink = nav.querySelector('a[href="#' + id + '"]');
                    if (activeLink) {
                        activeLink.classList.add('active');
                        activeLink.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
                    }
                }
            }
        });
    }, observerOptions);

    ['section-ringkasan', 'section-grafik', 'section-detail-bulan', 'section-detail-tabs', 'section-breakdown'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) observer.observe(el);
    });
})();

// =========================================================================
// Tree Accessibility & Collapsible Helpers
// =========================================================================
function toggleTreeNode(el) {
    if (!el || !el.parentElement) return;
    var parent = el.parentElement;
    parent.classList.toggle('tree-collapsed');
    var isExpanded = !parent.classList.contains('tree-collapsed');
    el.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
}

function toggleDeviasiNode(el) {
    if (!el || !el.parentElement) return;
    var parent = el.parentElement;
    parent.classList.toggle('d-collapsed');
    var isExpanded = !parent.classList.contains('d-collapsed');
    el.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
}

function handleTreeKey(e, el) {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        el.click();
    }
}

// =========================================================================
// Mobile Responsive Toggle for Monthly Absorption Table
// =========================================================================
document.addEventListener('DOMContentLoaded', function() {
    var toggleBtn = document.getElementById('toggleMonthlyDetailMobile');
    var table = document.querySelector('.monthly-absorption-table');
    var toggleText = document.getElementById('toggleMonthlyDetailText');
    if (toggleBtn && table) {
        toggleBtn.addEventListener('click', function() {
            var expanded = table.classList.toggle('is-expanded-months');
            if (toggleText) {
                toggleText.textContent = expanded ? 'Sembunyikan Detail Bulan' : 'Lihat Semua Bulan (12 Bln)';
            }
            toggleBtn.classList.toggle('active', expanded);
        });
    }
});
</script>
