            <!-- Tab 1: Serapan Bulanan -->
            <div class="tab-pane fade show active" id="tab-serapan-rekening" role="tabpanel" aria-labelledby="tab-serapan-rekening-btn">
                <!-- Serapan per Sub Kegiatan per Rekening per Bulan -->
    <?php if (!empty($monthlyAbsorptionDetails['sub_kegiatan'])): ?>
    <?php
        $absorptionSubKegiatan = $monthlyAbsorptionDetails['sub_kegiatan'];
        $absorptionTotals = $monthlyAbsorptionDetails['totals'];
        $absorptionPct = $absorptionTotals['pagu'] > 0 ? ($absorptionTotals['realisasi'] / $absorptionTotals['pagu']) * 100 : 0;
        $absorptionSisa = $absorptionTotals['pagu'] - $absorptionTotals['realisasi'];
        $formatCompactRupiah = function ($value, bool $dashZero = false): string {
            $value = (float) $value;
            $abs = abs($value);
            if ($abs < 0.5) {
                return $dashZero ? '&mdash;' : 'Rp 0';
            }
            $sign = $value < 0 ? '-' : '';
            return ($sign ? '-' : '') . 'Rp ' . number_format($abs, 0, ',', '.');
        };
        $rupiahTooltip = function ($value): string {
            return '';
        };
    ?>
    <div class="row mb-4" id="section-serapan-rekening">
        <div class="col-12 animate-fade-in-up delay-5">
            <div class="card rounded-4 overflow-hidden">
                <div class="section-header" style="padding-bottom:0.75rem;">
                    <div>
                        <h5 class="section-title"><i class="bi bi-grid-3x3-gap-fill me-2" style="color:#0284c7;"></i>Serapan per Sub Kegiatan dan Rekening per Bulan</h5>
                        <p class="section-subtitle">Realisasi bulanan per rekening, dikelompokkan berdasarkan sub kegiatan</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <input type="text" id="absorptionSearchInput" class="form-control form-control-sm" placeholder="Cari sub kegiatan / rekening..." aria-label="Cari sub kegiatan atau rekening" style="width:230px;">
                        <button type="button" id="btnAbsorptionToggleAll" class="btn btn-outline-secondary btn-sm" aria-pressed="false" title="Buka atau tutup seluruh rincian rekening sekaligus">
                            <i class="bi bi-arrows-expand me-1"></i><span>Expand All</span>
                        </button>
                        <span style="font-size:var(--fs-xs);font-weight:700;padding:0.3rem 0.875rem;border-radius:var(--radius-full);background:#ecfeff;color:#0369a1;border:1px solid #bae6fd;white-space:nowrap;">
                            <i class="bi bi-wallet2 me-1"></i><?= (int) $absorptionTotals['rekening_count'] ?> Rekening
                        </span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="d-flex flex-wrap align-items-center gap-2 px-4 py-3" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                        <span style="font-size:var(--fs-sm);font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.04em;">Total Serapan</span>
                        <span style="font-size:var(--fs-base);font-weight:800;color:#0f172a;">Rp <?= number_format($absorptionTotals['realisasi'], 0, ',', '.') ?></span>
                        <span style="font-size:var(--fs-sm);color:#64748b;">dari pagu Rp <?= number_format($absorptionTotals['pagu'], 0, ',', '.') ?></span>
                        <span style="font-size:var(--fs-xs);font-weight:800;padding:0.2rem 0.6rem;border-radius:999px;background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;">
                            <?= number_format($absorptionPct, 2) ?>%
                        </span>
                    </div>
                    <div class="d-md-none px-3 py-2 bg-light border-bottom d-flex align-items-center justify-content-between">
    <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" id="toggleMonthlyDetailMobile" style="font-size:var(--fs-xs);font-weight:600;">
        <i class="bi bi-calendar3 me-1"></i><span id="toggleMonthlyDetailText">Lihat Semua Bulan (12 Bln)</span>
    </button>
    <span style="font-size:var(--fs-xs);color:var(--gray-500);"><i class="bi bi-info-circle me-1"></i>Ringkas di HP</span>
