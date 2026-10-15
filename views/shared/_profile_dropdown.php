<?php
/**
 * Partial: Dropdown Profil Terpadu (dipakai layout admin & layout seksi).
 *
 * Variabel (semua opsional):
 *   $pdName     nama tampilan            (default $_SESSION['username'] ?? '')
 *   $pdRole     label peran              (default role session, ucfirst)
 *   $pdSub      info tambahan (seksi)    (default '')
 *   $pdVariant  'light' | 'dark'         (default 'light'; dark utk topbar hijau)
 *   $pdLoginUrl URL tombol login (mode tamu)
 *
 * Mode tamu (belum login): tampil tombol "Login Admin" seperti semula.
 */
$pdName = $pdName ?? ($_SESSION['username'] ?? '');
$pdRole = $pdRole ?? ucfirst((string) ($_SESSION['role'] ?? ''));
$pdSub = $pdSub ?? '';
$pdVariant = $pdVariant ?? 'light';
$pdLoginUrl = $pdLoginUrl ?? base_url('login');
$isDark = $pdVariant === 'dark';
$pdInitial = strtoupper(mb_substr(trim((string) $pdName) !== '' ? (string) $pdName : '?', 0, 1));
?>
<?php if (trim((string) $pdName) === ''): ?>
    <a href="<?= htmlspecialchars($pdLoginUrl) ?>" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-box-arrow-in-right"></i> Login Admin
    </a>
<?php else: ?>
    <div class="dropdown ux-profile">
        <button class="btn btn-sm ux-profile-chip <?= $isDark ? 'ux-profile-chip-dark' : '' ?>"
                type="button" id="uxProfileBtn" data-bs-toggle="dropdown" aria-expanded="false"
                title="<?= htmlspecialchars($pdName) ?>">
            <span class="ux-profile-avatar"><?= htmlspecialchars($pdInitial) ?></span>
            <span class="ux-profile-name"><?= htmlspecialchars($pdName) ?></span>
            <i class="bi bi-chevron-down ux-profile-caret"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end ux-profile-menu shadow" aria-labelledby="uxProfileBtn">
            <li class="px-3 pt-2 pb-1">
                <div class="fw-bold text-dark" style="font-size:.85rem;"><?= htmlspecialchars($pdName) ?></div>
                <div class="mt-1 d-flex flex-wrap gap-1">
                    <?php if ($pdRole !== ''): ?>
                        <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle"><?= htmlspecialchars($pdRole) ?></span>
                    <?php endif; ?>
                    <?php if ($pdSub !== ''): ?>
                        <span class="badge bg-light text-secondary border"><?= htmlspecialchars($pdSub) ?></span>
                    <?php endif; ?>
                </div>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item text-danger" href="<?= base_url('logout') ?>">
                    <i class="bi bi-box-arrow-right me-2"></i>Logout
                </a>
            </li>
        </ul>
    </div>
<?php endif; ?>
