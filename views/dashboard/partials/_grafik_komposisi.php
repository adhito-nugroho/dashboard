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
                    <div class="d-flex gap-1 mb-3 align-items-center flex-wrap" id="monthlyChartToggle">
                        <button type="button" class="btn btn-sm monthly-toggle-btn active" data-range="all">Semua Bulan</button>
                        <button type="button" class="btn btn-sm monthly-toggle-btn" data-range="h1">Jan–Jun</button>
                        <button type="button" class="btn btn-sm monthly-toggle-btn" data-range="h2">Jul–Des</button>
                        <button type="button" class="btn btn-sm monthly-toggle-btn ms-auto" id="monthlyLogToggle" aria-pressed="false" title="Ubah skala sumbu Y ke logaritmik agar bulan kecil tetap terlihat saat ada lonjakan besar">
                            <i class="bi bi-bar-chart-line me-1"></i>Mode Skala Logaritmik
                        </button>
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
                        <div id="breakdownCustomLegend" class="breakdown-legend" aria-label="Legenda komposisi realisasi"></div>
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

