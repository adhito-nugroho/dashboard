
            <!-- Seksi: Sisa per Semester -->
            <div class="tab-pane fade show active" id="tab-sisa-semester" role="region" aria-label="Sisa Dana per Semester">
                <!-- ===== Sisa Dana per Semester ===== -->
    <?php if (!empty($semesterRekap['sub_kegiatan'])): ?>
    <?php
        $semT = $semesterRekap['totals'];
        $semSK = $semesterRekap['sub_kegiatan'];
        $bulanBerjalanNow = (int) date('n');
        $tahunNow = (int) date('Y');
        // Rekening ber-RAK (yang dihitung di sini) vs semua rekening ber-pagu (tab Serapan Bulanan).
        $semTotalRekeningPagu = (int) ($monthlyAbsorptionDetails['totals']['rekening_count'] ?? $semT['total_rekening']);
        // Total sisa = S1 + S2 murni. sisa_s2 sudah termasuk carry-over S1 sehingga tidak boleh dijumlah lagi.
        $totalSisaTahun = (float) ($semT['sisa_total'] ?? ($semT['sisa_s1'] + ($semT['sisa_s2_raw'] ?? ($semT['sisa_s2'] - $semT['carry_over']))));
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
                        <span style="font-size:var(--fs-xs);font-weight:700;padding:0.3rem 0.875rem;border-radius:var(--radius-full);background:#ecfeff;color:#0369a1;border:1px solid #bae6fd;cursor:help;" data-bs-toggle="tooltip" title="<?= $semTotalRekeningPagu > $semT['total_rekening'] ? 'Ada ' . $semTotalRekeningPagu . ' rekening ber-pagu; ' . ($semTotalRekeningPagu - $semT['total_rekening']) . ' di antaranya belum memiliki RAK sehingga tidak tampil di tab ini.' : 'Seluruh rekening ber-pagu memiliki RAK.' ?>">
                            <i class="bi bi-bank me-1"></i><?= $semT['total_rekening'] ?> Rekening ber-RAK<?= $semTotalRekeningPagu > $semT['total_rekening'] ? ' <span style="opacity:.75;">(dari ' . $semTotalRekeningPagu . ')</span>' : '' ?>
                        </span>
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
                                    <div style="margin-top:0.5rem;font-size:var(--fs-xs);color:#cbd5e1;" title="Sisa S1 + Sisa S2 murni. Carry-over S1 sudah masuk ke kuota S2 sehingga tidak dihitung dua kali.">
                                        Akumulasi sisa <strong style="color:#fff;">S1 + S2</strong> (tanpa carry-over ganda)
                                    </div>
                                    <div style="margin-top:0.4rem;font-size:var(--fs-xs);color:#94a3b8;border-top:1px solid rgba(255,255,255,0.1);padding-top:0.4rem;">
                                        S1 Rp <?= number_format($semT['sisa_s1'], 0, ',', '.') ?> + S2 murni Rp <?= number_format($semT['sisa_s2_raw'] ?? ($semT['sisa_s2'] - $semT['carry_over']), 0, ',', '.') ?>
                                        &bull; Total RAK Tahun: Rp <?= number_format($semT['rak_s1']+$semT['rak_s2'], 0, ',', '.') ?>
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
                                <th class="sm-col-s1 sm-col-rak" title="RAK Semester 1 (Rupiah)">RAK (Rp)</th>
                                <th class="sm-col-s1 sm-col-real" title="Realisasi Semester 1 (Rupiah)">Realisasi (Rp)</th>
                                <th class="sm-col-s1 sm-col-sisa" title="Sisa Semester 1 (Rupiah)">Sisa (Rp)</th>
                                <th class="sm-col-s2 sm-col-rak" title="RAK Semester 2 (Rupiah)">RAK (Rp)</th>
                                <th class="sm-col-s2 sm-col-real" title="Realisasi Semester 2 (Rupiah)">Realisasi (Rp)</th>
                                <th class="sm-col-s2 sm-col-sisa" title="Sisa Semester 2 termasuk sisa semester 1 (Rupiah)">Sisa (Rp)</th>
                                <th class="sm-col-total" title="Total = Sisa S1 + Sisa S2 murni (carry-over S1 tidak dihitung dua kali), dalam Rupiah">Total (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($semSK as $skid => $sk): ?>
                            <?php
                                // Total murni: sisa_s2 sudah termasuk carry-over S1, jadi jangan tambah sisa_s1 lagi.
                                $skSisaS2Raw = $sk['rak_s2'] - $sk['real_s2'];
                                $skSisaTotal = $sk['sisa_total'] ?? ($sk['sisa_s1'] + $skSisaS2Raw);
                                $skHasS1 = $sk['sisa_s1'] > 0;
                                $skHasS2 = $sk['sisa_s2'] > 0;
                                $skOverS1 = $sk['rak_s1'] > 0 && $sk['sisa_s1'] < 0;
                                $skOverS2 = $sk['rak_s2'] > 0 && $skSisaS2Raw < 0;
                                $skHasNegS1 = $skOverS1;
                                $skHasNegS2 = $skOverS2;
                                $skHasNeg = $skOverS1 || $skOverS2;
                                $skOverLabel = $skOverS1 && $skOverS2 ? 'Over RAK S1 & S2' : ($skOverS1 ? 'Over RAK S1' : ($skOverS2 ? 'Over RAK S2' : 'Over RAK'));
                                $skOverTitle = $skOverS2
                                    ? 'Realisasi Semester 2 melebihi RAK S2 sebesar Rp ' . number_format(abs($skSisaS2Raw), 0, ',', '.')
                                        . ($sk['carry_over'] > 0 ? '; sisa S2 tetap positif karena carry-over S1 Rp ' . number_format($sk['carry_over'], 0, ',', '.') : '')
                                    : 'Realisasi melebihi RAK semester';
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
                                            <span class="sm-status-pill sm-pill-neg" title="<?= htmlspecialchars($skOverTitle) ?>" style="cursor:help;"><i class="bi bi-exclamation-triangle-fill"></i> <?= $skOverLabel ?></span>
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
                                    <span class="sm-sisa <?= $sk['sisa_s2']>0?'sm-sisa-pos':($sk['sisa_s2']<0?'sm-sisa-neg':'sm-sisa-zero') ?>"<?= $skOverS2 ? ' title="' . htmlspecialchars($skOverTitle) . '"' : '' ?>><?= fmtRpKompak($sk['sisa_s2']) ?></span>
                                    <?php if ($skOverS2 && $sk['sisa_s2'] > 0): ?>
                                    <div style="font-size:var(--fs-xs);color:#dc2626;font-weight:600;margin-top:0.1rem;white-space:normal;line-height:1.2;">Over RAK S2 <?= fmtRpKompak(abs($skSisaS2Raw)) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="sm-col-total">
                                    <span class="sm-sisa <?= $skSisaTotal>0?'sm-sisa-pos':($skSisaTotal<0?'sm-sisa-neg':'sm-sisa-zero') ?>"><?= fmtRpKompak($skSisaTotal) ?></span>
                                </td>
                            </tr>
                            <?php foreach ($sk['rekening'] as $rid => $rek): ?>
                                <?php
                                    $rekCarry     = $rek['carry_over'] ?? 0;
                                    $rekSisaS2Raw = $rek['sisa_s2_raw'] ?? ($rek['sisa_s2'] - $rekCarry);
                                    $rekHasS1    = $rek['sisa_s1'] > 0;
                                    $rekHasS2    = $rek['sisa_s2'] > 0;
                                    $rekHasNegS1 = $rek['sisa_s1'] < 0;
                                    $rekHasNegS2 = $rek['rak_s2'] > 0 && $rekSisaS2Raw < 0;
                                    $rekOverS2   = $rekHasNegS2 && $rek['sisa_s2'] > 0;
                                    $rekHasNeg   = $rekHasNegS1 || $rekHasNegS2;
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
                                        <?php if ($rekOverS2): ?>
                                        <div style="font-size:var(--fs-xs);color:#dc2626;font-weight:600;margin-top:0.1rem;white-space:normal;line-height:1.2;" title="Realisasi S2 melebihi RAK S2 sebesar Rp <?= number_format(abs($rekSisaS2Raw), 0, ',', '.') ?>; sisa tetap positif karena carry-over S1">Over RAK S2 <?= fmtRpKompak(abs($rekSisaS2Raw)) ?></div>
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
                        <span title="Realisasi S2 bisa melebihi RAK S2 namun sisa efektif tetap positif karena ada carry-over S1"><strong style="color:#059669;">+X sem 1</strong> = sisa S1 yang menambah kuota S2</span>
                        <span title="Realisasi S2 melebihi RAK S2, tetapi sisa efektif masih positif karena carry-over S1"><strong style="color:#dc2626;">Over RAK S2</strong> = realisasi S2 &gt; RAK S2</span>
                        <span style="margin-left:auto;color:#94a3b8;font-size:var(--fs-xs);">Sisa S2 = RAK S2 + Sisa Sem 1 &minus; Realisasi S2 &bull; Total = Sisa S1 + Sisa S2 murni</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
            </div>
