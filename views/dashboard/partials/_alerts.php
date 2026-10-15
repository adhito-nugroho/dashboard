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

