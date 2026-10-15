
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
                </div>
                <div class="card-body" style="padding:1.5rem;overflow-x:auto;">

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
    <?php endif; ?>
            </div>
