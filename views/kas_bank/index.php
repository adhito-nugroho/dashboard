<?php
$namaBulanMap = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$bulanTampil = $namaBulanMap[$bulan] ?? (string)$bulan;
?>

<div class="container-fluid px-4 py-3">
    <!-- Header Title & Filter -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="bi bi-bank text-primary me-2"></i>Buku Pembantu Kas & Monitoring UP/GU
            </h1>
            <p class="text-muted small mb-0">
                Pencatatan saldo kas/bank, perputaran Uang Persediaan (UP), dan pencairan Ganti Uang (GU) periode <strong class="text-dark"><?= $bulanTampil ?> <?= $tahun ?></strong>.
            </p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <form method="GET" action="<?= base_url('kas-bank') ?>" class="d-flex align-items-center gap-2 bg-white p-1 rounded-3 border shadow-sm">
                <select name="bulan" class="form-select form-select-sm border-0 bg-light" style="width: auto;">
                    <?php foreach ($namaBulanMap as $m => $nama): ?>
                        <option value="<?= $m ?>" <?= $m === $bulan ? 'selected' : '' ?>><?= $nama ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="tahun" class="form-select form-select-sm border-0 bg-light" style="width: auto;">
                    <?php for ($y = date('Y') + 1; $y >= 2024; $y--): ?>
                        <option value="<?= $y ?>" <?= $y === $tahun ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-primary px-3">
                    <i class="bi bi-filter me-1"></i>Tampilkan
                </button>
            </form>

            <button type="button" class="btn btn-sm btn-success px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahKas">
                <i class="bi bi-plus-circle me-1"></i>Catat GU / Penerimaan
            </button>
        </div>
    </div>

    <!-- 4 Metrik Kartu Saldo & UP/GU -->
    <div class="row g-3 mb-4">
        <!-- Saldo Kas/Bank Saat Ini -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background: linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%); color: #fff;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-semibold" style="font-size:0.75rem; letter-spacing:0.05em; color: #93c5fd;">
                        Saldo Kas/Bank Riil
                    </span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1" style="font-size:0.7rem;">
                        Tersedia
                    </span>
                </div>
                <div class="h3 fw-bold mb-1 font-monospace text-white">
                    Rp <?= number_format($ringkasan['saldo_kas_saat_ini'], 0, ',', '.') ?>
                </div>
                <div class="small mt-auto text-light opacity-75" style="font-size:0.75rem;">
                    <i class="bi bi-shield-check me-1"></i>Dana siap pakai per saat ini
                </div>
            </div>
        </div>

        <!-- Plafond UP Kantor -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-semibold text-muted" style="font-size:0.75rem; letter-spacing:0.05em;">
                        Plafond Uang Persediaan (UP)
                    </span>
                    <div class="p-2 rounded-circle bg-light text-secondary">
                        <i class="bi bi-building"></i>
                    </div>
                </div>
                <div class="h3 fw-bold mb-1 font-monospace text-dark">
                    Rp <?= number_format($ringkasan['plafond_up'], 0, ',', '.') ?>
                </div>
                <div class="small mt-auto text-muted" style="font-size:0.75rem;">
                    Batas pagu revolving fund kas kantor
                </div>
            </div>
        </div>

        <!-- Total Pengeluaran Bulan Ini (Diverifikasi) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-semibold text-danger" style="font-size:0.75rem; letter-spacing:0.05em;">
                        Pengeluaran (Diverifikasi)
                    </span>
                    <div class="p-2 rounded-circle bg-danger-subtle text-danger">
                        <i class="bi bi-arrow-down-right-circle"></i>
                    </div>
                </div>
                <div class="h3 fw-bold mb-1 font-monospace text-danger">
                    Rp <?= number_format($ringkasan['total_pengeluaran_diverifikasi'], 0, ',', '.') ?>
                </div>
                <div class="small mt-auto text-muted" style="font-size:0.75rem;">
                    <?= count($belanjaList) ?> transaksi telah diverifikasi
                </div>
            </div>
        </div>

        <!-- Estimasi GU Menunggu Cair / Siap Diajukan -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background: #fffbeb; border: 1px solid #fde68a !important;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-semibold text-warning-emphasis" style="font-size:0.75rem; letter-spacing:0.05em;">
                        Estimasi GU Belum Cair
                    </span>
                    <div class="p-2 rounded-circle bg-warning-subtle text-warning-emphasis">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
                <div class="h3 fw-bold mb-1 font-monospace text-warning-emphasis">
                    Rp <?= number_format($ringkasan['estimasi_gu_cair'], 0, ',', '.') ?>
                </div>
                <div class="small mt-auto text-warning-emphasis" style="font-size:0.75rem;">
                    Plafond UP dikurangi sisa saldo kas
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Rincian Mutasi Kas Masuk & Rincian Pengeluaran -->
    <div class="row g-4">
        <!-- Kolom Kiri: Riwayat Penerimaan Kas / Pencairan GU -->
        <div class="col-12 col-xl-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
                    <div>
                        <h5 class="card-title fw-bold mb-0 text-dark">
                            <i class="bi bi-arrow-down-left-circle text-success me-2"></i>Penerimaan Kas & Pencairan GU
                        </h5>
                        <small class="text-muted">Daftar saldo awal dan pencairan dana UP/GU pada periode ini</small>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 font-monospace">
                        Total: Rp <?= number_format($ringkasan['total_penerimaan'], 0, ',', '.') ?>
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size:0.875rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4" style="width: 120px;">Tanggal</th>
                                    <th>Jenis</th>
                                    <th>No. Bukti / SP2D</th>
                                    <th>Keterangan</th>
                                    <th class="text-end" style="width: 150px;">Nominal</th>
                                    <th class="text-center pe-4" style="width: 80px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($mutasiList)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox fs-3 d-block mb-1 text-secondary opacity-50"></i>
                                            Belum ada catatan mutasi penerimaan di bulan ini.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($mutasiList as $m): ?>
                                        <?php
                                        $badgeJenis = [
                                            'saldo_awal' => ['Saldo Awal', 'bg-info-subtle text-info-emphasis border-info-subtle'],
                                            'up'         => ['Pencairan UP', 'bg-primary-subtle text-primary border-primary-subtle'],
                                            'gu'         => ['Pencairan GU', 'bg-success-subtle text-success border-success-subtle'],
                                            'setoran'    => ['Setoran Kas', 'bg-warning-subtle text-warning-emphasis border-warning-subtle'],
                                            'lainnya'    => ['Lainnya', 'bg-secondary-subtle text-secondary border-secondary-subtle'],
                                        ];
                                        $jInfo = $badgeJenis[$m['jenis']] ?? ['Penerimaan', 'bg-light text-dark'];
                                        ?>
                                        <tr>
                                            <td class="ps-4 fw-semibold text-dark font-monospace" style="font-size:0.8rem;">
                                                <?= date('d/m/Y', strtotime($m['tanggal'])) ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= $jInfo[1] ?> border px-2 py-1" style="font-size:0.7rem;">
                                                    <?= $jInfo[0] ?>
                                                </span>
                                            </td>
                                            <td class="font-monospace text-xs text-muted">
                                                <?= htmlspecialchars($m['nomor_bukti'] ?: '-') ?>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars($m['keterangan']) ?></div>
                                                <?php if (!empty($m['created_by_name'])): ?>
                                                    <small class="text-muted text-xs">Oleh: <?= htmlspecialchars($m['created_by_name']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end font-monospace fw-bold text-success">
                                                + Rp <?= number_format((float)$m['nominal'], 0, ',', '.') ?>
                                            </td>
                                            <td class="text-center pe-4">
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-light p-1 rounded-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="bi bi-three-dots-vertical"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="font-size:0.85rem;">
                                                        <li>
                                                            <button class="dropdown-item btn-edit-kas" 
                                                                    data-id="<?= $m['id'] ?>"
                                                                    data-tanggal="<?= $m['tanggal'] ?>"
                                                                    data-jenis="<?= $m['jenis'] ?>"
                                                                    data-nomor="<?= htmlspecialchars($m['nomor_bukti'] ?? '') ?>"
                                                                    data-keterangan="<?= htmlspecialchars($m['keterangan']) ?>"
                                                                    data-nominal="<?= (float)$m['nominal'] ?>">
                                                                <i class="bi bi-pencil me-2 text-warning"></i>Edit
                                                            </button>
                                                        </li>
                                                        <li>
                                                            <form method="POST" action="<?= base_url('kas-bank/delete/' . $m['id']) ?>" onsubmit="return confirm('Hapus catatan mutasi kas ini?')">
                                                                <button type="submit" class="dropdown-item text-danger">
                                                                    <i class="bi bi-trash me-2"></i>Hapus
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Ringkasan Pengeluaran Belanja (Diverifikasi) -->
        <div class="col-12 col-xl-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
                    <div>
                        <h5 class="card-title fw-bold mb-0 text-dark">
                            <i class="bi bi-receipt text-danger me-2"></i>Pengeluaran Terverifikasi
                        </h5>
                        <small class="text-muted">Transaksi belanja yang telah memotong saldo kas</small>
                    </div>
                    <a href="<?= base_url('transaksi?status=diverifikasi&tahun=' . $tahun) ?>" class="btn btn-sm btn-outline-primary" style="font-size:0.75rem;">
                        Lihat Semua
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 480px;">
                        <table class="table table-hover align-middle mb-0" style="font-size:0.825rem;">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th class="ps-3">No. Bukti / Tanggal</th>
                                    <th>Uraian & Seksi</th>
                                    <th class="text-end pe-3">Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($belanjaList)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">
                                            Belum ada pengeluaran terverifikasi pada periode ini.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($belanjaList as $b): ?>
                                        <tr>
                                            <td class="ps-3">
                                                <div class="fw-bold font-monospace text-dark text-xs"><?= htmlspecialchars($b['nomor_bukti'] ?: '-') ?></div>
                                                <small class="text-muted" style="font-size:0.7rem;"><?= date('d/m/Y', strtotime($b['tanggal'])) ?></small>
                                            </td>
                                            <td>
                                                <div class="text-truncate text-dark fw-medium" style="max-width: 180px;" title="<?= htmlspecialchars($b['uraian']) ?>">
                                                    <?= htmlspecialchars($b['uraian']) ?>
                                                </div>
                                                <small class="badge bg-light text-secondary border px-1.5 py-0.5" style="font-size:0.65rem;">
                                                    <?= htmlspecialchars($b['nama_seksi'] ?? '-') ?>
                                                </small>
                                            </td>
                                            <td class="text-end pe-3 font-monospace fw-bold text-danger">
                                                - Rp <?= number_format((float)$b['nilai'], 0, ',', '.') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="small text-muted">Total Pengeluaran Bulan Ini:</span>
                    <strong class="font-monospace text-danger">Rp <?= number_format($ringkasan['total_pengeluaran_diverifikasi'], 0, ',', '.') ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH PENERIMAAN / PENCAIRAN GU -->
<div class="modal fade" id="modalTambahKas" tabindex="-1" aria-labelledby="modalTambahKasLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white py-3 px-4 rounded-top-4">
                <h5 class="modal-title fw-bold fs-6" id="modalTambahKasLabel">
                    <i class="bi bi-plus-circle me-2"></i>Catat Pencairan GU / Penerimaan Kas
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= base_url('kas-bank/store') ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Tanggal Penerimaan / Cair <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Jenis Mutasi <span class="text-danger">*</span></label>
                        <select name="jenis" class="form-select" required>
                            <option value="gu" selected>Pencairan GU (Ganti Uang)</option>
                            <option value="saldo_awal">Saldo Awal Bulan</option>
                            <option value="up">Pencairan UP (Uang Persediaan Awal Tahun)</option>
                            <option value="setoran">Setoran Kas / Pengembalian</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Nomor Bukti / SP2D / Ref Bank</label>
                        <input type="text" name="nomor_bukti" class="form-control" placeholder="Contoh: SP2D-GU/09/2026 atau Bukti Transfer">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Keterangan <span class="text-danger">*</span></label>
                        <textarea name="keterangan" class="form-control" rows="2" placeholder="Contoh: Pencairan Dana GU Bulan September 2026" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Nominal (Rp) <span class="text-danger">*</span></label>
                        <input type="text" name="nominal" class="form-control font-monospace fs-5 fw-bold text-success input-currency" placeholder="0" required>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3 rounded-bottom-4">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">
                        <i class="bi bi-save me-1"></i>Simpan Mutasi Kas
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDIT MUTASI KAS -->
<div class="modal fade" id="modalEditKas" tabindex="-1" aria-labelledby="modalEditKasLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-warning text-dark py-3 px-4 rounded-top-4">
                <h5 class="modal-title fw-bold fs-6" id="modalEditKasLabel">
                    <i class="bi bi-pencil-square me-2"></i>Edit Mutasi Kas & Bank
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formEditKas" method="POST" action="">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Tanggal Penerimaan / Cair <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal" id="editTanggal" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Jenis Mutasi <span class="text-danger">*</span></label>
                        <select name="jenis" id="editJenis" class="form-select" required>
                            <option value="gu">Pencairan GU (Ganti Uang)</option>
                            <option value="saldo_awal">Saldo Awal Bulan</option>
                            <option value="up">Pencairan UP (Uang Persediaan Awal Tahun)</option>
                            <option value="setoran">Setoran Kas / Pengembalian</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Nomor Bukti / SP2D / Ref Bank</label>
                        <input type="text" name="nomor_bukti" id="editNomorBukti" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Keterangan <span class="text-danger">*</span></label>
                        <textarea name="keterangan" id="editKeterangan" class="form-control" rows="2" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Nominal (Rp) <span class="text-danger">*</span></label>
                        <input type="text" name="nominal" id="editNominal" class="form-control font-monospace fs-5 fw-bold text-success input-currency" required>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3 rounded-bottom-4">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning btn-sm px-4 fw-semibold">
                        <i class="bi bi-save me-1"></i>Perbarui Mutasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Format input currency ribuan
    const currencyInputs = document.querySelectorAll('.input-currency');
    currencyInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let val = this.value.replace(/\D/g, '');
            if (val === '') {
                this.value = '';
                return;
            }
            this.value = new Intl.NumberFormat('id-ID').format(val);
        });
    });

    // Modal Edit handler
    const modalEditEl = document.getElementById('modalEditKas');
    const formEdit = document.getElementById('formEditKas');
    const editTanggal = document.getElementById('editTanggal');
    const editJenis = document.getElementById('editJenis');
    const editNomorBukti = document.getElementById('editNomorBukti');
    const editKeterangan = document.getElementById('editKeterangan');
    const editNominal = document.getElementById('editNominal');

    document.querySelectorAll('.btn-edit-kas').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const tgl = this.getAttribute('data-tanggal');
            const jenis = this.getAttribute('data-jenis');
            const nomor = this.getAttribute('data-nomor');
            const ket = this.getAttribute('data-keterangan');
            const nominal = parseFloat(this.getAttribute('data-nominal') || 0);

            formEdit.action = `<?= base_url('kas-bank/update/') ?>${id}`;
            editTanggal.value = tgl;
            editJenis.value = jenis;
            editNomorBukti.value = nomor;
            editKeterangan.value = ket;
            editNominal.value = new Intl.NumberFormat('id-ID').format(nominal);

            const modal = new bootstrap.Modal(modalEditEl);
            modal.show();
        });
    });
});
</script>
