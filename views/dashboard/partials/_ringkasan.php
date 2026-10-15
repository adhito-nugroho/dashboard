    <!-- KPI Cards: urutan = Pagu > Realisasi (capaian utama) > Sisa > Serapan vs RAK (pendukung) -->
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
            <?php $pctColor = $stats['percentage'] > 100 ? 'danger' : ($stats['percentage'] > 80 ? 'warning' : 'success'); ?>
            <div class="card kpi-card kpi-border-success h-100" data-bs-toggle="tooltip" title="Capaian realisasi terhadap total pagu">
                <div class="card-body" style="padding:1.25rem;">
                    <div class="d-flex align-items-start gap-3">
                        <div class="kpi-icon" style="background:#ecfdf5;color:var(--success);"><i class="bi bi-receipt-cutoff"></i></div>
                        <div style="flex:1;min-width:0;">
                            <div class="kpi-label">Realisasi <small style="font-size:0.7em;font-weight:600;color:var(--gray-400);">s/d <?= strtoupper($bulanNames[$bulanBerjalan]) ?></small></div>
                            <div class="kpi-value" style="color:var(--<?= $pctColor ?>);font-size:1.9rem;"><?= number_format($stats['percentage'], 2) ?>%</div>
                            <div class="fw-bold text-dark mt-1" style="font-size:0.95rem;letter-spacing:-0.01em;">Rp <?= number_format($stats['total_realisasi'], 0, ',', '.') ?></div>
                            <div class="mt-2">
                                <div class="progress" style="height:6px;border-radius:3px;background:var(--gray-100);">
                                    <div class="progress-bar bg-<?= $pctColor ?>" style="width:<?= min($stats['percentage'],100) ?>%;border-radius:3px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 animate-fade-in-up delay-3">
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
        <div class="col-xl-3 col-md-6 animate-fade-in-up delay-4">
            <div class="card kpi-card kpi-border-info h-100" data-bs-toggle="tooltip" title="Komparasi serapan kumulatif dengan target RAK s/d bulan berjalan (indikator disiplin rencana, bukan capaian)">
                <div class="card-body" style="padding:1.25rem;">
                    <div class="d-flex align-items-start gap-3">
                        <div class="kpi-icon" style="background:#ecfeff;color:var(--info);"><i class="bi bi-calendar-check"></i></div>
                        <div style="flex:1;min-width:0;">
                            <div class="kpi-label">Serapan vs RAK <small style="font-size:0.7em;font-weight:600;color:var(--gray-400);">s/d <?= strtoupper($bulanNames[$bulanBerjalan]) ?></small></div>
                            <?php
                                $fmtTargetRakKpi = $targetRakBulanBerjalan >= 1e9
                                    ? 'Rp ' . number_format($targetRakBulanBerjalan / 1e9, 2, ',', '.') . ' M'
                                    : ($targetRakBulanBerjalan >= 1e6
                                        ? 'Rp ' . number_format($targetRakBulanBerjalan / 1e6, 2, ',', '.') . ' Jt'
                                        : 'Rp ' . number_format($targetRakBulanBerjalan, 0, ',', '.'));
                            ?>
                            <div class="kpi-value" style="font-size:1.05rem;color:var(--gray-700);"><?= number_format($capaianRakBulanBerjalan, 2) ?>% <span style="font-size:0.72rem;font-weight:600;color:var(--gray-400);" title="Target RAK kumulatif s/d <?= $bulanNames[$bulanBerjalan] ?>: Rp <?= number_format($targetRakBulanBerjalan, 0, ',', '.') ?>">dari target <?= $fmtTargetRakKpi ?></span></div>
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

