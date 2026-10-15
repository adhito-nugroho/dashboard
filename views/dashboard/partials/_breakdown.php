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