</div>
<div class="monthly-absorption-scroll-hint">
                        <span><i class="bi bi-arrows"></i> Geser tabel ke kanan/kiri untuk melihat semua bulan. Kolom Sub Kegiatan, Pagu, Total, dan % tetap terlihat.</span>
                    </div>
                    <div class="table-responsive monthly-absorption-wrap">
                        <table class="table table-hover table-dashboard align-middle mb-0 monthly-absorption-table">
                            <thead>
                                <tr>
                                    <th class="ps-4 monthly-absorption-name monthly-sticky-name">Sub Kegiatan / Rekening</th>
                                    <th class="text-end monthly-absorption-pagu monthly-sticky-pagu">Pagu</th>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <th class="text-end monthly-absorption-month"><?= $bulanNames[$m] ?></th>
                                    <?php endfor; ?>
                                    <th class="text-end text-nowrap monthly-absorption-total monthly-sticky-total">Total</th>
                                    <th class="text-end text-nowrap monthly-absorption-sisa monthly-sticky-sisa">Sisa Anggaran</th>
                                    <th class="text-end text-nowrap pe-4 monthly-absorption-percent monthly-sticky-percent">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($absorptionSubKegiatan as $skid => $sk): ?>
                                    <?php
                                        $skPct = $sk['pagu'] > 0 ? ($sk['realisasi'] / $sk['pagu']) * 100 : 0;
                                        $skSisa = $sk['pagu'] - $sk['realisasi'];
                                        $skRowKey = 'absorption-sk-' . (int) $skid;
                                    ?>
                                    <tr class="monthly-absorption-group is-collapsed" data-absorption-toggle="<?= htmlspecialchars($skRowKey) ?>" role="button" tabindex="0" aria-expanded="false">
                                        <td class="ps-4 monthly-absorption-name monthly-sticky-name">
                                            <div class="monthly-absorption-title">
                                                <button type="button" class="monthly-absorption-toggle-btn" aria-label="Buka rincian rekening" aria-expanded="false" data-absorption-toggle-button="<?= htmlspecialchars($skRowKey) ?>">
                                                    <i class="bi bi-chevron-right"></i>
                                                </button>
                                                <div>
                                                    <div class="monthly-absorption-sk-name"><?= htmlspecialchars($sk['nama']) ?></div>
                                                    <div class="monthly-absorption-sk-meta"><?= htmlspecialchars($sk['kode']) ?> &bull; <?= count($sk['rekening']) ?> rekening</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end monthly-absorption-pagu monthly-sticky-pagu"><?= $formatCompactRupiah($sk['pagu']) ?></td>
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <?php $monthValue = $sk['months'][$m] ?? 0; ?>
                                            <td class="text-end monthly-absorption-month <?= $monthValue > 0 ? 'monthly-absorption-filled' : 'monthly-absorption-zero' ?>"><?= $formatCompactRupiah($monthValue, true) ?></td>
                                        <?php endfor; ?>
                                        <td class="text-end monthly-absorption-total monthly-sticky-total"><?= $formatCompactRupiah($sk['realisasi']) ?></td>
                                        <td class="text-end monthly-absorption-sisa monthly-sticky-sisa <?= $skSisa < 0 ? 'is-over' : '' ?>"><?= $formatCompactRupiah($skSisa) ?></td>
                                        <td class="text-end pe-4 monthly-absorption-percent monthly-sticky-percent">
                                            <span class="monthly-absorption-pct <?= $skPct > 100 ? 'is-over' : ($skPct >= 80 ? 'is-high' : ($skPct > 0 ? 'is-mid' : 'is-empty')) ?>">
                                                <?= number_format($skPct, 2) ?>%
                                            </span>
                                        </td>
                                    </tr>
                                    <?php foreach ($sk['rekening'] as $rek): ?>
                                        <?php
                                            $rekPct = $rek['pagu'] > 0 ? ($rek['realisasi'] / $rek['pagu']) * 100 : 0;
                                            $rekSisa = $rek['pagu'] - $rek['realisasi'];
                                        ?>
                                        <tr class="monthly-absorption-detail" data-absorption-child="<?= htmlspecialchars($skRowKey) ?>" hidden>
                                            <td class="ps-4 monthly-absorption-name monthly-sticky-name">
                                                <div style="font-weight:700;color:#334155;"><?= htmlspecialchars($rek['nama']) ?></div>
                                                <div style="font-size:var(--fs-xs);color:#94a3b8;font-weight:600;">Kode rekening: <?= htmlspecialchars($rek['kode']) ?></div>
                                            </td>
                                            <td class="text-end monthly-absorption-pagu monthly-sticky-pagu"><?= $formatCompactRupiah($rek['pagu']) ?></td>
                                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                                <?php $monthValue = $rek['months'][$m] ?? 0; ?>
                                                <td class="text-end monthly-absorption-month <?= $monthValue > 0 ? 'monthly-absorption-filled' : 'monthly-absorption-zero' ?>"><?= $formatCompactRupiah($monthValue, true) ?></td>
                                            <?php endfor; ?>
                                            <td class="text-end monthly-absorption-total monthly-sticky-total"><?= $formatCompactRupiah($rek['realisasi']) ?></td>
                                            <td class="text-end monthly-absorption-sisa monthly-sticky-sisa <?= $rekSisa < 0 ? 'is-over' : '' ?>"><?= $formatCompactRupiah($rekSisa) ?></td>
                                            <td class="text-end pe-4 monthly-absorption-percent monthly-sticky-percent">
                                                <span class="monthly-absorption-pct <?= $rekPct > 100 ? 'is-over' : ($rekPct >= 80 ? 'is-high' : ($rekPct > 0 ? 'is-mid' : 'is-empty')) ?>">
                                                    <?= number_format($rekPct, 2) ?>%
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td class="ps-4 monthly-absorption-name monthly-sticky-name">Total</td>
                                    <td class="text-end monthly-absorption-pagu monthly-sticky-pagu"><?= $formatCompactRupiah($absorptionTotals['pagu']) ?></td>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <?php $monthValue = $absorptionTotals['months'][$m] ?? 0; ?>
                                        <td class="text-end monthly-absorption-month <?= $monthValue > 0 ? 'monthly-absorption-filled' : 'monthly-absorption-zero' ?>"><?= $formatCompactRupiah($monthValue, true) ?></td>
                                    <?php endfor; ?>
                                    <td class="text-end monthly-absorption-total monthly-sticky-total"><?= $formatCompactRupiah($absorptionTotals['realisasi']) ?></td>
                                    <td class="text-end monthly-absorption-sisa monthly-sticky-sisa <?= $absorptionSisa < 0 ? 'is-over' : '' ?>"><?= $formatCompactRupiah($absorptionSisa) ?></td>
                                    <td class="text-end pe-4 monthly-absorption-percent monthly-sticky-percent"><?= number_format($absorptionPct, 2) ?>%</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div id="absorptionEmptyMsg" style="display:none;padding:2rem 1rem;text-align:center;color:var(--gray-400);font-size:var(--fs-base);">
                        <i class="bi bi-search me-1"></i>Tidak ada sub kegiatan / rekening yang cocok dengan pencarian.
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
            </div>
