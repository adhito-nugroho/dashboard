<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="theme-color" content="#0f172a">
    <title><?= htmlspecialchars($pageTitle ?? 'CDK Bojonegoro') ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- Custom CSS with cache busting -->
    <link rel="stylesheet" href="<?= base_url('css/style.css') ?>?v=<?= file_exists(__DIR__ . '/../public/css/style.css') ? filemtime(__DIR__ . '/../public/css/style.css') : time() ?>">

    <!-- Design Tokens -->
    <style>
    :root {
        --status-success: #059669;
        --status-success-bg: #ecfdf5;
        --status-success-border: #bbf7d0;
        --status-warning: #d97706;
        --status-warning-bg: #fffbeb;
        --status-warning-border: #fde68a;
        --status-danger: #dc2626;
        --status-danger-bg: #fef2f2;
        --status-danger-border: #fecaca;
        --status-neutral: #94a3b8;
        --status-neutral-bg: #f1f5f9;
        --status-neutral-border: #e2e8f0;

        --fs-xs: 0.68rem;
        --fs-sm: 0.75rem;
        --fs-base: 0.85rem;
        --fs-md: 0.95rem;
        --fs-lg: 1.1rem;
        --fs-xl: 1.35rem;
    }
    </style>

    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>

<body>
    <div class="wrapper d-flex">
        <?php $hideSidebar = (isset($activePage) && $activePage === 'dashboard') && empty($_SESSION['is_admin']); ?>
        <?php if (!$hideSidebar): ?>
            <!-- Sidebar -->
            <aside id="sidebar" class="sidebar">
                <div class="sidebar-header">
                    <h4 class="mb-0">
                        <i class="bi bi-calculator"></i>
                        <span class="ms-2" style="font-size:1.1rem; line-height: 1.2;">CDK Wilayah Bojonegoro<br><small
                                style="font-size:0.75rem; opacity:0.8;">Monitoring Anggaran</small></span>
                    </h4>
                    <button class="btn btn-link d-md-none text-white p-0" id="sidebarClose" type="button"
                        aria-label="Tutup menu" style="font-size:1.25rem;opacity:0.7;">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <nav class="sidebar-nav">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?= (isset($activePage) && $activePage === 'dashboard') ? 'active' : '' ?>"
                                href="<?= base_url() ?>">
                                <i class="bi bi-speedometer2"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                            <li class="nav-item">
                                <a class="nav-link <?= (isset($activePage) && $activePage === 'seksi') ? 'active' : '' ?>"
                                    href="<?= base_url('seksi') ?>">
                                    <i class="bi bi-building"></i>
                                    <span>Seksi</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= (isset($activePage) && $activePage === 'program') ? 'active' : '' ?>"
                                    href="<?= base_url('program') ?>">
                                    <i class="bi bi-folder"></i>
                                    <span>Program</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= (isset($activePage) && $activePage === 'kegiatan') ? 'active' : '' ?>"
                                    href="<?= base_url('kegiatan') ?>">
                                    <i class="bi bi-list-task"></i>
                                    <span>Kegiatan</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= (isset($activePage) && $activePage === 'sub_kegiatan') ? 'active' : '' ?>"
                                    href="<?= base_url('sub-kegiatan') ?>">
                                    <i class="bi bi-list-nested"></i>
                                    <span>Sub Kegiatan</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= (isset($activePage) && $activePage === 'rekening') ? 'active' : '' ?>"
                                    href="<?= base_url('rekening') ?>">
                                    <i class="bi bi-wallet2"></i>
                                    <span>Rekening</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= (isset($activePage) && $activePage === 'pagu') ? 'active' : '' ?>"
                                    href="<?= base_url('pagu') ?>">
                                    <i class="bi bi-cash-stack"></i>
                                    <span>Pagu</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= (isset($activePage) && $activePage === 'rak') ? 'active' : '' ?>"
                                    href="<?= base_url('rak') ?>">
                                    <i class="bi bi-calendar-month"></i>
                                    <span>RAK</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= (isset($activePage) && $activePage === 'rekap_rak') ? 'active' : '' ?>"
                                    href="<?= base_url('rak/rekap') ?>">
                                    <i class="bi bi-calendar2-check"></i>
                                    <span>Rekap RAK</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= (isset($activePage) && $activePage === 'transaksi') ? 'active' : '' ?>"
                                    href="<?= base_url('transaksi') ?>">
                                    <i class="bi bi-receipt"></i>
                                    <span>Transaksi</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </aside>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="main-content flex-grow-1" <?= $hideSidebar ? 'style="margin-left: 0; width: 100%;"' : '' ?>>
            <!-- Header -->
            <header class="header">
                <div class="header-content d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <?php if (!$hideSidebar): ?>
                            <button class="btn btn-link d-md-none" id="sidebarToggle" type="button" aria-label="Buka menu">
                                <i class="bi bi-list"></i>
                            </button>
                        <?php endif; ?>
                        <img src="<?= base_url('images/logo_jatim.png') ?>" alt="Logo Jatim"
                            style="height:35px; width:auto; margin-right:12px;"
                            class="<?= $hideSidebar ? '' : 'ms-2 ms-md-0' ?>">
                        <h5 class="mb-0 fw-bold" style="color:#1e293b;">
                            <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h5>
                    </div>
                    <div class="header-actions">
                        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                            <a href="<?= base_url('logout') ?>" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        <?php else: ?>
                            <a href="<?= base_url('login') ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-box-arrow-in-right"></i> Login Admin
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="content">
                <?php if (isset($content)): ?>
                    <?= $content ?>
                <?php else: ?>
                    <div class="container-fluid py-4">
                        <?php if (isset($viewFile)): ?>
                            <?php include $viewFile; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>

    <!-- Base URL for JavaScript -->
    <script>
        const BASE_URL = '<?= rtrim(base_url(), '/') ?>/';
    </script>

    <!-- Custom JS -->
    <script src="<?= base_url('js/app.js') ?>"></script>

    <?php if (isset($additionalJS)): ?>
        <?php foreach ($additionalJS as $js): ?>
            <script src="<?= htmlspecialchars($js) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>

</html>