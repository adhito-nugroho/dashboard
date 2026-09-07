<?php
/**
 * View: Kalibrasi Cetak Kuitansi (admin saja).
 * Variabel: $kalibrasi ['offset_x_mm','offset_y_mm','updated_at','updated_by'], $flash, $flashType
 */
$kalibrasi = $kalibrasi ?? ['offset_x_mm' => 0, 'offset_y_mm' => 0, 'updated_at' => null, 'updated_by' => null];
$flash = $flash ?? null;
$flashType = $flashType ?? 'info';
$ox = (float) ($kalibrasi['offset_x_mm'] ?? 0);
$oy = (float) ($kalibrasi['offset_y_mm'] ?? 0);
?>
<div class="container-fluid py-4" style="max-width:760px;">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item active">Kalibrasi Cetak Kuitansi</li>
        </ol>
    </nav>

    <h4 class="fw-bold mb-1"><i class="bi bi-printer text-primary me-2"></i>Kalibrasi Cetak Kuitansi</h4>
    <p class="text-muted mb-4" style="font-size:.875rem;">
        Kertas NCR pra-cetak 215&thinsp;mm &times; 165&thinsp;mm (landscape), satu template
        "KWITANSI" a.n. Kepala Dinas Kehutanan Prov. Jatim.
        Offset digeser ke <strong>semua elemen</strong> sekaligus. Kalibrasi sekali pasang di printer ini.
    </p>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : ($flashType === 'success' ? 'success' : 'info') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flash) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <form method="POST" action="<?= base_url('kuitansi/kalibrasi/simpan') ?>" id="formKalibrasi">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="offset_x_mm" class="form-label fw-semibold">Offset X (mm, + kanan / − kiri)</label>
                        <input type="number" class="form-control" id="offset_x_mm" name="offset_x_mm"
                               step="0.5" min="-50" max="50" value="<?= htmlspecialchars(number_format($ox, 2, '.', '')) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="offset_y_mm" class="form-label fw-semibold">Offset Y (mm, + bawah / − atas)</label>
                        <input type="number" class="form-control" id="offset_y_mm" name="offset_y_mm"
                               step="0.5" min="-50" max="50" value="<?= htmlspecialchars(number_format($oy, 2, '.', '')) ?>" required>
                    </div>
                </div>
                <div class="form-text mt-2">
                    Terakhir diubah: <?= $kalibrasi['updated_at'] ? htmlspecialchars($kalibrasi['updated_at']) : '-' ?>
                    <?= $kalibrasi['updated_by'] ? ' oleh ' . htmlspecialchars((string) $kalibrasi['updated_by']) : '' ?>
                </div>
                <div class="d-flex gap-2 mt-3 flex-wrap">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Simpan
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btnCetakUji">
                        <i class="bi bi-crosshair me-1"></i>Cetak Uji
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4" style="font-size:.875rem;">
            <h6 class="fw-bold mb-2"><i class="bi bi-info-circle me-1"></i>Cara kalibrasi</h6>
            <ol class="mb-0 ps-3">
                <li>Klik <strong>Cetak Uji</strong> — memakai nilai offset yang sedang diketik (belum tersimpan).</li>
                <li>Cetak PDF uji ke atas <strong>kertas NCR contoh</strong> (ukuran 215&times;165&thinsp;mm, landscape, skala 100%).</li>
                <li>Ukur selisih posisi crosshair terhadap kotak fisik (mm). Geser X untuk kiri-kanan, Y untuk atas-bawah.</li>
                <li>Masukkan selisih ke form, ulangi Cetak Uji sampai pas, lalu klik <strong>Simpan</strong>.</li>
            </ol>
        </div>
    </div>
</div>

<script>
document.getElementById('btnCetakUji')?.addEventListener('click', function() {
    const ox = document.getElementById('offset_x_mm')?.value || '0';
    const oy = document.getElementById('offset_y_mm')?.value || '0';
    const url = '<?= rtrim(base_url('kuitansi/kalibrasi/uji'), '/') ?>'
        + '?ox=' + encodeURIComponent(ox) + '&oy=' + encodeURIComponent(oy);
    window.open(url, '_blank');
});
</script>
