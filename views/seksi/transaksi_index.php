<style>
.uraian-clamp {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    word-break: break-word;
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
                <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-search me-1"></i>Cari</button>
                <button type="button" id="btnUnduhBku" class="btn btn-success btn-sm flex-shrink-0" title="Unduh BKU bulan &amp; tahun yang aktif">
                    <i class="bi bi-file-earmark-excel me-1"></i>BKU
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
    <div class="card-body p-0">
        <?php if (empty($transaksis) && !$isFilteredEmpty): ?>
            <!-- EMPTY STATE UTAMA -->
            <div class="text-center py-5 px-3">
                <div style="width:72px;height:72px;border-radius:50%;background:#eff6ff;color:#3b82f6;display:inline-flex;align-items:center;justify-content:center;font-size:2rem;margin-bottom:1rem;">
                    <i class="bi bi-receipt-cutoff"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">Belum Ada Transaksi yang Diajukan</h5>
                <p class="text-muted mx-auto mb-4" style="max-width:420px;font-size:0.875rem;">
                    Seksi Anda belum pernah mengajukan transaksi belanja. Klik tombol di bawah untuk mulai menginput transaksi baru.
                </p>
                <a href="<?= base_url('seksi/transaksi/create') ?>" class="btn btn-primary px-4 py-2 fw-semibold" style="border-radius:8px;">
                    <i class="bi bi-plus-circle me-1"></i> Tambah Transaksi Sekarang
                </a>
            </div>
        <?php elseif ($isFilteredEmpty): ?>
            <div class="text-center py-5 px-3">
                <div style="width:56px;height:56px;border-radius:50%;background:#f1f5f9;color:#64748b;display:inline-flex;align-items:center;justify-content:center;font-size:1.6rem;margin-bottom:0.8rem;">
                    <i class="bi bi-search"></i>
                </div>
                <h6 class="fw-bold text-dark mb-1">Tidak ada transaksi yang cocok dengan filter ini.</h6>
                <p class="text-muted small mb-3">Coba ubah filter status/bulan/tahun atau kata kunci pencarian.</p>
                <a href="<?= base_url('seksi/transaksi') ?>" class="btn btn-outline-secondary btn-sm">Reset Filter</a>
            </div>
        <?php else: ?>
            <!-- DESKTOP TABLE -->
            <div class="table-responsive table-sticky-wrap d-none d-md-block">
                <table class="table table-hover align-middle mb-0" style="font-size:0.875rem;">
                    <thead class="table-light text-muted" style="font-size:0.8rem;text-transform:uppercase;letter-spacing:0.04em;">
                        <tr>
                            <th class="ps-3 py-3" style="width:10%;">Tanggal</th>
                            <th style="width:18%;">Sub Kegiatan</th>
                            <th style="width:14%;">Rekening</th>
                            <th style="width:24%;">Uraian & Penerima</th>
                            <th class="text-end" style="width:14%;">Nilai (Rp)</th>
                            <th class="text-center" style="width:10%;">Status</th>
                            <th class="text-center pe-3" style="width:10%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transaksis as $t): ?>
                            <?php
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
                                $noST = $t['nomor_surat_tugas'] ?? '';
                            ?>
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold text-dark"><?= date('d/m/Y', strtotime($t['tanggal'])) ?></div>
                                    <small class="text-muted font-monospace" style="font-size:0.75rem;"><?= htmlspecialchars($noBukti) ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark" style="font-size:0.825rem;"><?= htmlspecialchars($t['nama_sub_kegiatan'] ?? '-') ?></div>
                                    <small class="text-muted font-monospace" style="font-size:0.75rem;"><?= htmlspecialchars($t['kode_sub_kegiatan'] ?? '') ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border" style="font-weight:600;font-size:0.775rem;">
                                        <?= htmlspecialchars($t['kode_rekening'] ?? '') ?>
                                    </span>
                                    <div class="small text-muted text-truncate mt-1" style="max-width:180px;" title="<?= htmlspecialchars($t['nama_rekening'] ?? '') ?>">
                                        <?= htmlspecialchars($t['nama_rekening'] ?? '') ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($jenisInfo): ?>
                                        <span class="jenis-chip <?= $jenisInfo[1] ?> me-1"><?= $jenisInfo[0] ?></span>
                                    <?php endif; ?>
                                    <div class="uraian-clamp text-dark" id="uraian-<?= $t['id'] ?>" style="font-size:0.85rem;line-height:1.4;"><?= htmlspecialchars($uraianFull) ?></div>
                                    <a href="#" class="small link-lihat" data-id="<?= $t['id'] ?>" style="display:none;font-size:0.75rem;">Lihat selengkapnya</a>
                                    <?php if (!empty($namaPenerima)): ?>
                                        <div class="small text-muted mt-1">
                                            <i class="bi bi-person-fill text-secondary me-1"></i><?= htmlspecialchars($namaPenerima) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($noST)): ?>
                                        <div class="small text-muted" style="font-size:0.75rem;">
                                            <i class="bi bi-file-earmark-text me-1"></i>ST: <?= htmlspecialchars($noST) ?>
                                        </div>
                                    <?php endif; ?>
                                    <!-- hidden data for modal -->
                                    <span class="d-none uraian-data"
                                        data-uraian="<?= htmlspecialchars($uraianFull) ?>"
                                        data-penerima="<?= htmlspecialchars($namaPenerima) ?>"
                                        data-bukti="<?= htmlspecialchars($noBukti) ?>"
                                        data-st="<?= htmlspecialchars($noST) ?>"></span>
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
                                    <?php if ($bolehEdit): ?>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?= base_url('seksi/transaksi/edit/' . $t['id']) ?>" class="btn btn-outline-primary" title="Edit Transaksi">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" action="<?= base_url('seksi/transaksi/delete/' . $t['id']) ?>" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus transaksi ini?')">
                                                <button type="submit" class="btn btn-outline-danger" title="Hapus Transaksi">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:0.8rem;"><i class="bi bi-lock-fill"></i> Terkunci</span>
                                    <?php endif; ?>
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
                    ?>
                    <div class="mobile-tx-card">
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
                        <div class="mc-value"><?= htmlspecialchars($t['kode_rekening'] ?? '') ?> <small class="text-muted"><?= htmlspecialchars($t['nama_rekening'] ?? '') ?></small></div>
                        <div class="mc-label">Uraian</div>
                        <div class="uraian-clamp mc-value" id="m-uraian-<?= $t['id'] ?>"><?= htmlspecialchars($t['uraian']) ?></div>
                        <a href="#" class="small link-lihat" data-id="<?= $t['id'] ?>" style="display:none;">Lihat selengkapnya</a>
                        <?php if(!empty($t['nama_penerima'])): ?><div class="small text-muted"><i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($t['nama_penerima']) ?></div><?php endif; ?>
                        <?php if(!empty($t['nomor_surat_tugas'])): ?><div class="small text-muted"><i class="bi bi-file-earmark-text me-1"></i>ST: <?= htmlspecialchars($t['nomor_surat_tugas']) ?></div><?php endif; ?>
                        <span class="d-none uraian-data" data-uraian="<?= htmlspecialchars($t['uraian']) ?>" data-penerima="<?= htmlspecialchars($t['nama_penerima'] ?? '') ?>" data-bukti="<?= htmlspecialchars($t['nomor_bukti'] ?? '-') ?>" data-st="<?= htmlspecialchars($t['nomor_surat_tugas'] ?? '') ?>"></span>
                        <div class="mc-label mt-2">Nilai</div>
                        <div class="mc-value fw-bold">Rp <?= number_format($t['nilai'], 0, ',', '.') ?></div>
                        <?php if($status==='ditolak' && !empty($t['catatan_verifikasi'])): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger mt-1" data-bs-toggle="modal" data-bs-target="#modalTolak-<?= $t['id'] ?>"><i class="bi bi-info-circle me-1"></i>Alasan Ditolak</button>
                        <?php endif; ?>
                        <div class="d-flex gap-2 mt-3">
                            <?php if($bolehEdit): ?>
                                <a href="<?= base_url('seksi/transaksi/edit/'.$t['id']) ?>" class="btn btn-sm btn-outline-primary flex-fill"><i class="bi bi-pencil me-1"></i>Edit</a>
                                <form method="POST" action="<?= base_url('seksi/transaksi/delete/'.$t['id']) ?>" class="flex-fill" onsubmit="return confirm('Apakah Anda yakin ingin menghapus transaksi ini?')">
                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-trash me-1"></i>Hapus</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted small"><i class="bi bi-lock-fill"></i> Terkunci (sudah diverifikasi)</span>
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
document.addEventListener('DOMContentLoaded', function(){
    // Task1: show Lihat selengkapnya only if truncated (per element sibling)
    function toggleLihat(){
        document.querySelectorAll('.uraian-clamp').forEach(function(el){
            let link = el.nextElementSibling;
            // walk to next link-lihat
            while(link && !link.classList.contains('link-lihat')) link = link.nextElementSibling;
            if(!link){
                const id = (el.id||'').replace('m-uraian-','').replace('uraian-','');
                link = document.querySelector('a.link-lihat[data-id="'+id+'"]');
            }
            if(!link) return;
            if(el.scrollHeight > el.clientHeight + 2) link.style.display='inline';
            else link.style.display='none';
        });
    }
    toggleLihat();
    window.addEventListener('resize', toggleLihat);
    document.querySelectorAll('a.link-lihat').forEach(function(a){
        a.addEventListener('click', function(e){
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const row = this.closest('tr') || this.closest('.mobile-tx-card');
            const dataEl = row ? row.querySelector('.uraian-data') : null;
            // fallback find in document
            let uraian='', penerima='-', bukti='-', st='-';
            if(dataEl){
                uraian = dataEl.getAttribute('data-uraian')||'';
                penerima = dataEl.getAttribute('data-penerima')||'-';
                bukti = dataEl.getAttribute('data-bukti')||'-';
                st = dataEl.getAttribute('data-st')||'-';
            } else {
                // mobile without data-el: try parent
                const el = document.getElementById('uraian-'+id) || document.getElementById('m-uraian-'+id);
                uraian = el ? el.textContent : '';
            }
            document.getElementById('muUraian').textContent = uraian||'-';
            document.getElementById('muPenerima').textContent = penerima||'-';
            document.getElementById('muBukti').textContent = bukti||'-';
            document.getElementById('muST').textContent = st && st!=='' ? st : '-';
            var modal = new bootstrap.Modal(document.getElementById('modalUraian'));
            modal.show();
        });
    });
    // Task3: search UX - Enter, clear x, debounce 350ms
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
