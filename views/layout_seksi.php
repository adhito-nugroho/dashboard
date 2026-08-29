<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['rlpm', 'tkuk', 'seksi'], true)) {
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
        :root { --primary: #3b82f6; }
        body { background: #f1f5f9; }
        .seksi-topbar {
            background: #0f172a; color: #fff; padding: 0.75rem 1.5rem;
            display: flex; align-items: center; justify-content: space-between;
        }
        .seksi-nav {
            background: #fff; border-bottom: 1px solid #e2e8f0;
            padding: 0 1.5rem; display: flex; gap: 0.25rem; flex-wrap: wrap;
        }
        .seksi-nav a {
            padding: 0.75rem 1rem; text-decoration: none; color: #475569;
            font-size: 0.875rem; font-weight: 600; border-bottom: 2px solid transparent;
        }
        .seksi-nav a.active { color: #2563eb; border-bottom-color: #2563eb; }
        .bsa-card { border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; }
    </style>
</head>
<body>
    <div class="seksi-topbar">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-calculator" style="font-size:1.3rem;"></i>
            <span class="fw-bold">CDK Wilayah Bojonegoro <small style="opacity:.8;">· <?= htmlspecialchars($seksiName ?: ucfirst($_SESSION['role'])) ?></small></span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username'] ?? '') ?></span>
            <a href="<?= base_url('logout') ?>" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>
    <div class="seksi-nav">
        <a href="<?= $urlDashboard ?>" class="<?= $activePage === 'dashboardseksi' ? 'active' : '' ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
        <a href="<?= base_url('seksi/transaksi') ?>" class="<?= $activePage === 'transaksi' ? 'active' : '' ?>"><i class="bi bi-receipt me-1"></i>Transaksi Saya</a>
        <a href="<?= base_url('seksi/transaksi/create') ?>" class="<?= $activePage === 'transaksicreate' ? 'active' : '' ?>"><i class="bi bi-plus-circle me-1"></i>Tambah Transaksi</a>
    </div>
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
