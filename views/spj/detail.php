<?php
/**
 * View: SPJ — Detail Surat Tugas (daftar pegawai + status rincian biaya)
 * Variabel dari controller: $suratTugas, $pegawaiList, $rincianAda, $stError
 */
$suratTugas  = $suratTugas  ?? null;
$pegawaiList = $pegawaiList ?? [];
$rincianAda  = $rincianAda  ?? [];   // ['nip' => row header rincian_biaya]
$stError     = $stError     ?? null;

$flash     = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>

<div class="container-fluid py-4">

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : ($flashType === 'success' ? 'success' : 'info') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flash) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url('spj') ?>"><i class="bi bi-briefcase me-1"></i>SPJ</a></li>
            <li class="breadcrumb-item active">Detail Surat Tugas</li>
        </ol>
    </nav>

    <!-- Header ST -->
    <?php if ($suratTugas): ?>
        <div class="card border-0 shadow-sm mb-4" style="border-left:4px solid #4f46e5 !important;">
            <div class="card-body py-3">
                <div class="row g-2">
                    <div class="col-md-6">
                        <div class="text-muted" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;">Nomor Surat Tugas</div>
                        <div class="fw-bold" style="font-size:1rem;"><?= htmlspecialchars($suratTugas['nomor_surat']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;">Tanggal Pelaksanaan</div>
                        <div class="fw-semibold">
                            <?= $suratTugas['tanggal_mulai'] ? date('d/m/Y', strtotime($suratTugas['tanggal_mulai'])) : '-' ?>
                            <?php if (!empty($suratTugas['tanggal_selesai']) && $suratTugas['tanggal_selesai'] !== $suratTugas['tanggal_mulai']): ?>
                                &mdash; <?= date('d/m/Y', strtotime($suratTugas['tanggal_selesai'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;">Tujuan / Untuk</div>
                        <div style="font-size:.875rem;"><?= htmlspecialchars($suratTugas['untuk'] ?? '-') ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($stError): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($stError) ?></div>
    <?php endif; ?>

    <!-- Judul -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0"><i class="bi bi-people text-primary me-2"></i>Daftar Pegawai Surat Tugas</h5>
        <a href="<?= base_url('spj') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>

    <?php if (empty($pegawaiList)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-person-x" style="font-size:2.5rem;"></i>
                <p class="mt-2 mb-0">Tidak ada pegawai dalam Surat Tugas ini.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="4%">No</th>
                                <th width="14%">NIP</th>
                                <th>Nama</th>
                                <th width="16%">Pangkat / Jabatan</th>
                                <th width="14%" class="text-center">Status Rincian</th>
                                <th width="15%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pegawaiList as $i => $peg):
                                $nipKey     = trim($peg['nip'] ?? $peg['pt_nip'] ?? '');
                                $rincian    = $rincianAda[$nipKey] ?? null;
                                $sudahAda   = $rincian !== null;
                                $stId       = $suratTugas['id'] ?? 0;
                                $nipEncoded = urlencode($nipKey);
                            ?>
                                <tr>
                                    <td class="text-muted" style="font-size:.8rem;"><?= $i + 1 ?></td>
                                    <td style="font-size:.8rem;font-family:monospace;"><?= htmlspecialchars($nipKey) ?></td>
                                    <td>
                                        <span class="fw-semibold" style="font-size:.875rem;"><?= htmlspecialchars($peg['nama']) ?></span>
                                    </td>
                                    <td style="font-size:.8rem;">
                                        <?= htmlspecialchars($peg['pangkat'] ?? '-') ?>
                                        <?php if (!empty($peg['jabatan'])): ?>
                                            <div class="text-muted"><?= htmlspecialchars($peg['jabatan']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($sudahAda): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Sudah Diisi</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-hourglass me-1"></i>Belum Diisi</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($sudahAda): ?>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="<?= base_url('spj/edit/' . $rincian['id']) ?>"
                                                   class="btn btn-outline-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button"
                                                        class="btn btn-outline-danger btn-hapus-rincian"
                                                        data-id="<?= $rincian['id'] ?>"
                                                        data-nama="<?= htmlspecialchars($peg['nama']) ?>"
                                                        title="Hapus">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <a href="<?= base_url('spj/create/' . $stId . '/' . $nipEncoded) ?>"
                                               class="btn btn-sm btn-primary">
                                                <i class="bi bi-plus-circle me-1"></i>Isi Rincian
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- Form hapus (hidden, disubmit via JS) -->
<form id="formHapus" method="POST" action="" style="display:none;">
    <input type="hidden" name="_method" value="DELETE">
</form>

<script>
document.querySelectorAll('.btn-hapus-rincian').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var id   = btn.dataset.id;
        var nama = btn.dataset.nama;
        if (!confirm('Hapus rincian biaya untuk ' + nama + '?\nData tidak dapat dipulihkan.')) return;
        var form   = document.getElementById('formHapus');
        form.action = '<?= rtrim(base_url(), '/') ?>/spj/delete/' + id;
        form.submit();
    });
});
</script>
