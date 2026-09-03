<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
[x-cloak] { display: none !important; }
.uraian-clamp {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    word-break: break-word;
}
.btn-action-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-size: 0.825rem;
    line-height: 1;
    transition: all 0.15s ease;
}
.btn-action-icon:hover {
    transform: translateY(-1px);
}
/* Mode Compact / Density */
.table-compact th,
.table-compact td {
    padding-top: 0.35rem !important;
    padding-bottom: 0.35rem !important;
    padding-left: 0.6rem !important;
    padding-right: 0.6rem !important;
    font-size: 0.8rem !important;
}
.table-compact .btn-action-icon {
    width: 28px !important;
    height: 28px !important;
    font-size: 0.75rem !important;
}
.table-compact .badge {
    padding: 0.2rem 0.5rem !important;
    font-size: 0.7rem !important;
}
.table-compact .mobile-tx-card {
    padding: 0.65rem 0.85rem !important;
    margin-bottom: 0.6rem !important;
}
/* Visual Grouping Surat Tugas Rombongan */
.st-group-row {
    background-color: #fafbff;
}
.st-group-row:hover {
    background-color: #f1f5f9;
}
.filter-pill {
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 700;
    padding: 0.35rem 0.85rem;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #475569;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    transition: all 0.15s;
}
.filter-pill.active {
    background: #4f46e5;
    color: #fff;
    border-color: #4f46e5;
    box-shadow: 0 2px 6px rgba(79,70,229,0.25);
}
.filter-pill:hover { text-decoration: none; border-color: #4f46e5; color: #4f46e5; }
.filter-pill.active:hover { color: #fff; }
.filter-pill .count-badge {
    background: #f1f5f9;
    color: #334155;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.1rem 0.35rem;
    border-radius: 999px;
    min-width: 1.1rem;
    text-align: center;
    line-height: 1.2;
}
.filter-pill.active .count-badge {
    background: rgba(255,255,255,0.22);
    color: #fff;
}
.pill-scroll {
    overflow-x: auto;
    white-space: nowrap;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}
.pill-scroll::-webkit-scrollbar { display: none; }
.filter-card {
    border-bottom: 1px solid #e2e8f0;
}
.search-wrap {
    position: relative;
}
.search-clear {
    position: absolute;
    right: 0.55rem;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    color: #94a3b8;
    font-size: 1rem;
    line-height: 1;
    cursor: pointer;
    display: none;
    padding: 0.15rem;
}
.search-clear.visible { display: block; }
.search-clear:hover { color: #334155; }
@media (max-width: 767.98px) {
    .pill-scroll { flex-wrap: nowrap !important; overflow-x: auto; padding-bottom: 0.25rem; }
    .filter-month-year { flex: 0 0 100% !important; max-width: 100% !important; }
    .search-col, .btn-col { flex: 0 0 100% !important; max-width: 100% !important; }
    .btn-col .btn { width: 100%; }
}
.jenis-chip {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.18rem 0.45rem;
    border-radius: 999px;
    border: 1px solid;
    white-space: nowrap;
}
.jenis-chip.perjadin { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.jenis-chip.belanja { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
.jenis-chip.honorarium { background: #faf5ff; color: #7c3aed; border-color: #e9d5ff; }
.jenis-chip.lainnya { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
.table-sticky-wrap {
    max-height: 62vh;
    overflow: auto;
}
.table-sticky-wrap thead th {
    position: sticky;
    top: 0;
    z-index: 5;
    background: #f8fafc !important;
    box-shadow: 0 1px 0 #e2e8f0;
}
.mobile-tx-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 0.9rem;
    border-left: 4px solid #3b82f6;
}
.mobile-tx-card .mc-label {
    font-size: 0.68rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 0.15rem;
}
.mobile-tx-card .mc-value { font-size: 0.875rem; color: #0f172a; margin-bottom: 0.6rem; }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 style="font-weight:800;color:#0f172a;letter-spacing:-0.02em;margin-bottom:0.25rem;">
            <i class="bi bi-receipt me-2 text-primary"></i>Transaksi Saya
        </h3>
        <p class="text-muted mb-0" style="font-size:0.875rem;">Daftar transaksi anggaran yang diajukan oleh seksi Anda.</p>
    </div>
    <a href="<?= base_url('seksi/transaksi/create') ?>" class="btn btn-primary px-3 py-2 fw-semibold" style="border-radius:8px;box-shadow:0 2px 6px rgba(37,99,235,0.25);">
        <i class="bi bi-plus-circle me-1"></i> Tambah Transaksi
    </a>
</div>

<?php
// filter values passed from controller
$curStatus = $filters['status'] ?? ($_GET['status'] ?? '');
$curBulan  = $filters['bulan'] ?? (isset($_GET['bulan']) && $_GET['bulan']!=='' ? (int)$_GET['bulan'] : null);
$curTahun  = $filters['tahun'] ?? (isset($_GET['tahun']) && $_GET['tahun']!=='' ? (int)$_GET['tahun'] : null);
$curQ      = $filters['q'] ?? ($_GET['q'] ?? '');
$hasFilter = $hasFilter ?? ($curStatus!=='' || $curBulan!==null || $curTahun!==null || $curQ!=='');
$totalFiltered = $pagination['total'] ?? (is_array($transaksis)?count($transaksis):0);
$isFilteredEmpty = $hasFilter && empty($transaksis) && $totalFiltered===0;
?>

<!-- FILTER BAR -->
<div class="card border-0 shadow-sm mb-3 filter-card" style="border-radius:14px;">
    <div class="card-body p-3">
        <form method="GET" action="<?= base_url('seksi/transaksi') ?>" id="filterForm" class="row g-2 align-items-end">
            <div class="col-12 d-flex align-items-center gap-2">
                <div class="d-flex gap-2 pill-scroll flex-grow-1">
                    <?php
                    $statusOpts = [''=> 'Semua', 'diajukan'=>'Menunggu Verifikasi','diverifikasi'=>'Diverifikasi','ditolak'=>'Ditolak'];
                    $counts = $statusCounts ?? [''=>0,'diajukan'=>0,'diverifikasi'=>0,'ditolak'=>0];
                    foreach ($statusOpts as $val=>$label):
                        $active = ($curStatus === $val) || ($val==='' && $curStatus==='');
                        $qs = array_filter(['status'=>$val===''?null:$val,'bulan'=>$curBulan,'tahun'=>$curTahun,'q'=>$curQ?:null]);
                        $href = base_url('seksi/transaksi') . ($qs ? '?' . http_build_query($qs) : '');
                        $cnt = $counts[$val] ?? 0;
                    ?>
                    <a href="<?= $href ?>" class="filter-pill <?= $active?'active':'' ?>"><?= $label ?> <span class="count-badge"><?= $cnt ?></span></a>
                    <?php endforeach; ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($curStatus) ?>">
                </div>
                <a href="<?= base_url('seksi/transaksi') ?>" id="resetFilterBtn" class="btn btn-outline-secondary btn-sm flex-shrink-0" style="<?= $hasFilter?'':'display:none;' ?>" title="Reset semua filter"><i class="bi bi-x-circle me-1"></i>Reset Filter</a>
            </div>
            <div class="col-md-3 col-6 filter-month-year">
                <label class="form-label small fw-semibold mb-1">Bulan</label>
                <select name="bulan" class="form-select form-select-sm">
                    <option value="">Semua Bulan</option>
                    <?php for($m=1;$m<=12;$m++): $mn=[1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'][$m]; ?>
                    <option value="<?= $m ?>" <?= (int)$curBulan===$m?'selected':'' ?>><?= $mn ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3 col-6 filter-month-year">
                <label class="form-label small fw-semibold mb-1">Tahun</label>
                <select name="tahun" class="form-select form-select-sm">
                    <option value="">Semua Tahun</option>
                    <?php foreach(($tahunList ?? [(int)date('Y')]) as $y): ?>
                    <option value="<?= $y ?>" <?= (int)$curTahun===(int)$y?'selected':'' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-12 search-col">
                <label class="form-label small fw-semibold mb-1">Cari uraian / no bukti</label>
                <div class="search-wrap">
                    <input type="text" name="q" id="qInput" value="<?= htmlspecialchars($curQ) ?>" class="form-control form-control-sm pe-4" placeholder="Ketik uraian atau nomor bukti..." autocomplete="off">
                    <button type="button" id="qClear" class="search-clear <?= $curQ!==''?'visible':'' ?>" aria-label="Clear"><i class="bi bi-x-circle-fill"></i></button>
                </div>
            </div>
            <div class="col-md-2 col-12 d-flex gap-2 btn-col filter-search-row">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-fill fw-semibold" style="border-color:#4f46e5;color:#4f46e5;background:transparent;">
                    <i class="bi bi-search me-1"></i>Cari
                </button>
                <button type="button" id="btnUnduhBku" class="btn btn-outline-success btn-sm flex-shrink-0 fw-semibold" title="Unduh BKU bulan &amp; tahun yang aktif">
                    <i class="bi bi-file-earmark-excel me-1"></i>BKU
                </button>
                <button type="button" id="btnToggleDensity" class="btn btn-outline-secondary btn-sm flex-shrink-0 fw-semibold" title="Ubah Kerapatan Tampilan (Normal / Compact)">
                    <i class="bi bi-distribute-vertical" id="densityIcon"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
    <div class="card-body p-0">
        <?php if (empty($transaksis)): ?>
            <?php
                $emptyTitle = 'Belum Ada Transaksi yang Diajukan';
                $emptySubtitle = 'Seksi Anda belum pernah mengajukan transaksi belanja. Klik tombol di bawah untuk mulai menginput transaksi baru.';
                $emptyIcon = 'bi-receipt-cutoff';
                $emptyIconColor = '#3b82f6';
                $emptyIconBg = '#eff6ff';

                if ($curStatus === 'diverifikasi') {
                    $emptyTitle = 'Belum Ada Transaksi yang Diverifikasi';
                    $emptySubtitle = 'Transaksi belanja yang telah disetujui oleh admin/verifikator akan tampil di sini.';
                    $emptyIcon = 'bi-check2-circle';
                    $emptyIconColor = '#16a34a';
                    $emptyIconBg = '#f0fdf4';
                } elseif ($curStatus === 'ditolak') {
                    $emptyTitle = 'Belum Ada Transaksi yang Ditolak';
                    $emptySubtitle = 'Bagus! Tidak ada transaksi belanja seksi Anda yang ditolak oleh verifikator.';
                    $emptyIcon = 'bi-shield-check';
                    $emptyIconColor = '#059669';
                    $emptyIconBg = '#ecfdf5';
                } elseif ($curStatus === 'diajukan') {
                    $emptyTitle = 'Tidak Ada Transaksi yang Menunggu Verifikasi';
                    $emptySubtitle = 'Semua pengajuan transaksi belanja seksi Anda saat ini sudah diproses.';
                    $emptyIcon = 'bi-inbox';
                    $emptyIconColor = '#d97706';
                    $emptyIconBg = '#fef3c7';
                } elseif ($hasFilter) {
                    $emptyTitle = 'Tidak ada transaksi yang cocok dengan filter ini.';
                    $emptySubtitle = 'Coba ubah filter status, bulan, tahun, atau kata kunci pencarian Anda.';
                    $emptyIcon = 'bi-search';
                    $emptyIconColor = '#64748b';
                    $emptyIconBg = '#f1f5f9';
                }
            ?>
            <div class="text-center py-5 px-3">
                <div style="width:68px;height:68px;border-radius:50%;background:<?= $emptyIconBg ?>;color:<?= $emptyIconColor ?>;display:inline-flex;align-items:center;justify-content:center;font-size:1.8rem;margin-bottom:1rem;">
                    <i class="bi <?= $emptyIcon ?>"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($emptyTitle) ?></h5>
                <p class="text-muted mx-auto mb-4" style="max-width:440px;font-size:0.875rem;">
                    <?= htmlspecialchars($emptySubtitle) ?>
                </p>
                <?php if ($hasFilter): ?>
                    <a href="<?= base_url('seksi/transaksi') ?>" class="btn btn-outline-secondary btn-sm px-3 py-1.5 fw-semibold" style="border-radius:8px;">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Filter
                    </a>
                <?php else: ?>
                    <a href="<?= base_url('seksi/transaksi/create') ?>" class="btn btn-primary px-4 py-2 fw-semibold" style="border-radius:8px;">
                        <i class="bi bi-plus-circle me-1"></i> Tambah Transaksi Sekarang
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php
                // Hitung kemunculan ST untuk visual grouping ringan
                $stCounts = [];
                foreach ($transaksis as $t) {
                    $stNum = trim((string)($t['nomor_surat_tugas'] ?? ''));
                    if ($stNum !== '') {
                        $stCounts[$stNum] = ($stCounts[$stNum] ?? 0) + 1;
                    }
                }
            ?>

            <!-- DESKTOP TABLE -->
            <div class="table-responsive table-sticky-wrap d-none d-md-block">
                <table class="table table-hover align-middle mb-0" style="font-size:0.875rem;">
                    <thead class="table-light text-muted" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.04em;">
                        <tr>
                            <th class="ps-3 py-3" style="width:10%;">Tanggal</th>
                            <th style="width:18%;">Sub Kegiatan</th>
                            <th style="width:16%;">Rekening</th>
                            <th style="width:24%;">Uraian & Penerima</th>
                            <th class="text-end" style="width:12%;">Nilai (Rp)</th>
                            <th class="text-center" style="width:9%;">Status</th>
                            <th class="text-center pe-3" style="width:11%; min-width:145px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $prevSt = null;
                        foreach ($transaksis as $t): 
                            $status = $t['status'] ?? 'diverifikasi';
                            $badge = match ($status) {
                                'diajukan'     => ['Menunggu Verifikasi', 'warning', '#fefce8', '#854d0e', '#fef08a'],
                                'diverifikasi' => ['Diverifikasi', 'success', '#f0fdf4', '#166534', '#bbf7d0'],
                                'ditolak'      => ['Ditolak', 'danger', '#fef2f2', '#991b1b', '#fecaca'],
                                default        => [ucfirst($status), 'secondary', '#f1f5f9', '#475569', '#e2e8f0'],
                            };
                            $bolehEdit = in_array($status, ['diajukan','ditolak'], true);

                            $jenisMap = [
                                'perjalanan_dinas'=> ['Perjadin','perjadin'],
                                'belanja'=> ['Belanja','belanja'],
                                'honorarium'=> ['Honor','honorarium'],
                                'lainnya'=> ['Lainnya','lainnya'],
                            ];
                            $jv = $t['jenis_transaksi'] ?? '';
                            $jenisInfo = $jenisMap[$jv] ?? null;
                            $uraianFull = $t['uraian'] ?? '';
                            $namaPenerima = $t['nama_penerima'] ?? '';
                            $noBukti = $t['nomor_bukti'] ?? '-';
                            $noST = trim((string)($t['nomor_surat_tugas'] ?? ''));

                            // Teks copy uraian transaksi
                            $copyText = trim((string)$uraianFull);

                            // Cek apakah transaksi ini berbagi Nomor ST yang sama (>= 2 transaksi)
                            $isSharedSt = ($noST !== '' && ($stCounts[$noST] ?? 0) >= 2);
                            $isNewStGroup = ($isSharedSt && $noST !== $prevSt);
                            $prevSt = $noST;
                        ?>
                        <tr class="<?= $isSharedSt ? 'st-group-row' : '' ?>" style="<?= $isNewStGroup ? 'border-top: 2px solid #cbd5e1;' : '' ?>">
                            <td class="ps-3" style="<?= $isSharedSt ? 'border-left: 3px solid #6366f1;' : '' ?>">
                                <div class="fw-semibold text-dark"><?= date('d/m/Y', strtotime($t['tanggal'])) ?></div>
                                <small class="text-muted font-monospace" style="font-size:0.75rem;"><?= htmlspecialchars($noBukti) ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark" style="font-size:0.825rem;"><?= htmlspecialchars($t['nama_sub_kegiatan'] ?? '-') ?></div>
                                <small class="text-muted font-monospace" style="font-size:0.75rem;"><?= htmlspecialchars($t['kode_sub_kegiatan'] ?? '') ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark text-truncate" style="max-width:180px;font-size:0.825rem;" title="<?= htmlspecialchars($t['nama_rekening'] ?? '') ?>">
                                    <?= htmlspecialchars($t['nama_rekening'] ?? '-') ?>
                                </div>
                                <small class="font-monospace d-block text-truncate" style="font-size:0.75rem;color:#94a3b8;max-width:180px;" title="<?= htmlspecialchars($t['kode_rekening'] ?? '') ?>">
                                    <?= htmlspecialchars($t['kode_rekening'] ?? '') ?>
                                </small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-1 mb-1">
                                    <?php if ($jenisInfo): ?>
                                        <span class="jenis-chip <?= $jenisInfo[1] ?>"><?= $jenisInfo[0] ?></span>
                                    <?php endif; ?>
                                    <div class="text-truncate text-dark fw-medium" style="max-width:270px;font-size:0.85rem;cursor:default;" title="<?= htmlspecialchars($uraianFull) ?>">
                                        <?= htmlspecialchars($uraianFull) ?>
                                    </div>
                                </div>
                                <?php if (!empty($namaPenerima) || !empty($noST)): ?>
                                    <div class="small text-muted text-truncate" style="max-width:270px;font-size:0.75rem;">
                                        <?php if (!empty($namaPenerima)): ?>
                                            <span class="me-2"><i class="bi bi-person-fill text-secondary me-1"></i><?= htmlspecialchars($namaPenerima) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($noST)): ?>
                                            <span><i class="bi bi-file-earmark-text me-1"></i>ST: <?= htmlspecialchars($noST) ?><?php if ($isSharedSt): ?> <span class="badge bg-indigo-subtle text-primary border border-indigo-subtle" style="font-size:0.65rem;padding:0.1rem 0.3rem;">Rombongan</span><?php endif; ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold text-dark">
                                Rp <?= number_format($t['nilai'], 0, ',', '.') ?>
                            </td>
                            <td class="text-center">
                                <span style="font-size:0.75rem;font-weight:700;padding:0.3rem 0.65rem;border-radius:999px;background:<?= $badge[2] ?>;color:<?= $badge[3] ?>;border:1px solid <?= $badge[4] ?>;white-space:nowrap;">
                                    <?= $badge[0] ?>
                                </span>
                                <?php if ($status === 'ditolak' && !empty($t['catatan_verifikasi'])): ?>
                                    <div class="mt-1">
                                        <button type="button" class="btn btn-link p-0 text-danger" style="font-size:0.75rem;text-decoration:none;" data-bs-toggle="modal" data-bs-target="#modalTolak-<?= $t['id'] ?>">
                                            <i class="bi bi-info-circle"></i> Alasan
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center pe-3">
                                <div class="d-inline-flex align-items-center justify-content-center gap-1">
                                    <button type="button"
                                            x-data="{ copied: false }"
                                            class="btn btn-outline-secondary btn-action-icon"
                                            :class="{ 'border-success text-success bg-success-subtle': copied }"
                                            @click="
                                                copyTextToClipboard(<?= htmlspecialchars(json_encode($copyText), ENT_QUOTES, 'UTF-8') ?>).then(() => {
                                                    copied = true;
                                                    setTimeout(() => { copied = false; }, 1500);
                                                })
                                            "
                                            :title="copied ? 'Uraian tersalin!' : 'Salin uraian'">
                                        <i :class="copied ? 'bi bi-check-lg text-success' : 'bi bi-clipboard'"></i>
                                    </button>

                                    <?php if ($bolehEdit): ?>
                                        <a href="<?= base_url('seksi/transaksi/edit/' . $t['id']) ?>"
                                           class="btn btn-outline-primary btn-action-icon"
                                           title="Edit Transaksi">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" action="<?= base_url('seksi/transaksi/delete/' . $t['id']) ?>"
                                              class="d-inline m-0 p-0"
                                              onsubmit="return confirm('Apakah Anda yakin ingin menghapus transaksi ini?')">
                                            <button type="submit" class="btn btn-outline-danger btn-action-icon" title="Hapus Transaksi">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border d-inline-flex align-items-center py-1 px-2"
                                              style="font-size:0.75rem;height:32px;"
                                              title="Terkunci (sudah diverifikasi)">
                                            <i class="bi bi-lock-fill me-1"></i>Terkunci
                                        </span>
                                    <?php endif; ?>

                                    <?php if (($t['jenis_transaksi'] ?? '') === 'perjalanan_dinas'): ?>
                                        <a href="<?= base_url('seksi/transaksi/unduh-rincian-biaya?transaksi_id=' . $t['id']) ?>"
                                           class="btn btn-outline-success btn-action-icon"
                                           title="Unduh Excel Rincian Biaya SPPD">
                                            <i class="bi bi-file-earmark-excel"></i>
                                        </a>
                                    <?php elseif ($bolehEdit): ?>
                                        <!-- Placeholder invisible agar layout tetap konsisten dan tidak loncat -->
                                        <span class="btn-action-icon" style="visibility:hidden;" aria-hidden="true"></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- MOBILE CARD LIST -->
            <div class="d-md-none p-3">
                <?php foreach ($transaksis as $t): ?>
                    <?php
                        $status = $t['status'] ?? 'diverifikasi';
                        $badge = match ($status) {
                            'diajukan'     => ['Menunggu Verifikasi', '#fefce8', '#854d0e', '#fef08a'],
                            'diverifikasi' => ['Diverifikasi', '#f0fdf4', '#166534', '#bbf7d0'],
                            'ditolak'      => ['Ditolak', '#fef2f2', '#991b1b', '#fecaca'],
                            default        => [ucfirst($status), '#f1f5f9', '#475569', '#e2e8f0'],
                        };
                        $bolehEdit = in_array($status, ['diajukan','ditolak'], true);
                        $jv = $t['jenis_transaksi'] ?? '';
                        $jenisMap2 = ['perjalanan_dinas'=>['Perjadin','perjadin'],'belanja'=>['Belanja','belanja'],'honorarium'=>['Honor','honorarium'],'lainnya'=>['Lainnya','lainnya']];
                        $jenisInfo2 = $jenisMap2[$jv] ?? null;
                        $noST = trim((string)($t['nomor_surat_tugas'] ?? ''));
                        $isSharedSt = ($noST !== '' && ($stCounts[$noST] ?? 0) >= 2);

                        // Teks copy uraian transaksi (mobile)
                        $mCopyText = trim((string)($t['uraian'] ?? ''));
                    ?>
                    <div class="mobile-tx-card" style="<?= $isSharedSt ? 'border-left: 4px solid #6366f1;' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="mc-label">Tanggal / No Bukti</div>
                                <div class="mc-value fw-semibold"><?= date('d/m/Y', strtotime($t['tanggal'])) ?> <small class="text-muted font-monospace"><?= htmlspecialchars($t['nomor_bukti'] ?? '-') ?></small></div>
                            </div>
                            <span style="font-size:0.7rem;font-weight:700;padding:0.25rem 0.6rem;border-radius:999px;background:<?= $badge[1] ?>;color:<?= $badge[2] ?>;border:1px solid <?= $badge[3] ?>;"><?= $badge[0] ?></span>
                        </div>
                        <?php if($jenisInfo2): ?><span class="jenis-chip <?= $jenisInfo2[1] ?> mb-2 d-inline-block"><?= $jenisInfo2[0] ?></span><?php endif; ?>
                        <div class="mc-label">Sub Kegiatan</div>
                        <div class="mc-value"><?= htmlspecialchars($t['kode_sub_kegiatan'] ?? '') ?> - <?= htmlspecialchars($t['nama_sub_kegiatan'] ?? '-') ?></div>
                        <div class="mc-label">Rekening</div>
                        <div class="mc-value">
                            <div class="fw-semibold text-dark"><?= htmlspecialchars($t['nama_rekening'] ?? '-') ?></div>
                            <small class="font-monospace d-block" style="font-size:0.75rem;color:#94a3b8;"><?= htmlspecialchars($t['kode_rekening'] ?? '') ?></small>
                        </div>
                        <div class="mc-label">Uraian</div>
                        <div class="text-truncate text-dark fw-medium mb-1" style="font-size:0.875rem;" title="<?= htmlspecialchars($t['uraian']) ?>">
                            <?= htmlspecialchars($t['uraian']) ?>
                        </div>
                        <?php if(!empty($t['nama_penerima'])): ?><div class="small text-muted"><i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($t['nama_penerima']) ?></div><?php endif; ?>
                        <?php if(!empty($noST)): ?>
                            <div class="small text-muted"><i class="bi bi-file-earmark-text me-1"></i>ST: <?= htmlspecialchars($noST) ?><?php if($isSharedSt): ?> <span class="badge bg-indigo-subtle text-primary border" style="font-size:0.65rem;">Rombongan</span><?php endif; ?></div>
                        <?php endif; ?>
                        <div class="mc-label mt-2">Nilai</div>
                        <div class="mc-value fw-bold">Rp <?= number_format($t['nilai'], 0, ',', '.') ?></div>
                        <?php if($status==='ditolak' && !empty($t['catatan_verifikasi'])): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger mt-1" data-bs-toggle="modal" data-bs-target="#modalTolak-<?= $t['id'] ?>"><i class="bi bi-info-circle me-1"></i>Alasan Ditolak</button>
                        <?php endif; ?>
                        <div class="d-flex gap-2 mt-3 align-items-center" x-data="{ copied: false }">
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary flex-shrink-0"
                                    :class="{ 'border-success text-success bg-success-subtle': copied }"
                                    @click="
                                        copyTextToClipboard(<?= htmlspecialchars(json_encode($mCopyText), ENT_QUOTES, 'UTF-8') ?>).then(() => {
                                            copied = true;
                                            setTimeout(() => { copied = false; }, 1500);
                                        })
                                    "
                                    :title="copied ? 'Uraian tersalin!' : 'Salin uraian'">
                                <i :class="copied ? 'bi bi-check-lg text-success me-1' : 'bi bi-clipboard me-1'"></i><span x-text="copied ? 'Tersalin' : 'Salin'">Salin</span>
                            </button>
                            <?php if($bolehEdit): ?>
                                <a href="<?= base_url('seksi/transaksi/edit/'.$t['id']) ?>" class="btn btn-sm btn-outline-primary flex-fill"><i class="bi bi-pencil me-1"></i>Edit</a>
                                <form method="POST" action="<?= base_url('seksi/transaksi/delete/'.$t['id']) ?>" class="flex-fill" onsubmit="return confirm('Apakah Anda yakin ingin menghapus transaksi ini?')">
                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-trash me-1"></i>Hapus</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted small d-inline-flex align-items-center ms-auto"><i class="bi bi-lock-fill me-1"></i> Terkunci</span>
                            <?php endif; ?>
                            <?php if(($t['jenis_transaksi'] ?? '') === 'perjalanan_dinas'): ?>
                                <a href="<?= base_url('seksi/transaksi/unduh-rincian-biaya?transaksi_id=' . $t['id']) ?>"
                                   class="btn btn-sm btn-outline-success <?= $bolehEdit ? 'flex-shrink-0' : 'flex-fill' ?>"
                                   title="Unduh Excel Rincian Biaya SPPD">
                                    <i class="bi bi-file-earmark-excel me-1"></i>Excel
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($pagination) && $pagination['totalPages'] > 1): ?>
                <?php
                    $qsBase = array_filter(['status'=>$curStatus?:null,'bulan'=>$curBulan,'tahun'=>$curTahun,'q'=>$curQ?:null]);
                    $base = $pagination['baseUrl'] . ($qsBase ? '?' . http_build_query($qsBase) . '&' : '?');
                ?>
                <nav class="p-3 border-top">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= $pagination['page']<=1?'disabled':'' ?>">
                            <a class="page-link" href="<?= $pagination['page']<=1?'#': $base.'page='.($pagination['page']-1) ?>">«</a>
                        </li>
                        <?php for($p=1;$p<=$pagination['totalPages'];$p++): ?>
                            <li class="page-item <?= $p==$pagination['page']?'active':'' ?>"><a class="page-link" href="<?= $base.'page='.$p ?>"><?= $p ?></a></li>
                        <?php endfor; ?>
                        <li class="page-item <?= $pagination['page']>=$pagination['totalPages']?'disabled':'' ?>">
                            <a class="page-link" href="<?= $pagination['page']>=$pagination['totalPages']?'#': $base.'page='.($pagination['page']+1) ?>">»</a>
                        </li>
                    </ul>
                    <div class="text-center small text-muted mt-2">Menampilkan <?= count($transaksis) ?> dari <?= $pagination['total'] ?> transaksi · Halaman <?= $pagination['page'] ?>/<?= $pagination['totalPages'] ?></div>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Uraian Lengkap -->
<div class="modal fade" id="modalUraian" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content" style="border-radius:14px;">
      <div class="modal-header">
        <h6 class="modal-title fw-bold"><i class="bi bi-file-text me-1"></i> Detail Uraian</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2"><small class="text-muted fw-semibold">Uraian Lengkap</small><div id="muUraian" class="text-dark" style="white-space:pre-wrap;"></div></div>
        <div class="mb-2"><small class="text-muted fw-semibold">Nama Penerima</small><div id="muPenerima" class="text-dark">-</div></div>
        <div class="mb-2"><small class="text-muted fw-semibold">Nomor Bukti</small><div id="muBukti" class="font-monospace">-</div></div>
        <div class="mb-0"><small class="text-muted fw-semibold">Nomor Surat Tugas</small><div id="muST" class="font-monospace">-</div></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button></div>
    </div>
  </div>
</div>

<!-- Modal Alasan Ditolak per baris -->
<?php foreach($transaksis as $t): if(($t['status']??'')==='ditolak' && !empty($t['catatan_verifikasi'])): ?>
<div class="modal fade" id="modalTolak-<?= $t['id'] ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content" style="border-radius:14px;">
      <div class="modal-header bg-danger-subtle">
        <h6 class="modal-title fw-bold text-danger"><i class="bi bi-x-circle me-1"></i> Alasan Penolakan</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body small"><?= nl2br(htmlspecialchars($t['catatan_verifikasi'])) ?></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Tutup</button></div>
    </div>
  </div>
</div>
<?php endif; endforeach; ?>

<script>
function copyTextToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
    } else {
        return new Promise(function(resolve, reject) {
            var textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            textArea.style.top = '-9999px';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                var successful = document.execCommand('copy');
                document.body.removeChild(textArea);
                if (successful) {
                    resolve();
                } else {
                    reject(new Error('Copy failed'));
                }
            } catch (err) {
                document.body.removeChild(textArea);
                reject(err);
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function(){
    // Search UX - Enter, clear x, debounce 350ms
    const form = document.getElementById('filterForm');
    const qInput = document.getElementById('qInput');
    const qClear = document.getElementById('qClear');
    const resetBtn = document.getElementById('resetFilterBtn');
    function updateResetVisibility(){
        if(!resetBtn || !qInput) return;
        const hasQ = qInput.value.trim() !== '';
        const selBulan = form ? form.querySelector('select[name="bulan"]').value : '';
        const selTahun = form ? form.querySelector('select[name="tahun"]').value : '';
        // status pills active check: presence of active not Semua already implies filter, but we also check window location
        const hasOther = selBulan !== '' || selTahun !== '' || hasQ;
        // status pill active is captured via server hasFilter but update live for q
        if(hasQ || hasOther) resetBtn.style.display = '';
        else {
            // fallback to server hasFilter for status pills
            const serverHas = <?= json_encode($hasFilter) ?>;
            resetBtn.style.display = serverHas ? '' : 'none';
        }
    }
    function toggleClear(){
        if(!qInput || !qClear) return;
        if(qInput.value.trim() !== '') qClear.classList.add('visible');
        else qClear.classList.remove('visible');
        updateResetVisibility();
    }
    if(qInput){
        qInput.addEventListener('keyup', function(e){
            if(e.key === 'Enter'){
                e.preventDefault();
                form.submit();
            }
        });
        let debounceTimer;
        qInput.addEventListener('input', function(){
            toggleClear();
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function(){
                // auto submit only if value changed (debounce)
                if(qInput.value !== <?= json_encode($curQ) ?>){
                    form.submit();
                }
            }, 350);
        });
        toggleClear();
    }
    if(qClear && qInput){
        qClear.addEventListener('click', function(){
            qInput.value = '';
            toggleClear();
            form.submit();
        });
    }
    // pill active handling for filter form (optional)
    document.querySelectorAll('.filter-pill').forEach(function(p){
        p.addEventListener('click', function(e){
            // allow normal link navigation
        });
    });

    // Mode Density Toggle (Normal vs Compact) dengan localStorage
    const densityBtn = document.getElementById('btnToggleDensity');
    const densityIcon = document.getElementById('densityIcon');
    const txTable = document.querySelector('.table-responsive table');
    const mobileContainer = document.querySelector('.d-md-none');

    function applyDensity(mode) {
        if (mode === 'compact') {
            if (txTable) txTable.classList.add('table-compact');
            if (mobileContainer) mobileContainer.classList.add('table-compact');
            if (densityIcon) densityIcon.className = 'bi bi-list-ul';
            if (densityBtn) densityBtn.title = 'Ubah ke Mode Normal (Renggang)';
        } else {
            if (txTable) txTable.classList.remove('table-compact');
            if (mobileContainer) mobileContainer.classList.remove('table-compact');
            if (densityIcon) densityIcon.className = 'bi bi-distribute-vertical';
            if (densityBtn) densityBtn.title = 'Ubah ke Mode Compact (Rapat)';
        }
        localStorage.setItem('seksi_tx_density', mode);
    }

    const savedDensity = localStorage.getItem('seksi_tx_density') || 'normal';
    applyDensity(savedDensity);

    if (densityBtn) {
        densityBtn.addEventListener('click', function() {
            const isCompact = txTable ? txTable.classList.contains('table-compact') : (localStorage.getItem('seksi_tx_density') === 'compact');
            applyDensity(isCompact ? 'normal' : 'compact');
        });
    }

    // Tombol Unduh Excel BKU
    var btnBku = document.getElementById('btnUnduhBku');
    if (btnBku && form) {
        btnBku.addEventListener('click', function() {
            var selBulan = form.querySelector('select[name="bulan"]').value;
            var selTahun = form.querySelector('select[name="tahun"]').value;
            if (!selBulan || !selTahun) {
                alert('Pilih Bulan dan Tahun terlebih dahulu untuk mengunduh BKU');
                return;
            }
            var url = <?= json_encode(base_url('seksi/transaksi/bku')) ?>;
            url += '?bulan=' + encodeURIComponent(selBulan) + '&tahun=' + encodeURIComponent(selTahun);
            window.location.href = url;
        });
    }
});
</script>
