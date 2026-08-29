<?php if (session_status() === PHP_SESSION_NONE) { session_start(); } ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard Seksi') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= base_url('css/style.css') ?>">
    <style>
        :root {
            --primary: #3b82f6;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-400: #9ca3af;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --fs-xs: 0.68rem;
            --fs-sm: 0.75rem;
            --fs-base: 0.85rem;
            --fs-md: 0.95rem;
            --fs-lg: 1.1rem;
            --fs-xl: 1.35rem;
        }
        .kpi-card {
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            transition: all 0.2s;
        }
        .kpi-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .kpi-label {
            font-size: var(--fs-sm);
            color: var(--gray-600);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .kpi-value {
            font-size: var(--fs-xl);
            font-weight: 800;
            color: var(--gray-900);
            margin-top: 0.25rem;
        }
        .chart-wrapper {
            position: relative;
            height: 300px;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= base_url() ?>">
                <i class="bi bi-speedometer2"></i> Dashboard Anggaran
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="text-white"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
                <a href="<?= base_url('logout') ?>" class="btn btn-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid" style="padding: 1.75rem 1.5rem;">
        <div class="mb-4">
            <h2 style="font-weight:800;color:var(--gray-900);font-size:var(--fs-xl);">
                <i class="bi bi-building me-2" style="color:var(--primary);"></i><?= htmlspecialchars($pageTitle) ?>
            </h2>
            <p style="color:var(--gray-400);font-size:var(--fs-base);">Monitoring Anggaran Tahun <strong style="color:var(--gray-600);"><?= $stats['tahun'] ?></strong></p>
        </div>

        <div class="row mb-4 g-3">
            <div class="col-xl-3 col-md-6">
                <div class="card kpi-card h-100">
                    <div class="card-body" style="padding:1.25rem;">
                        <div class="d-flex align-items-start gap-3">
                            <div class="kpi-icon" style="background:#eff6ff;color:var(--primary);">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                            <div style="flex:1;">
                                <div class="kpi-label">Total Pagu</div>
                                <div class="kpi-value">Rp <?= number_format($stats['total_pagu'], 0, ',', '.') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card kpi-card h-100">
                    <div class="card-body" style="padding:1.25rem;">
                        <div class="d-flex align-items-start gap-3">
                            <div class="kpi-icon" style="background:#ecfeff;color:#0891b2;">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            <div style="flex:1;">
                                <div class="kpi-label">Total RAK</div>
                                <div class="kpi-value">Rp <?= number_format($stats['total_rak'], 0, ',', '.') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card kpi-card h-100">
                    <div class="card-body" style="padding:1.25rem;">
                        <div class="d-flex align-items-start gap-3">
                            <div class="kpi-icon" style="background:#ecfdf5;color:#059669;">
                                <i class="bi bi-receipt-cutoff"></i>
                            </div>
                            <div style="flex:1;">
                                <div class="kpi-label">Realisasi</div>
                                <div class="kpi-value">Rp <?= number_format($stats['total_realisasi'], 0, ',', '.') ?></div>
                                <div class="mt-1">
                                    <span style="font-size:var(--fs-xs);font-weight:700;color:#059669;">
                                        <?= number_format($stats['percentage'], 2) ?>%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card kpi-card h-100">
                    <div class="card-body" style="padding:1.25rem;">
                        <div class="d-flex align-items-start gap-3">
                            <div class="kpi-icon" style="background:#fffbeb;color:#d97706;">
                                <i class="bi bi-wallet2"></i>
                            </div>
                            <div style="flex:1;">
                                <div class="kpi-label">Sisa Anggaran</div>
                                <div class="kpi-value" style="color:<?= $stats['sisa_anggaran'] < 0 ? '#dc2626' : '#d97706' ?>;">
                                    Rp <?= number_format(abs($stats['sisa_anggaran']), 0, ',', '.') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card" style="border-radius:12px;">
                    <div class="card-body" style="padding:1.5rem;">
                        <h5 style="font-weight:700;color:var(--gray-800);margin-bottom:1rem;">
                            <i class="bi bi-graph-up me-2" style="color:var(--primary);"></i>Grafik Realisasi Bulanan
                        </h5>
                        <div class="chart-wrapper">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        const monthlyData = <?= json_encode($monthlyData) ?>;
        const bulanNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: bulanNames,
                datasets: [
                    {
                        label: 'RAK',
                        data: Object.values(monthlyData.rak),
                        backgroundColor: 'rgba(59, 130, 246, 0.5)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Realisasi',
                        data: Object.values(monthlyData.realisasi),
                        backgroundColor: 'rgba(5, 150, 105, 0.5)',
                        borderColor: 'rgba(5, 150, 105, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': Rp ' + context.parsed.y.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
