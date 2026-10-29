<?php
/**
 * View: SPJ — Form cetak lembar 2 SPPD (overlay A4)
 *
 * Variabel dari controller:
 *   $values      : isian otomatis (kota tujuan, tanggal tiba/berangkat, tujuan kembali)
 *   $pejabat     : ['jabatan','nama','nip'] pejabat instansi tujuan (tersimpan per ST)
 *   $suratTugas  : header ST dari db_surat_tugas
 *   $pegawai     : data pegawai ST
 */
$values     = $values     ?? [];
$pejabat    = $pejabat    ?? ['jabatan' => '', 'nama' => '', 'nip' => ''];
$suratTugas = $suratTugas ?? null;
$pegawai    = $pegawai    ?? [];

$stId   = (int) ($suratTugas['id'] ?? 0);
$nipKey = (string) ($values['pegawai_nip'] ?? ($pegawai['nip'] ?? ''));
$formAction = base_url('spj/sppd2/cetak/' . $stId . '/' . urlencode($nipKey));

$flash     = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>

<style>
.sppd2-card { border:1px solid #e2e8f0; border-radius:14px; background:#fff; box-shadow:0 1px 4px rgba(0,0,0,.04); margin-bottom:1.5rem; overflow:hidden; }
.sppd2-card-header { background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:.85rem 1.25rem; }
.sppd2-card-header h5 { margin:0; font-size:.95rem; font-weight:700; color:#1e293b; }
.sppd2-card-body { padding:1.25rem; }
.sppd2-label { font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; color:#64748b; margin-bottom:.25rem; }
.sppd2-value { font-size:.9rem; font-weight:600; color:#1e293b; }
.sppd2-input { border-radius:8px; border:1px solid #cbd5e1; font-size:.875rem; padding:.5rem .75rem; }
.sppd2-input:focus { border-color:#4f46e5; box-shadow:0 0 0 3px rgba(79,70,229,.12); outline:none; }
</style>

<div class="container-fluid py-4">

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : ($flashType === 'success' ? 'success' : 'info') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flash) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url('spj') ?>"><i class="bi bi-briefcase me-1"></i>SPJ</a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('spj/detail/' . $stId) ?>">Detail Surat Tugas</a></li>
            <li class="breadcrumb-item active">Cetak Lembar 2 SPPD</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0"><i class="bi bi-file-earmark-text text-danger me-2"></i>Cetak Lembar 2 SPPD</h5>
        <div class="d-flex gap-2">
            <a href="<?= base_url('spj/sppd2/kalibrasi') ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-sliders me-1"></i>Kalibrasi Posisi
            </a>
            <a href="<?= base_url('spj/sppd2/uji') ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-crosshair me-1"></i>Cetak Uji Posisi
            </a>
            <a href="<?= base_url('spj/detail/' . $stId) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Kembali
            </a>
        </div>
    </div>

    <div class="alert alert-info d-flex align-items-start gap-2 py-2" role="alert">
        <i class="bi bi-info-circle-fill"></i>
        <div class="small">
            PDF yang dihasilkan hanya berisi <strong>isian</strong> (tanpa garis tabel) untuk dicetak di atas
            blanko lembar 2 SPPD. Cetak <em>actual size / 100%</em> pada kertas A4.
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <form method="POST" action="<?= $formAction ?>">
                <div class="sppd2-card">
                    <div class="sppd2-card-header"><h5><i class="bi bi-magic me-1"></i>Terisi Otomatis dari Surat Tugas</h5></div>
                    <div class="sppd2-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="sppd2-label">Pegawai</div>
                                <div class="sppd2-value"><?= htmlspecialchars($values['pegawai_nama'] ?? '-') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="sppd2-label">Nomor Surat Tugas</div>
                                <div class="sppd2-value"><?= htmlspecialchars($values['nomor_surat'] ?? '-') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="sppd2-label">I. Berangkat dari (tempat kedudukan)</div>
                                <div class="sppd2-value"><?= htmlspecialchars($values['i_berangkat_dari'] ?? '-') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="sppd2-label">I. Ke</div>
                                <div class="sppd2-value"><?= htmlspecialchars($values['i_ke'] ?? '-') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="sppd2-label">I. Pada tanggal</div>
                                <div class="sppd2-value"><?= htmlspecialchars($values['i_tanggal'] ?? '-') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="sppd2-label">II. Tiba di</div>
                                <div class="sppd2-value"><?= htmlspecialchars($values['tiba_di'] ?? '-') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="sppd2-label">Pada tanggal (tiba)</div>
                                <div class="sppd2-value"><?= htmlspecialchars($values['tiba_tanggal'] ?? '-') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="sppd2-label">II. Berangkat dari</div>
                                <div class="sppd2-value"><?= htmlspecialchars($values['berangkat_dari'] ?? '-') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="sppd2-label">Ke</div>
                                <div class="sppd2-value"><?= htmlspecialchars($values['berangkat_ke'] ?? '-') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="sppd2-label">Pada tanggal (berangkat)</div>
                                <div class="sppd2-value"><?= htmlspecialchars($values['berangkat_tanggal'] ?? '-') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="sppd2-label">VI. Tiba di</div>
                                <div class="sppd2-value"><?= htmlspecialchars($values['vi_tiba_di'] ?? '-') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="sppd2-label">VI. Pada tanggal</div>
                                <div class="sppd2-value"><?= htmlspecialchars($values['vi_tiba_tanggal'] ?? '-') ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sppd2-card">
                    <div class="sppd2-card-header"><h5><i class="bi bi-person-badge me-1"></i>Pejabat Instansi Tujuan</h5></div>
                    <div class="sppd2-card-body">
                        <div class="mb-3">
                            <label class="form-label sppd2-label" for="jabatan">Jabatan</label>
                            <input type="text" class="form-control sppd2-input" id="jabatan" name="jabatan" maxlength="255"
                                   placeholder="mis. Kepala Kantor Regional II BKN"
                                   value="<?= htmlspecialchars($pejabat['jabatan'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label sppd2-label" for="nama">Nama Pejabat</label>
                            <input type="text" class="form-control sppd2-input" id="nama" name="nama" maxlength="255"
                                   placeholder="Nama lengkap (dengan gelar bila ada)"
                                   value="<?= htmlspecialchars($pejabat['nama'] ?? '') ?>" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label sppd2-label" for="nip">NIP</label>
                            <input type="text" class="form-control sppd2-input" id="nip" name="nip" maxlength="30"
                                   inputmode="numeric" placeholder="18 digit NIP"
                                   value="<?= htmlspecialchars($pejabat['nip'] ?? '') ?>" required>
                        </div>
                        <div class="form-text">
                            Nama &amp; NIP dicetak pada kedua kolom stempel (Tiba di dan Berangkat dari),
                            lalu data ini disimpan untuk Surat Tugas ini.
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 mb-4">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-printer me-1"></i>Cetak Lembar 2 SPPD
                    </button>
                    <a href="<?= base_url('spj/detail/' . $stId) ?>" class="btn btn-outline-secondary">Batal</a>
                </div>
            </form>
        </div>

        <div class="col-lg-5">
            <div class="sppd2-card">
                <div class="sppd2-card-header"><h5><i class="bi bi-info-circle me-1"></i>Catatan</h5></div>
                <div class="sppd2-card-body small text-muted">
                    <ul class="mb-0 ps-3">
                        <li class="mb-2">Kertas: <strong>A4</strong>, cetak <em>actual size</em> (jangan <em>fit to page</em>).</li>
                        <li class="mb-2">Bagian I, II, dan VI terisi otomatis; bagian III–V, VII, dan VIII tidak diisi oleh aplikasi.</li>
                        <li class="mb-2">Jika posisi isian bergeser dari blanko, gunakan <strong>Cetak Uji Posisi</strong> lalu sesuaikan koordinat.</li>
                        <li>Pejabat yang tersimpan akan terisi otomatis saat membuka halaman ini lagi.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
