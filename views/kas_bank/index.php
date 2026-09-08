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
                Pencatatan saldo kas/bank, pemantauan pencairan GU (Ganti Uang), dan akumulasi SPJ belanja periode <strong class="text-dark"><?= $bulanTampil ?> <?= $tahun ?></strong>.
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

    <!-- 4 Metrik Kartu Saldo, GU Menunggu Cair, Belanja SPJ, & Proyeksi Kas -->
    <div class="row g-3 mb-4">
        <!-- 1. Saldo Kas/Bank Riil Saat Ini -->
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
                    <i class="bi bi-wallet2 me-1"></i>Uang fisik/bank siap pakai saat ini
                </div>
            </div>
        </div>

        <!-- 2. Dana GU Menunggu Cair (SPJ Periode Lalu) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background: #fffbeb; border: 1px solid #fde68a !important;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-semibold text-warning-emphasis" style="font-size:0.75rem; letter-spacing:0.05em;">
                        GU Menunggu Cair (SPJ Lalu)
                    </span>
                    <div class="p-1.5 rounded-circle bg-warning-subtle text-warning-emphasis">
                        <i class="bi bi-hourglass-split fs-6"></i>
                    </div>
                </div>
                <div class="h3 fw-bold mb-1 font-monospace text-warning-emphasis">
                    Rp <?= number_format($ringkasan['gu_menunggu_cair'], 0, ',', '.') ?>
                </div>
                <div class="small mt-auto text-warning-emphasis" style="font-size:0.75rem;">
                    SPJ diajukan ke Kasda (sedang proses SP2D)
                </div>
            </div>
        </div>

        <!-- 3. Belanja Terverifikasi (Potensi Pengajuan GU Berikutnya) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-semibold text-danger" style="font-size:0.75rem; letter-spacing:0.05em;">
                        Belanja Bulan Ini (Potensi GU)
                    </span>
                    <div class="p-1.5 rounded-circle bg-danger-subtle text-danger">
                        <i class="bi bi-receipt fs-6"></i>
                    </div>
                </div>
                <div class="h3 fw-bold mb-1 font-monospace text-danger">
                    Rp <?= number_format($ringkasan['belanja_siap_gu'], 0, ',', '.') ?>
                </div>
                <div class="small mt-auto text-muted" style="font-size:0.75rem;">
                    <?= count($belanjaList) ?> transaksi diverifikasi (siap di-SPJ-kan)
                </div>
            </div>
        </div>

        <!-- 4. Proyeksi Kas Setelah GU Cair -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background: #f0fdf4; border: 1px solid #bbf7d0 !important;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase fw-semibold text-success" style="font-size:0.75rem; letter-spacing:0.05em;">
                        Proyeksi Kas Setelah GU Cair
                    </span>
                    <div class="p-1.5 rounded-circle bg-success-subtle text-success">
                        <i class="bi bi-graph-up-arrow fs-6"></i>
                    </div>
                </div>
                <div class="h3 fw-bold mb-1 font-monospace text-success">
                    Rp <?= number_format($ringkasan['proyeksi_kas_setelah_cair'], 0, ',', '.') ?>
                </div>
                <div class="small mt-auto text-success" style="font-size:0.75rem;">
                    Saldo riil + GU yang segera cair
                </div>
            </div>
        </div>
    </div>

    <!-- Info Plafond UP Ribbon -->
    <div class="alert alert-light border shadow-sm rounded-3 py-2 px-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2" style="font-size:0.85rem;">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-info-circle-fill text-primary"></i>
            <span>Plafond Uang Persediaan (UP) Kantor: <strong class="font-monospace text-dark">Rp <?= number_format($ringkasan['plafond_up'], 0, ',', '.') ?></strong></span>
        </div>
        <div class="text-muted small">
            Mekanisme: Belanja terverifikasi di-SPJ-kan <i class="bi bi-arrow-right text-primary mx-1"></i> dimintakan Ganti Uang (GU) <i class="bi bi-arrow-right text-primary mx-1"></i> Kas kembali utuh.
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
                            <i class="bi bi-arrow-down-left-circle text-success me-2"></i>Penerimaan Kas & Status Pencairan GU
                        </h5>
                        <small class="text-muted">Daftar saldo awal, pencairan UP, dan pengajuan/pencairan dana GU</small>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 font-monospace">
                        Cair: Rp <?= number_format($ringkasan['total_penerimaan_cair'], 0, ',', '.') ?>
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size:0.875rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4" style="width: 110px;">Tanggal</th>
                                    <th>Jenis & Status</th>
                                    <th>No. Bukti / SP2D</th>
                                    <th>Keterangan</th>
                                    <th class="text-end" style="width: 140px;">Nominal</th>
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
                                        $isPending = ($m['status'] === 'menunggu_cair');
                                        ?>
                                        <tr class="<?= $isPending ? 'table-warning bg-opacity-25' : '' ?>">
                                            <td class="ps-4 fw-semibold text-dark font-monospace" style="font-size:0.8rem;">
                                                <?= date('d/m/Y', strtotime($m['tanggal'])) ?>
                                                <?php if (!empty($m['tanggal_cair']) && $m['status'] === 'cair'): ?>
                                                    <div class="text-success text-xxs" style="font-size:0.68rem;" title="Tanggal cair ke bank">
                                                        <i class="bi bi-check-circle me-0.5"></i>Cair: <?= date('d/m/Y', strtotime($m['tanggal_cair'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column gap-1 align-items-start">
                                                    <span class="badge <?= $jInfo[1] ?> border px-2 py-0.5" style="font-size:0.68rem;">
                                                        <?= $jInfo[0] ?>
                                                    </span>
                                                    <?php if ($isPending): ?>
                                                        <span class="badge bg-warning text-dark border border-warning px-2 py-0.5" style="font-size:0.65rem;">
                                                            <i class="bi bi-hourglass me-1"></i>Menunggu Cair
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5" style="font-size:0.65rem;">
                                                            <i class="bi bi-check2 me-1"></i>Sudah Cair
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="font-monospace text-xs text-muted">
                                                <?= htmlspecialchars($m['nomor_bukti'] ?: '-') ?>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars($m['keterangan']) ?></div>
                                                <?php if ($isPending): ?>
                                                    <form method="POST" action="<?= base_url('kas-bank/cairkan/' . $m['id']) ?>" class="mt-1" onsubmit="return confirm('Tandai dana GU ini sudah cair dan masuk ke rekening kas/bank?')">
                                                        <input type="hidden" name="tanggal_cair" value="<?= date('Y-m-d') ?>">
                                                        <button type="submit" class="btn btn-xs btn-success py-0.5 px-2 rounded-2 fw-semibold" style="font-size:0.72rem;">
                                                            <i class="bi bi-check-lg me-1"></i>Tandai Sudah Cair
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end font-monospace fw-bold <?= $isPending ? 'text-warning-emphasis' : 'text-success' ?>">
                                                + Rp <?= number_format((float)$m['nominal'], 0, ',', '.') ?>
                                            </td>
                                            <td class="text-center pe-4">
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-light p-1 rounded-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="bi bi-three-dots-vertical"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="font-size:0.85rem;">
                                                        <?php if ($isPending): ?>
                                                            <li>
                                                                <form method="POST" action="<?= base_url('kas-bank/cairkan/' . $m['id']) ?>">
                                                                    <button type="submit" class="dropdown-item text-success fw-semibold">
                                                                        <i class="bi bi-check-circle me-2"></i>Tandai Cair
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <button class="dropdown-item btn-edit-kas" 
                                                                    data-id="<?= $m['id'] ?>"
                                                                    data-tanggal="<?= $m['tanggal'] ?>"
                                                                    data-jenis="<?= $m['jenis'] ?>"
                                                                    data-status="<?= $m['status'] ?>"
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
                            <i class="bi bi-receipt text-danger me-2"></i>Belanja Terverifikasi Bulan Ini
                        </h5>
                        <small class="text-muted">Transaksi belanja yang telah memotong saldo kas & siap di-SPJ-kan</small>
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
                    <span class="small text-muted">Total Belanja Siap GU:</span>
                    <strong class="font-monospace text-danger">Rp <?= number_format($ringkasan['belanja_siap_gu'], 0, ',', '.') ?></strong>
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
                    <i class="bi bi-plus-circle me-2"></i>Catat Pengajuan GU / Penerimaan Kas
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= base_url('kas-bank/store') ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Tanggal Pengajuan / Penerimaan <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Jenis Mutasi <span class="text-danger">*</span></label>
                            <select name="jenis" class="form-select" required>
                                <option value="gu" selected>Pencairan GU (Ganti Uang)</option>
                                <option value="saldo_awal">Saldo Awal Bulan</option>
                                <option value="up">Pencairan UP (Uang Persediaan)</option>
                                <option value="setoran">Setoran Kas / Pengembalian</option>
                                <option value="lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Status Dana <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="menunggu_cair">⏳ Menunggu Cair (Proses Kasda)</option>
                                <option value="cair">✅ Sudah Cair (Masuk Kas/Bank)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Nomor Bukti / SP2D / Ref Bank</label>
                        <input type="text" name="nomor_bukti" class="form-control" placeholder="Contoh: SP2D-GU/09/2026 atau Bukti Transfer">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Keterangan <span class="text-danger">*</span></label>
                        <textarea name="keterangan" class="form-control" rows="2" placeholder="Contoh: Pengajuan GU SPJ Periode 1-15 September 2026" required></textarea>
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
                        <label class="form-label small fw-bold text-dark">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal" id="editTanggal" class="form-control" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Jenis Mutasi <span class="text-danger">*</span></label>
                            <select name="jenis" id="editJenis" class="form-select" required>
                                <option value="gu">Pencairan GU (Ganti Uang)</option>
                                <option value="saldo_awal">Saldo Awal Bulan</option>
                                <option value="up">Pencairan UP (Uang Persediaan)</option>
                                <option value="setoran">Setoran Kas / Pengembalian</option>
                                <option value="lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Status Dana <span class="text-danger">*</span></label>
                            <select name="status" id="editStatus" class="form-select" required>
                                <option value="menunggu_cair">⏳ Menunggu Cair</option>
                                <option value="cair">✅ Sudah Cair</option>
                            </select>
                        </div>
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
    const editStatus = document.getElementById('editStatus');
    const editNomorBukti = document.getElementById('editNomorBukti');
    const editKeterangan = document.getElementById('editKeterangan');
    const editNominal = document.getElementById('editNominal');

    document.querySelectorAll('.btn-edit-kas').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const tgl = this.getAttribute('data-tanggal');
            const jenis = this.getAttribute('data-jenis');
            const status = this.getAttribute('data-status');
            const nomor = this.getAttribute('data-nomor');
            const ket = this.getAttribute('data-keterangan');
            const nominal = parseFloat(this.getAttribute('data-nominal') || 0);

            formEdit.action = `<?= base_url('kas-bank/update/') ?>${id}`;
            editTanggal.value = tgl;
            editJenis.value = jenis;
            if (editStatus) editStatus.value = status;
            editNomorBukti.value = nomor;
            editKeterangan.value = ket;
            editNominal.value = new Intl.NumberFormat('id-ID').format(nominal);

            const modal = new bootstrap.Modal(modalEditEl);
            modal.show();
        });
    });
});
</script>
