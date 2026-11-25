    <?php
        // Bulan yang sudah selesai dinilai (bulan berjalan belum final)
        $bulanPenilaianGrafik = ($stats['tahun'] == (int) date('Y')) ? max(0, $bulanBerjalan - 1) : $bulanBerjalan;

        // Deteksi anomali (<20% RAK) hanya untuk bulan yang sudah selesai
        $anomaliBulan = [];
        for ($m = 1; $m <= $bulanPenilaianGrafik; $m++) {
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

        // Bulan dengan realisasi menonjol (mis. lonjakan Oktober).
        // Hanya dinilai bila cukup banyak bulan berdata, agar bulan tunggal
        // yang memang satu-satunya realisasi tidak salah dilabeli "outlier".
        $grafikOutlier = null;
        $realPerBulan = [];
        for ($m = 1; $m <= $bulanPenilaianGrafik; $m++) {
            $realPerBulan[$m] = (float)($monthlyData['realisasi'][$m] ?? 0);
        }
        $bulanBerdata = array_filter($realPerBulan, fn($v) => $v > 0);
        if (count($bulanBerdata) >= 2) {
            $rataData = array_sum($bulanBerdata) / count($bulanBerdata);
            $maxReal = max($bulanBerdata);
            $bulanMax = array_search($maxReal, $bulanBerdata, true);
            $grafikOutlier = [
                'bulan' => $bulanNames[$bulanMax],
                'realisasi' => $maxReal,
                'rata' => $rataData,
                'kali' => $rataData > 0 ? $maxReal / $rataData : 0,
                'ekstrem' => $rataData > 0 && $maxReal > (2 * $rataData),
            ];
        }
    ?>
    <!-- Charts Row -->
    <div class="row mb-4 g-3 align-items-start">
        <!-- Monthly Chart -->
        <div class="col-lg-8 animate-fade-in-up delay-3" id="section-grafik">
            <div class="card rounded-4">
                <div class="section-header" style="padding-bottom:0.5rem;">
                    <div>
                        <h5 class="section-title"><i class="bi bi-graph-up me-2" style="color:var(--primary);"></i>Grafik Realisasi Bulanan</h5>
                        <p class="section-subtitle">RAK vs realisasi per bulan &mdash; tampilan <strong id="monthlyChartModeLabel">kumulatif (S-curve)</strong></p>
                    </div>
                </div>
                <div class="card-body" style="padding:0.75rem 1.5rem 1.5rem;">
                    <!-- Toggle Mode & Rentang -->
                    <div class="d-flex gap-1 mb-3 align-items-center flex-wrap" id="monthlyChartToggle">
                        <div class="d-inline-flex gap-1" role="group" aria-label="Mode tampilan grafik">
                            <button type="button" class="btn btn-sm monthly-toggle-btn active" data-mode="cumulative" aria-pressed="true">Kumulatif</button>
                            <button type="button" class="btn btn-sm monthly-toggle-btn" data-mode="monthly" aria-pressed="false">Per Bulan</button>
                        </div>
                        <span style="width:1px;height:22px;background:#e2e8f0;margin:0 0.4rem;" aria-hidden="true"></span>
                        <button type="button" class="btn btn-sm monthly-toggle-btn active" data-range="all">Semua Bulan</button>
                        <button type="button" class="btn btn-sm monthly-toggle-btn" data-range="h1">Jan–Jun</button>
                        <button type="button" class="btn btn-sm monthly-toggle-btn" data-range="h2">Jul–Des</button>
                        <button type="button" class="btn btn-sm monthly-toggle-btn ms-auto" id="monthlyLogToggle" aria-pressed="false" title="Ubah skala sumbu Y ke logaritmik agar bulan kecil tetap terlihat saat ada lonjakan besar">
                            <i class="bi bi-bar-chart-line me-1"></i>Skala Log
                        </button>
                    </div>
                    <div class="chart-wrapper monthly-chart-wrapper"><canvas id="monthlyChart"></canvas></div>

                    <?php if ($grafikOutlier): ?>
                    <div class="chart-outlier-note">
                        <i class="bi bi-graph-up-arrow" style="flex-shrink:0;"></i>
                        <span><strong>Realisasi tertinggi: bulan <?= $grafikOutlier['bulan'] ?></strong> (Rp <?= number_format($grafikOutlier['realisasi'], 0, ',', '.') ?>).
                        <?php if ($grafikOutlier['ekstrem']): ?>
                        Nilainya sekitar <?= number_format($grafikOutlier['kali'], 1) ?>× rata-rata bulan berdata (Rp <?= number_format($grafikOutlier['rata'], 0, ',', '.') ?>), sehingga tampak menonjol.
                        <?php endif; ?>
                        Gunakan <strong>Skala Log</strong> bila ingin detail bulan bernilai kecil tetap terbaca.</span>
                    </div>
                    <?php endif; ?>

                    <!-- Legenda penanda: menjelaskan setiap gaya titik/garis di grafik -->
                    <div class="chart-marker-legend" aria-label="Keterangan penanda grafik">
                        <span class="cml-item"><span class="cml-line"></span>RAK aktual (per bulan)</span>
                        <span class="cml-item"><span class="cml-line cml-proj"></span>RAK proyeksi (belum berjalan)</span>
                        <span class="cml-item"><span class="cml-dot cml-green"></span>Realisasi normal</span>
                        <span class="cml-item"><span class="cml-dot cml-blue"></span>Realisasi &gt; RAK (over)</span>
                        <span class="cml-item"><span class="cml-dot cml-red"></span>Realisasi &lt; RAK / anomali</span>
                        <span class="cml-item"><span class="cml-dot cml-indigo"></span>Bulan berjalan (belum dinilai)</span>
                    </div>

                    <?php if (!empty($anomaliBulan)): ?>
                    <?php
                        usort($anomaliBulan, fn($a, $b) => $a['capaian'] <=> $b['capaian']);
                        $anomaliTop = array_slice($anomaliBulan, 0, 3);
                        $anomaliSisa = count($anomaliBulan) - count($anomaliTop);
                    ?>
                    <div class="chart-anomaly-alert mt-2">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size:1.1rem;flex-shrink:0;"></i>
                        <div style="flex:1;">
                            <strong>Perhatian Anomali Capaian:</strong>
                            <?php foreach ($anomaliTop as $ab): ?>
                            <span>Bulan <strong><?= $ab['bulan'] ?></strong> realisasi hanya Rp <?= number_format($ab['realisasi'], 0, ',', '.') ?> (<strong><?= number_format($ab['capaian'], 1) ?>%</strong> dari target RAK Rp <?= number_format($ab['rak'], 0, ',', '.') ?> &mdash; realisasi jauh di bawah target RAK).</span>
                            <?php endforeach; ?>
                            <?php if ($anomaliSisa > 0): ?>
                            <span>dan <strong><?= $anomaliSisa ?> bulan lain</strong> dengan capaian di bawah 20% dari RAK.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($stats['tahun'] == (int) date('Y')): ?>
                    <div style="margin-top:0.6rem;font-size:var(--fs-xs);color:#64748b;">
                        <i class="bi bi-clock-history me-1"></i>Bulan berjalan (<?= $bulanNames[$bulanBerjalan] ?>) belum dinilai Under/Over RAK.
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
                            <span style="font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--gray-400);" title="Realisasi ÷ total pagu tahunan. Beda dengan 'Serapan vs RAK' yang memakai RAK kumulatif sebagai penyebut.">Total Serapan <span style="font-weight:600;text-transform:none;letter-spacing:0;">(thd Pagu)</span></span>
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

