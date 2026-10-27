<?php
/**
 * View: Tambah Uang (TU) — Sisi Seksi
 * Variabel: $items, $tuAktif, $status
 */
$items   = $items   ?? [];
$tuAktif = $tuAktif ?? [];
$status  = $status  ?? '';

$namaBulanMap = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                 7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];

$statusBadge = [
    'diajukan'  => ['warning',  'Menunggu Persetujuan'],
    'disetujui' => ['info',     'Disetujui — Menunggu SP2D'],
    'cair'      => ['success',  'Dana Cair — Aktif'],
    'selesai'   => ['secondary','Selesai'],
    'ditolak'   => ['danger',   'Ditolak'],
];

$flash     = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>

<div class="container-fluid py-4">

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : ($flashType === 'success' ? 'success' : 'info') ?> alert-dismissible fade show">
            <?= htmlspecialchars($flash) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="mb-1 fw-bold"><i class="bi bi-plus-circle-dotted text-primary me-2"></i>Tambah Uang (TU)</h3>
            <p class="text-muted mb-0">Ajukan kebutuhan Tambah Uang untuk kegiatan seksi Anda</p>
        </div>
        <a href="<?= base_url('seksi/tu/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Ajukan TU Baru
        </a>
    </div>

    <!-- Panel Dana TU Aktif -->
    <?php if (!empty($tuAktif)): ?>
    <div class="mb-4">
        <?php foreach ($tuAktif as $ak):
            $cair = (float)($ak['nominal_cair'] ?? 0);
            $terpakai = (float)($ak['nominal_terpakai'] ?? 0);
            $sisa = max(0, $cair - $terpakai);
            $pct  = $cair > 0 ? min(100, round($terpakai / $cair * 100)) : 0;
            $jatuhTempo = !empty($ak['batas_spj']) && $ak['batas_spj'] < date('Y-m-d');
        ?>
        <div class="card border-0 shadow-sm mb-3" style="border-left: 4px solid #2563eb !important;">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="fw-bold text-primary"><?= htmlspecialchars($ak['nomor_pengajuan']) ?></div>
                        <div class="text-muted" style="font-size:.82rem;"><?= htmlspecialchars($ak['keperluan']) ?></div>
                        <div class="mt-1 d-flex gap-3 flex-wrap" style="font-size:.82rem;">
                            <span>Dana Cair: <strong>Rp <?= number_format($cair, 0, ',', '.') ?></strong></span>
                            <span>Terpakai: <strong class="text-primary">Rp <?= number_format($terpakai, 0, ',', '.') ?></strong></span>
                            <span class="<?= $sisa > 0 ? 'text-success' : '' ?>">Sisa: <strong>Rp <?= number_format($sisa, 0, ',', '.') ?></strong></span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="<?= $jatuhTempo ? 'text-danger fw-bold' : 'text-muted' ?>" style="font-size:.8rem;">
                            Batas SPJ: <?= $ak['batas_spj'] ? date('d/m/Y', strtotime($ak['batas_spj'])) : '-' ?>
                            <?= $jatuhTempo ? '<br><span class="badge bg-danger">JATUH TEMPO!</span>' : '' ?>
                        </div>
                    </div>
                </div>
                <div class="progress mt-2" style="height:8px;">
                    <div class="progress-bar bg-<?= $pct >= 100 ? 'success' : 'primary' ?>" style="width:<?= $pct ?>%"></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Filter pill status -->
    <div class="mb-3 d-flex gap-2 flex-wrap">
        <?php $pills = ['' => 'Semua'] + array_map(fn($v) => $v[1], $statusBadge); ?>
        <?php foreach ($pills as $k => $label): ?>
            <a href="<?= base_url('seksi/tu?status=' . urlencode($k)) ?>"
               class="btn btn-sm <?= $status === $k ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <?= htmlspecialchars($label) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Tabel pengajuan TU -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($items)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox" style="font-size:2rem;"></i>
                    <p class="mt-2 mb-0">Belum ada pengajuan TU<?= $status ? ' dengan status ini' : '' ?>.</p>
                    <a href="<?= base_url('seksi/tu/create') ?>" class="btn btn-primary btn-sm mt-3">
                        <i class="bi bi-plus-circle me-1"></i>Ajukan TU Sekarang
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:.875rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Nomor TU</th>
                                <th>Periode</th>
                                <th>Keperluan</th>
                                <th class="text-end">Kebutuhan (Rp)</th>
                                <th class="text-end">Cair (Rp)</th>
                                <th>Batas SPJ</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item):
                                $bInfo = $statusBadge[$item['status']] ?? ['secondary', $item['status']];
                                $jatuhTempo = $item['status'] === 'cair' && !empty($item['batas_spj']) && $item['batas_spj'] < date('Y-m-d');
                            ?>
                                <tr class="<?= $jatuhTempo ? 'table-warning' : '' ?>">
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($item['nomor_pengajuan']) ?></div>
                                        <div class="text-muted" style="font-size:.75rem;"><?= date('d/m/Y', strtotime($item['tanggal_pengajuan'])) ?></div>
                                    </td>
                                    <td><?= ($namaBulanMap[$item['bulan']] ?? $item['bulan']) . ' ' . $item['tahun'] ?></td>
                                    <td style="max-width:220px;"><div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($item['keperluan']) ?>"><?= htmlspecialchars($item['keperluan']) ?></div></td>
                                    <td class="text-end"><?= number_format((float)$item['kebutuhan'], 0, ',', '.') ?></td>
                                    <td class="text-end <?= (float)($item['nominal_cair'] ?? 0) > 0 ? 'fw-semibold text-primary' : 'text-muted' ?>">
                                        <?= (float)($item['nominal_cair'] ?? 0) > 0 ? number_format((float)$item['nominal_cair'], 0, ',', '.') : '—' ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['batas_spj'])): ?>
                                            <span class="<?= $jatuhTempo ? 'text-danger fw-bold' : '' ?>"><?= date('d/m/Y', strtotime($item['batas_spj'])) ?></span>
                                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                    </td>
                                    <td class="text-center"><span class="badge bg-<?= $bInfo[0] ?>" style="font-size:.78rem;"><?= $bInfo[1] ?></span></td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($item['status'] === 'diajukan'): ?>
                                                <a href="<?= base_url('seksi/tu/edit/' . $item['id']) ?>" class="btn btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                                <form method="POST" action="<?= base_url('seksi/tu/delete/' . $item['id']) ?>" style="display:inline;" onsubmit="return confirm('Hapus pengajuan TU ini?')">
                                                    <button type="submit" class="btn btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                                </form>
                                            <?php else: ?>
                                                <span class="btn btn-outline-secondary disabled btn-sm" style="font-size:.75rem;"><i class="bi bi-lock"></i> Terkunci</span>
                                            <?php endif; ?>
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
</div>
