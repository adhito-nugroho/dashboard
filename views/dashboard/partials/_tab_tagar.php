
            <!-- Tab: Realisasi per Tagar -->
            <div class="tab-pane fade" id="tab-tagar" role="tabpanel" aria-labelledby="tab-tagar-btn">
                <?php if (empty($tagRecap)): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card rounded-4">
                                <div class="card-body text-center text-muted py-5">
                                    <i class="bi bi-tags" style="font-size:2rem;"></i>
                                    <div class="mt-2">Data tagar belum tersedia untuk filter ini.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php
                    $tagTotalPagu = array_sum(array_map(fn($t) => (float) $t['pagu'], $tagRecap));
                    $tagTotalRealisasi = array_sum(array_map(fn($t) => (float) $t['realisasi'], $tagRecap));
                    $tagTotalSisa = $tagTotalPagu - $tagTotalRealisasi;
                    $tagTotalSerapan = $tagTotalPagu > 0 ? ($tagTotalRealisasi / $tagTotalPagu) * 100 : 0.0;
                    $tagTotalCount = array_sum(array_map(fn($t) => (int) $t['count'], $tagRecap));
                    ?>
                    <div class="row mb-4 g-3 align-items-stretch" id="section-tagar">
                        <div class="col-12 col-xl-5 animate-fade-in-up delay-5">
                            <div class="card rounded-4 h-100">
                                <div class="section-header" style="padding-bottom:.5rem;">
                                    <div>
                                        <h5 class="section-title"><i class="bi bi-tags-fill me-2" style="color:var(--primary);"></i>Pagu vs Realisasi per Tagar</h5>
                                        <p class="section-subtitle">Realisasi terverifikasi tahun <?= (int) $stats['tahun'] ?></p>
                                    </div>
                                </div>
                                <div class="card-body" style="padding:.75rem 1.5rem 1.5rem;">
                                    <div class="chart-wrapper" style="height:320px;">
                                        <canvas id="tagRecapChart" aria-label="Grafik pagu dan realisasi per tagar" role="img"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-xl-7 animate-fade-in-up delay-6">
                            <div class="card rounded-4 h-100 overflow-hidden">
                                <div class="section-header" style="padding-bottom:.75rem;">
                                    <div>
                                        <h5 class="section-title"><i class="bi bi-table me-2" style="color:#0284c7;"></i>Rekap Tagar</h5>
                                        <p class="section-subtitle">Pagu, realisasi, sisa, dan serapan per tagar</p>
                                    </div>
                                    <span style="font-size:var(--fs-xs);font-weight:700;padding:0.3rem 0.875rem;border-radius:var(--radius-full);background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;white-space:nowrap;">
                                        <i class="bi bi-tags me-1"></i><?= (int) count($tagRecap) ?> Tagar
                                    </span>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover table-dashboard align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Tagar</th>
                                                    <th class="text-end">Rekening</th>
                                                    <th class="text-end">Pagu (Rp)</th>
                                                    <th class="text-end">Realisasi (Rp)</th>
                                                    <th class="text-end">Sisa (Rp)</th>
                                                    <th class="text-end">Serapan</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($tagRecap as $t): ?>
                                                    <?php $sisa = (float) $t['sisa']; ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge" style="background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;font-weight:700;">
                                                                <?= htmlspecialchars($t['label']) ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-end"><?= number_format((int) $t['count'], 0, ',', '.') ?></td>
                                                        <td class="text-end">Rp <?= number_format((float) $t['pagu'], 0, ',', '.') ?></td>
                                                        <td class="text-end text-muted">Rp <?= number_format((float) $t['realisasi'], 0, ',', '.') ?></td>
                                                        <td class="text-end fw-bold <?= $sisa < 0 ? 'text-danger' : 'text-success' ?>">Rp <?= number_format($sisa, 0, ',', '.') ?></td>
                                                        <td class="text-end"><?= number_format((float) $t['serapan'], 2, ',', '.') ?>%</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr style="background:#f8fafc;font-weight:800;">
                                                    <td>Total</td>
                                                    <td class="text-end"><?= number_format($tagTotalCount, 0, ',', '.') ?></td>
                                                    <td class="text-end">Rp <?= number_format($tagTotalPagu, 0, ',', '.') ?></td>
                                                    <td class="text-end">Rp <?= number_format($tagTotalRealisasi, 0, ',', '.') ?></td>
                                                    <td class="text-end <?= $tagTotalSisa < 0 ? 'text-danger' : 'text-success' ?>">Rp <?= number_format($tagTotalSisa, 0, ',', '.') ?></td>
                                                    <td class="text-end"><?= number_format($tagTotalSerapan, 2, ',', '.') ?>%</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                    (function () {
                        var tagChart = null;
                        var labels = <?= json_encode(array_map(fn($t) => (string) $t['label'], $tagRecap), JSON_UNESCAPED_UNICODE) ?>;
                        var paguData = <?= json_encode(array_map(fn($t) => (float) $t['pagu'], $tagRecap)) ?>;
                        var realisasiData = <?= json_encode(array_map(fn($t) => (float) $t['realisasi'], $tagRecap)) ?>;

                        function shortRupiah(value) {
                            var n = Number(value) || 0;
                            var abs = Math.abs(n);
                            if (abs >= 1e9) return 'Rp ' + (n / 1e9).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' M';
                            if (abs >= 1e6) return 'Rp ' + (n / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt';
                            if (abs >= 1e3) return 'Rp ' + (n / 1e3).toLocaleString('id-ID') + ' rb';
                            return 'Rp ' + n.toLocaleString('id-ID');
                        }

                        function initTagChart() {
                            var el = document.getElementById('tagRecapChart');
                            if (!el || typeof Chart === 'undefined') return;
                            if (tagChart) { tagChart.resize(); return; }

                            tagChart = new Chart(el.getContext('2d'), {
                                type: 'bar',
                                data: {
                                    labels: labels,
                                    datasets: [
                                        { label: 'Pagu', data: paguData, backgroundColor: '#4338ca', borderRadius: 4 },
                                        { label: 'Realisasi', data: realisasiData, backgroundColor: '#059669', borderRadius: 4 }
                                    ]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { position: 'bottom' },
                                        tooltip: {
                                            callbacks: {
                                                label: function (ctx) {
                                                    return ctx.dataset.label + ': Rp ' + Number(ctx.raw).toLocaleString('id-ID');
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            ticks: { callback: function (v) { return shortRupiah(v); } }
                                        },
                                        x: { ticks: { maxRotation: 45, minRotation: 0 } }
                                    }
                                }
                            });
                        }

                        var tabBtn = document.getElementById('tab-tagar-btn');
                        if (tabBtn) tabBtn.addEventListener('shown.bs.tab', initTagChart);
                        document.addEventListener('DOMContentLoaded', function () {
                            var pane = document.getElementById('tab-tagar');
                            if (pane && pane.classList.contains('active')) initTagChart();
                        });
                    })();
                    </script>
                <?php endif; ?>
            </div>
