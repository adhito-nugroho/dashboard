    <!-- Detail Penyerapan per Bulan -->
    <div class="row mb-4" id="section-detail-bulan">
        <div class="col-12 animate-fade-in-up delay-5">
            <div class="card rounded-4 overflow-hidden">
                <div class="section-header" style="padding-bottom:0.75rem;">
                    <div><h5 class="section-title"><i class="bi bi-calendar2-month me-2" style="color:var(--primary);"></i>Detail Penyerapan per Bulan</h5><p class="section-subtitle">Tabel rincian RAK (Target) vs Realisasi tiap bulan</p></div>
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
                                <tr style="opacity:0.9;">
                                    <td class="ps-4" style="font-weight:600;color:#64748b;">
                                        <?= $bulanNames[(int)$i] ?>
                                        <span style="font-size:var(--fs-xs);font-weight:700;padding:0.15rem 0.45rem;border-radius:var(--radius-full);background:#f1f5f9;border:1px solid #e2e8f0;color:#64748b;margin-left:0.4rem;vertical-align:middle;">Belum Berjalan</span>
                                    </td>
                                    <td class="text-end" style="color:#64748b;">Rp <?= number_format($mRak,0,',','.') ?></td>
                                    <td class="text-end" style="color:#64748b;">&mdash;</td>
                                    <td class="text-end" style="color:#64748b;">&mdash;</td>
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


