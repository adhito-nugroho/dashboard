<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$bulanNames = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
$rakPersen = $stats['total_pagu'] > 0 ? ($stats['total_rak'] / $stats['total_pagu']) * 100 : 0;
$sisaPersen = $stats['total_pagu'] > 0 ? ($stats['sisa_anggaran'] / $stats['total_pagu']) * 100 : 0;
$bulanBerjalan = $stats['tahun'] == (int) date('Y') ? (int) date('n') : 12;
$bulanBerjalanDetail = $bulanBerjalan; // alias dipakai di tabel detail & chart JS
$targetRakBulanBerjalan = 0;
$serapanBulanBerjalan = 0;
for ($i = 1; $i <= $bulanBerjalan; $i++) {
    $targetRakBulanBerjalan += (float) ($monthlyData['rak'][$i] ?? 0);
    $serapanBulanBerjalan += (float) ($monthlyData['realisasi'][$i] ?? 0);
}
$capaianRakBulanBerjalan = $targetRakBulanBerjalan > 0 ? ($serapanBulanBerjalan / $targetRakBulanBerjalan) * 100 : 0;
$deviasiRakBulanBerjalan = $serapanBulanBerjalan - $targetRakBulanBerjalan;
$komparasiRakColor = $targetRakBulanBerjalan <= 0 ? 'secondary' : ($capaianRakBulanBerjalan > 110 ? 'primary' : ($capaianRakBulanBerjalan >= 90 ? 'success' : ($capaianRakBulanBerjalan >= 50 ? 'warning' : 'danger')));
?>

<link rel="stylesheet" href="<?= base_url('css/dashboard.css') ?>">
<?php include __DIR__ . '/partials/_sticky_nav.php'; ?><?php include __DIR__ . '/partials/_header.php'; ?><?php include __DIR__ . '/partials/_insight.php'; ?><?php include __DIR__ . '/partials/_filters.php'; ?><?php include __DIR__ . '/partials/_ringkasan.php'; ?><?php include __DIR__ . '/partials/_alerts.php'; ?><?php include __DIR__ . '/partials/_grafik_komposisi.php'; ?><?php include __DIR__ . '/partials/_detail_bulan.php'; ?><?php include __DIR__ . '/partials/_detail_tabs_open.php'; ?><?php include __DIR__ . '/partials/_tab_serapan.php'; ?><?php include __DIR__ . '/partials/_tab_semester.php'; ?><?php include __DIR__ . '/partials/_tab_struktur.php'; ?><?php include __DIR__ . '/partials/_tab_deviasi.php'; ?><?php include __DIR__ . '/partials/_detail_tabs_close.php'; ?><?php include __DIR__ . '/partials/_breakdown.php'; ?><?php include __DIR__ . '/partials/_export_modal.php'; ?><?php include __DIR__ . '/partials/_scripts.php'; ?>