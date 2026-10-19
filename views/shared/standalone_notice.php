<?php
/**
 * Halaman notifikasi mandiri (tanpa layout) — dipakai untuk respons seperti
 * "Kota Tujuan belum diisi" atau error lain yang tidak perlu menu navigasi.
 *
 * Variabel yang diharapkan (semua sudah di-escape oleh pemanggil):
 * @var string      $noticeTitle    Judul notifikasi.
 * @var string      $noticeMessage  Isi pesan (HTML ter-escape).
 * @var string      $noticeVariant  warning|danger|info (ikon + warna).
 * @var string      $noticeHint     Kotak saran tambahan (HTML ter-escape), boleh ''.
 * @var string      $noticeBackUrl  URL tombol kembali.
 * @var string      $noticeBackLabel Label tombol kembali.
 */
$noticeVariant   = $noticeVariant ?? 'warning';
$noticeTitle     = $noticeTitle ?? 'Perhatian';
$noticeMessage   = $noticeMessage ?? '';
$noticeHint      = $noticeHint ?? '';
$noticeBackUrl   = $noticeBackUrl ?? base_url('');
$noticeBackLabel = $noticeBackLabel ?? 'Kembali';

$variantMap = [
    'warning' => ['class' => 'text-warning', 'icon' => 'bi-exclamation-triangle-fill'],
    'danger'  => ['class' => 'text-danger',  'icon' => 'bi-x-octagon-fill'],
    'info'    => ['class' => 'text-info',    'icon' => 'bi-info-circle-fill'],
];
$v = $variantMap[$noticeVariant] ?? $variantMap['warning'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($noticeTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container py-5" style="max-width:640px;">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 text-center">
                <div class="mb-3 <?= $v['class'] ?>" style="font-size:3rem;line-height:1;">
                    <i class="bi <?= $v['icon'] ?>"></i>
                </div>
                <h4 class="fw-bold mb-2"><?= htmlspecialchars($noticeTitle, ENT_QUOTES, 'UTF-8') ?></h4>
                <p class="text-muted mb-4"><?= $noticeMessage ?></p>
                <?php if ($noticeHint !== ''): ?>
                <div class="alert alert-<?= htmlspecialchars($noticeVariant, ENT_QUOTES, 'UTF-8') ?> text-start small mb-4">
                    <?= $noticeHint ?>
                </div>
                <?php endif; ?>
                <a href="<?= htmlspecialchars($noticeBackUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">
                    <i class="bi bi-arrow-left me-1"></i><?= htmlspecialchars($noticeBackLabel, ENT_QUOTES, 'UTF-8') ?>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
