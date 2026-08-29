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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= base_url('css/style.css') ?>">
    <style>
        :root { --primary: #4f46e5; --primary-dark: #312e81; }
        body { background: #f8fafc; color: #0f172a; }
        .seksi-header {
            background: linear-gradient(135deg, #312e81 0%, #4338ca 55%, #4f46e5 100%);
            color: #fff;
            box-shadow: 0 8px 24px rgba(49,46,129,.18);
        }
        .seksi-topbar {
            padding: 1rem 1.5rem .75rem;
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        }
        .seksi-nav {
            padding: 0 1.5rem; display: flex; gap: .35rem; flex-wrap: wrap;
        }
        .seksi-nav a {
            padding: .8rem 1rem; text-decoration: none; color: rgba(255,255,255,.76);
            font-size: .875rem; font-weight: 700; border-bottom: 3px solid transparent;
            border-radius: 10px 10px 0 0; transition: all .15s ease;
        }
        .seksi-nav a:hover { color: #fff; background: rgba(255,255,255,.08); }
        .seksi-nav a.active { color: #fff; background: rgba(255,255,255,.12); border-bottom-color: #c7d2fe; }
        .bsa-card { border: 1px solid #e2e8f0; border-radius: 16px; background: #fff; box-shadow: 0 1px 3px rgba(15,23,42,.05); }
        @media (max-width: 640px) { .seksi-topbar { flex-direction: column; align-items: flex-start; } }
    </style>
</head>
<body>
    <header class="seksi-header">
        <div class="seksi-topbar">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-calculator" style="font-size:1.3rem;"></i>
                <span class="fw-bold">CDK Wilayah Bojonegoro <small style="opacity:.82;">· <?= htmlspecialchars($seksiName ?: ucfirst($_SESSION['role'])) ?></small></span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username'] ?? '') ?></span>
                <a href="<?= base_url('logout') ?>" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right"></i> Logout</a>
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
