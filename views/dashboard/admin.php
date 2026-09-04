<?php
/**
 * Admin Dashboard — Verification Command Center
 * 
 * Variables from DashboardController::index():
 *   $tahun, $statusCounts, $recentPending, $recentActivity,
 *   $pendingBySeksi, $monthlyTrend, $pendingCount
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Helper: format rupiah
function formatRp(float $v): string {
    return 'Rp ' . number_format($v, 0, ',', '.');
}

// Helper: relative time
function timeAgo(string $datetime): string {
    $ts = strtotime($datetime);
    if (!$ts) return '-';
    $diff = time() - $ts;
    if ($diff < 60)    return 'Baru saja';
    if ($diff < 3600)  return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    if ($diff < 604800) return floor($diff / 86400) . ' hari lalu';
    return date('d M Y', $ts);
}

$bulanNames = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
$maxPendingSeksi = 0;
foreach ($pendingBySeksi as $ps) {
    if ((int) $ps['cnt'] > $maxPendingSeksi) $maxPendingSeksi = (int) $ps['cnt'];
}
?>

<div class="admin-dash">
    <!-- Header -->
    <div class="admin-dash-header d-flex flex-wrap justify-content-between align-items-end animate-fade-in-up">
        <div>
            <h2><i class="bi bi-shield-check me-2" style="color:var(--primary);"></i>Dashboard Admin</h2>
            <p>Pusat verifikasi transaksi anggaran — Tahun <strong style="color:var(--gray-600);"><?= $tahun ?></strong></p>
        </div>
        <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
            <form method="GET" action="<?= base_url() ?>" style="background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-full);padding:0.35rem 0.5rem 0.35rem 1rem;display:inline-flex;align-items:center;gap:0.5rem;box-shadow:var(--shadow-xs);">
                <i class="bi bi-calendar3" style="color:var(--primary);font-size:var(--fs-md);"></i>
                <select class="form-select form-select-sm border-0 bg-transparent fw-bold py-0" name="tahun" onchange="this.form.submit()" style="width:auto;cursor:pointer;box-shadow:none;color:var(--primary);font-size:var(--fs-base);">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                    <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </form>
        </div>
    </div>

    <!-- Row 1: Status Summary Cards -->
    <div class="row g-3 mb-4 animate-fade-in-up" style="animation-delay:0.05s;">
        <!-- Menunggu Verifikasi -->
        <div class="col-6 col-lg-3">
            <a href="<?= base_url('transaksi') ?>?status=diajukan&tahun=<?= $tahun ?>" class="admin-stat-card admin-stat-card--pending">
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <div class="admin-stat-card__icon">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
                <div class="admin-stat-card__count"><?= $statusCounts['diajukan']['count'] ?></div>
                <div class="admin-stat-card__label">Menunggu Verifikasi</div>
                <div class="admin-stat-card__value mt-1"><?= formatRp($statusCounts['diajukan']['total']) ?></div>
            </a>
        </div>

        <!-- Terverifikasi -->
        <div class="col-6 col-lg-3">
            <a href="<?= base_url('transaksi') ?>?status=diverifikasi&tahun=<?= $tahun ?>" class="admin-stat-card admin-stat-card--verified">
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <div class="admin-stat-card__icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
                <div class="admin-stat-card__count"><?= $statusCounts['diverifikasi']['count'] ?></div>
                <div class="admin-stat-card__label">Terverifikasi</div>
                <div class="admin-stat-card__value mt-1"><?= formatRp($statusCounts['diverifikasi']['total']) ?></div>
            </a>
        </div>

        <!-- Ditolak -->
        <div class="col-6 col-lg-3">
            <a href="<?= base_url('transaksi') ?>?status=ditolak&tahun=<?= $tahun ?>" class="admin-stat-card admin-stat-card--rejected">
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <div class="admin-stat-card__icon">
                        <i class="bi bi-x-circle"></i>
                    </div>
                </div>
                <div class="admin-stat-card__count"><?= $statusCounts['ditolak']['count'] ?></div>
                <div class="admin-stat-card__label">Ditolak</div>
                <div class="admin-stat-card__value mt-1"><?= formatRp($statusCounts['ditolak']['total']) ?></div>
            </a>
        </div>

        <!-- Total -->
        <div class="col-6 col-lg-3">
            <a href="<?= base_url('transaksi') ?>?tahun=<?= $tahun ?>" class="admin-stat-card admin-stat-card--total">
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <div class="admin-stat-card__icon">
                        <i class="bi bi-receipt-cutoff"></i>
                    </div>
                </div>
                <div class="admin-stat-card__count"><?= $statusCounts['all']['count'] ?></div>
                <div class="admin-stat-card__label">Total Transaksi</div>
                <div class="admin-stat-card__value mt-1"><?= formatRp($statusCounts['all']['total']) ?></div>
            </a>
        </div>
    </div>

    <!-- Row 2: Queue + Side Panel -->
    <div class="row g-3 mb-4 animate-fade-in-up" style="animation-delay:0.1s;">
        <!-- Left: Verification Queue -->
        <div class="col-12 col-xl-8">
            <div class="admin-queue">
                <div class="admin-queue__header">
                    <div class="admin-queue__title">
                        <i class="bi bi-inbox" style="color:var(--warning);"></i>
                        Antrian Verifikasi
                        <?php if ($pendingCount > 0): ?>
                            <span class="admin-queue__count"><?= $pendingCount ?></span>
                        <?php endif; ?>
                    </div>
                    <a href="<?= base_url('transaksi') ?>?status=diajukan" class="btn btn-sm btn-outline-secondary px-3" style="font-size:var(--fs-sm);border-radius:var(--radius-full);">
                        Lihat Semua <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>

                <?php if (empty($recentPending)): ?>
                    <div class="admin-queue__empty">
                        <i class="bi bi-check-circle"></i>
                        <div style="font-size:var(--fs-base);font-weight:600;color:var(--gray-600);">Semua Bersih!</div>
                        <div style="font-size:var(--fs-sm);color:var(--gray-400);margin-top:0.25rem;">Tidak ada transaksi yang menunggu verifikasi saat ini.</div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Seksi</th>
                                    <th>Rekening</th>
                                    <th>Uraian</th>
                                    <th class="text-end">Nilai</th>
                                    <th class="text-center" style="width:100px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentPending as $trx): ?>
                                <tr>
                                    <td>
                                        <span class="fw-medium text-dark"><?= date('d/m', strtotime($trx['tanggal'])) ?></span>
                                        <span class="text-muted" style="font-size:var(--fs-xs);">/<?= date('Y', strtotime($trx['tanggal'])) ?></span>
                                    </td>
                                    <td>
                                        <span style="font-size:var(--fs-sm);font-weight:700;color:var(--gray-700);background:var(--gray-50);border:1px solid var(--gray-200);padding:1px 6px;border-radius:4px;">
                                            <?= htmlspecialchars($trx['kode_seksi']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-family:monospace;font-size:var(--fs-sm);color:var(--gray-600);background:var(--gray-50);border:1px solid var(--gray-200);padding:1px 6px;border-radius:4px;">
                                            <?= htmlspecialchars($trx['kode_rekening']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="uraian-truncate" title="<?= htmlspecialchars($trx['uraian']) ?>">
                                            <?= htmlspecialchars($trx['uraian']) ?>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <span class="fw-bold text-dark" style="font-family:monospace;font-size:var(--fs-base);">
                                            <?= number_format($trx['nilai'], 0, ',', '.') ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex align-items-center justify-content-center gap-1">
                                            <a href="<?= base_url('transaksi/show/' . $trx['id']) ?>" 
                                               class="quick-action-btn quick-action-btn--view"
                                               title="Detail">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <button type="button" 
                                                    class="quick-action-btn quick-action-btn--verify btn-dash-verif"
                                                    data-id="<?= $trx['id'] ?>"
                                                    title="Verifikasi">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                            <button type="button" 
                                                    class="quick-action-btn quick-action-btn--reject btn-dash-tolak"
                                                    data-id="<?= $trx['id'] ?>"
                                                    title="Tolak">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Side Panels -->
        <div class="col-12 col-xl-4">
            <!-- Distribusi per Seksi -->
            <div class="admin-panel mb-3">
                <div class="admin-panel__header">
                    <i class="bi bi-bar-chart-line" style="color:var(--warning);"></i>
                    Pending per Seksi
                </div>
                <div class="admin-panel__body">
                    <?php if (empty($pendingBySeksi)): ?>
                        <div class="text-center py-3" style="color:var(--gray-400);font-size:var(--fs-sm);">
                            <i class="bi bi-check-circle d-block" style="font-size:1.5rem;opacity:0.4;margin-bottom:0.25rem;"></i>
                            Tidak ada transaksi pending
                        </div>
                    <?php else: ?>
                        <?php foreach ($pendingBySeksi as $ps): 
                            $pct = $maxPendingSeksi > 0 ? ((int)$ps['cnt'] / $maxPendingSeksi) * 100 : 0;
                        ?>
                        <div class="seksi-bar-row">
                            <div class="seksi-bar-label"><?= htmlspecialchars($ps['kode_seksi']) ?></div>
                            <div class="seksi-bar-track">
                                <div class="seksi-bar-fill" style="width:<?= max(8, $pct) ?>%;">
                                    <span><?= (int)$ps['cnt'] ?></span>
                                </div>
                            </div>
                            <div class="seksi-bar-value"><?= formatRp((float)$ps['total_nilai']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Aktivitas Terkini -->
            <div class="admin-panel">
                <div class="admin-panel__header">
                    <i class="bi bi-clock-history" style="color:var(--primary);"></i>
                    Aktivitas Terkini
                </div>
                <div class="admin-panel__body" style="padding-top:0.5rem;padding-bottom:0.5rem;">
                    <?php if (empty($recentActivity)): ?>
                        <div class="text-center py-3" style="color:var(--gray-400);font-size:var(--fs-sm);">
                            Belum ada aktivitas verifikasi
                        </div>
                    <?php else: ?>
                        <ul class="admin-timeline">
                            <?php foreach ($recentActivity as $act): 
                                $isVerified = $act['status'] === 'diverifikasi';
                                $icon = $isVerified ? 'verified' : 'rejected';
                                $verb = $isVerified ? 'memverifikasi' : 'menolak';
                            ?>
                            <li class="admin-timeline__item">
                                <div class="admin-timeline__dot admin-timeline__dot--<?= $icon ?>"></div>
                                <div class="admin-timeline__text">
                                    <strong><?= htmlspecialchars($act['kode_seksi']) ?></strong>
                                    <?= $verb ?>
                                    <span style="color:var(--gray-800);font-weight:600;"><?= formatRp((float)$act['nilai']) ?></span>
                                </div>
                                <div class="admin-timeline__time"><?= timeAgo($act['diverifikasi_at']) ?></div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 3: Monthly Trend -->
    <div class="admin-trend animate-fade-in-up" style="animation-delay:0.15s;">
        <div class="admin-trend__header">
            <i class="bi bi-graph-up" style="color:var(--primary);"></i>
            Tren Transaksi Bulanan <?= $tahun ?>
        </div>
        <div class="admin-trend__body">
            <canvas id="adminTrendChart" height="85"></canvas>
        </div>
    </div>
</div>

<!-- Modal Tolak (Dashboard) -->
<div class="modal fade" id="modalDashTolak" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form class="modal-content border-0 shadow" id="formDashTolak" method="POST">
            <div class="modal-header py-2" style="background:var(--status-danger-bg);border-bottom:1px solid var(--status-danger-border);">
                <h6 class="modal-title fw-bold" style="color:var(--danger);font-size:var(--fs-base);"><i class="bi bi-x-circle me-2"></i>Tolak Transaksi</h6>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <label for="dashCatatan" class="form-label" style="font-size:var(--fs-sm);font-weight:600;color:var(--gray-600);">Alasan Penolakan</label>
                <textarea id="dashCatatan" name="catatan_verifikasi" class="form-control form-control-sm" rows="3" placeholder="Tuliskan alasan..." required style="font-size:var(--fs-sm);"></textarea>
            </div>
            <div class="modal-footer py-2 border-top">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-danger">Tolak</button>
            </div>
        </form>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>

<script>
(function() {
    'use strict';

    // ── Quick Actions: Verify ──
    document.querySelectorAll('.btn-dash-verif').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            if (!confirm('Verifikasi transaksi #' + id + '?')) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = BASE_URL + 'transaksi/verifikasi/' + id;
            form.style.display = 'none';
            document.body.appendChild(form);
            form.submit();
        });
    });

    // ── Quick Actions: Reject ──
    let rejectId = null;
    const modalTolak = document.getElementById('modalDashTolak');
    const formTolak  = document.getElementById('formDashTolak');

    document.querySelectorAll('.btn-dash-tolak').forEach(btn => {
        btn.addEventListener('click', function() {
            rejectId = this.dataset.id;
            formTolak.action = BASE_URL + 'transaksi/tolak/' + rejectId;
            new bootstrap.Modal(modalTolak).show();
        });
    });

    // ── Chart: Monthly Trend ──
    const ctx = document.getElementById('adminTrendChart');
    if (ctx) {
        const monthLabels = <?= json_encode(array_values($bulanNames)) ?>;
        const trendData   = <?= json_encode(array_values($monthlyTrend)) ?>;

        const diajukan     = trendData.map(d => d.diajukan);
        const diverifikasi = trendData.map(d => d.diverifikasi);
        const ditolak      = trendData.map(d => d.ditolak);

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: [
                    {
                        label: 'Diajukan',
                        data: diajukan,
                        backgroundColor: 'rgba(245, 158, 11, 0.7)',
                        borderColor: '#f59e0b',
                        borderWidth: 1,
                        borderRadius: 4,
                        barPercentage: 0.7,
                        categoryPercentage: 0.65
                    },
                    {
                        label: 'Terverifikasi',
                        data: diverifikasi,
                        backgroundColor: 'rgba(5, 150, 105, 0.7)',
                        borderColor: '#059669',
                        borderWidth: 1,
                        borderRadius: 4,
                        barPercentage: 0.7,
                        categoryPercentage: 0.65
                    },
                    {
                        label: 'Ditolak',
                        data: ditolak,
                        backgroundColor: 'rgba(220, 38, 38, 0.5)',
                        borderColor: '#dc2626',
                        borderWidth: 1,
                        borderRadius: 4,
                        barPercentage: 0.7,
                        categoryPercentage: 0.65
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'rectRounded',
                            padding: 16,
                            font: { size: 11, family: 'Inter', weight: '600' }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: { size: 12, family: 'Inter', weight: '700' },
                        bodyFont: { size: 11, family: 'Inter' },
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(ctx) {
                                return ' ' + ctx.dataset.label + ': ' + ctx.raw + ' transaksi';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 11, family: 'Inter', weight: '600' },
                            color: '#94a3b8'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: { size: 11, family: 'Inter' },
                            color: '#94a3b8'
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.04)',
                            drawBorder: false
                        }
                    }
                }
            }
        });
    }
})();
</script>
