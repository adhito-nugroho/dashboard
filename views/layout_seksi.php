<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['rlpm', 'tkuk', 'tu', 'seksi'], true)) {
    header('Location: ' . base_url('login'));
    exit;
}
$activePage = $activePage ?? '';
$seksiName = '';
if (!empty($_SESSION['seksi_id'])) {
    try {
        $db = \Database::getConnection();
        $st = $db->prepare('SELECT nama_seksi FROM seksi WHERE id = ?');
        $st->execute([$_SESSION['seksi_id']]);
        $seksiName = $st->fetchColumn() ?: '';
    } catch (\Exception $e) {
        $seksiName = '';
    }
}
$urlDashboard = base_url('dashboard/' . strtolower($_SESSION['role']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard Seksi') ?></title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=Source+Serif+4:ital,opsz,wght@0,8..60,500;0,8..60,600;0,8..60,700;1,8..60,400&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN + Config -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            corePlugins: {
                preflight: false,
            },
            theme: {
                extend: {
                    colors: {
                        forest: {
                            900: '#1F3D2B',
                            700: '#33553F',
                        },
                        moss: {
                            500: '#6B9080',
                            100: '#E4EBE6',
                        },
                        gold: {
                            500: '#B8874B',
                            100: '#F3E7D4',
                        },
                        paper: {
                            50: '#F7F5EF',
                            100: '#EFEAE0',
                        },
                        ink: {
                            900: '#23241F',
                            600: '#5C5A50',
                        },
                    },
                    fontFamily: {
                        serif: ['"Source Serif 4"', 'serif'],
                        sans: ['"IBM Plex Sans"', 'sans-serif'],
                    }
                }
            }
        };
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= base_url('css/style.css') ?>">
    <style>
        :root {
            --forest-900: #1F3D2B;
            --forest-700: #33553F;
            --moss-500: #6B9080;
            --moss-100: #E4EBE6;
            --gold-500: #B8874B;
            --gold-100: #F3E7D4;
            --paper-50: #F7F5EF;
            --paper-100: #EFEAE0;
            --ink-900: #23241F;
            --ink-600: #5C5A50;
            --border: #DBD5C6;
            --primary: #1F3D2B;
            --primary-dark: #33553F;
        }
        body {
            background: #F7F5EF;
            color: #23241F;
            font-family: 'IBM Plex Sans', sans-serif;
        }
        h1, h2, h3, h4, .font-serif {
            font-family: 'Source Serif 4', serif;
        }
        .seksi-header {
            background: #1F3D2B;
            color: #fff;
            box-shadow: 0 2px 8px rgba(31, 61, 43, 0.15);
        }
        .seksi-topbar {
            padding: 1rem 1.75rem 0.85rem;
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            background: #1F3D2B;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .seksi-nav {
            padding: 0 1.75rem;
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            background: #33553F;
        }
        .seksi-nav a {
            padding: 0.75rem 0.25rem;
            text-decoration: none;
            color: #E4ECE7;
            font-size: 0.875rem;
            font-weight: 500;
            border-bottom: 2px solid transparent;
            border-radius: 0;
            transition: all 0.15s ease;
            background: transparent;
        }
        .seksi-nav a:hover {
            color: #fff;
            background: transparent;
        }
        .seksi-nav a.active {
            color: #fff;
            background: transparent;
            border-bottom-color: #B8874B;
            font-weight: 600;
        }
        .bsa-card { border: 1px solid #DBD5C6; border-radius: 12px; background: #fff; box-shadow: 0 1px 3px rgba(35,23,42,.05); }
        @media (max-width: 640px) { .seksi-topbar { flex-direction: column; align-items: flex-start; } }
    </style>
</head>
<body>
    <header class="seksi-header">
        <div class="seksi-topbar">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-calculator" style="font-size:1.3rem;color:#F3E7D4;"></i>
                <span class="fw-bold" style="font-size:0.95rem;">CDK Wilayah Bojonegoro <small style="opacity:.82;font-weight:400;color:#D9E2DC;">· <?= htmlspecialchars($seksiName ?: ucfirst($_SESSION['role'])) ?></small></span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span style="font-size:0.875rem;"><i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($_SESSION['username'] ?? '') ?></span>
                <a href="<?= base_url('logout') ?>" class="btn btn-sm btn-outline-light" style="font-size:0.8rem;border-color:rgba(255,255,255,0.3);"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
            </div>
        </div>
        <nav class="seksi-nav">
            <a href="<?= $urlDashboard ?>" class="<?= $activePage === 'dashboardseksi' ? 'active' : '' ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
            <a href="<?= base_url('seksi/transaksi') ?>" class="<?= $activePage === 'transaksi' ? 'active' : '' ?>"><i class="bi bi-receipt me-1"></i>Transaksi Saya</a>
            <a href="<?= base_url('seksi/transaksi/create') ?>" class="<?= $activePage === 'transaksicreate' ? 'active' : '' ?>"><i class="bi bi-plus-circle me-1"></i>Tambah Transaksi</a>
        </nav>
    </header>
    <script>const BASE_URL = '<?= rtrim(base_url(), '/') ?>/';</script>
    <main class="container-fluid py-4">
        <?php if (!empty($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?= ($_SESSION['flash_type'] ?? 'info') === 'error' ? 'danger' : ($_SESSION['flash_type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['flash_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['flash_message']); unset($_SESSION['flash_type']); ?>
        <?php endif; ?>
        <?php if (isset($viewFile) && file_exists($viewFile)): ?>
            <?php include $viewFile; ?>
        <?php endif; ?>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (isset($additionalJS)): foreach ($additionalJS as $js): ?>
        <script src="<?= htmlspecialchars($js) ?>"></script>
    <?php endforeach; endif; ?>
</body>
</html>
