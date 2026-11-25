    <!-- Alerts Section (satu alert utama, nada lembut) -->
    <?php if (!empty($monthlyData['alerts']) || $serapan > 90): ?>
    <div class="mb-4 animate-fade-in-up delay-5" id="section-perhatian">
        <div class="card rounded-4 alert-soft-card">
            <div class="card-body" style="padding:1.1rem 1.35rem;">
                <div class="d-flex align-items-start gap-3">
                    <div class="alert-soft-icon"><i class="bi bi-exclamation-diamond"></i></div>
                    <div style="flex:1;min-width:0;">
                        <h6 style="font-weight:700;color:var(--gray-800);margin-bottom:0.5rem;font-size:var(--fs-md);">Perhatian Diperlukan</h6>
                        <?php if (!empty($monthlyData['alerts'])): ?>
                        <?php $bulanNamesLong=[1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember']; ?>
                        <?php
                            $bulanBerjalanAlert = $stats['tahun'] == (int)date('Y') ? (int)date('n') : 12;
                            $underAlerts = array_values(array_filter($monthlyData['alerts'], fn($a) =>
                                $a['type'] === 'under' && $a['bulan'] <= $bulanBerjalanAlert
                            ));
                            // Urutkan berdasarkan selisih absolut terbesar agar yang paling perlu perhatian tampil dulu
                            usort($underAlerts, fn($a, $b) => abs($b['rak'] - $b['realisasi']) <=> abs($a['rak'] - $a['realisasi']));
                            $underTop = array_slice($underAlerts, 0, 3);
                            $underRest = array_slice($underAlerts, 3);
                        ?>
                        <?php if (!empty($underAlerts)): ?>
                        <div style="font-size:var(--fs-sm);font-weight:700;color:#b45309;margin-bottom:0.4rem;"><i class="bi bi-arrow-down-circle me-1"></i>Realisasi kurang dari RAK (bulan yang sudah selesai):</div>
                        <div class="alert-chip-list">
                            <?php foreach ($underTop as $alert): ?>
                            <a class="alert-chip" href="#bulan-<?= (int) $alert['bulan'] ?>"
                               title="Lihat rincian bulan <?= $bulanNamesLong[$alert['bulan']] ?> di tabel Detail per Bulan">
                                <?= $bulanNamesLong[$alert['bulan']] ?> (&minus;Rp <?= number_format($alert['rak'] - $alert['realisasi'], 0, ',', '.') ?>)
                            </a>
                            <?php endforeach; ?>
                            <?php if (!empty($underRest)): ?>
                            <span id="alertChipExtra" hidden>
                                <?php foreach ($underRest as $alert): ?>
                                <a class="alert-chip" href="#bulan-<?= (int) $alert['bulan'] ?>"
                                   title="Lihat rincian bulan <?= $bulanNamesLong[$alert['bulan']] ?> di tabel Detail per Bulan">
                                    <?= $bulanNamesLong[$alert['bulan']] ?> (&minus;Rp <?= number_format($alert['rak'] - $alert['realisasi'], 0, ',', '.') ?>)
                                </a>
                                <?php endforeach; ?>
                            </span>
                            <button type="button" class="alert-chip-more" id="alertChipMore" aria-expanded="false" aria-controls="alertChipExtra">
                                <i class="bi bi-chevron-down me-1"></i>Lihat semua (<?= count($underAlerts) ?>)
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($stats['tahun'] == (int) date('Y')): ?>
                        <div style="font-size:var(--fs-xs);color:#92400e;margin-top:0.35rem;"><i class="bi bi-clock-history me-1"></i>Bulan berjalan (<?= $bulanNamesLong[$bulanBerjalanAlert] ?>) belum dinilai Under/Over RAK.</div>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($serapan > 90): ?>
                        <div style="font-size:var(--fs-base);color:var(--gray-600);<?= !empty($monthlyData['alerts']) ? 'margin-top:0.5rem;' : '' ?>">
                            <i class="bi bi-lightning-charge" style="color:var(--warning);"></i>
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
