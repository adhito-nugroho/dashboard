<?php
// Start session for flash messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get flash message
$flashMessage = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>

<div class="container-fluid py-4">
    <!-- Flash Message -->
    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : ($flashType === 'success' ? 'success' : 'info') ?> alert-dismissible fade show" role="alert" data-auto-dismiss>
            <?= htmlspecialchars($flashMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="page-header mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1">
                    <i class="bi bi-cash-stack text-primary me-2"></i>Data Pagu
                </h2>
                <p class="text-muted mb-0">Kelola alokasi anggaran tahunan</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= base_url('pagu/laporan?tahun=' . (int)($_GET['tahun'] ?? date('Y'))) ?>" class="btn btn-outline-info">
                    <i class="bi bi-clipboard-data me-1"></i> Laporan APBD vs PAPBD
                </a>
                <a href="<?= base_url('pagu/create') ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i> Tambah Pagu
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($pagus)): ?>
        <!-- Summary Cards: APBD awal vs PAPBD -->
        <div class="row mb-4 g-3">
            <div class="col-md-6 col-lg-3">
                <div class="card stat-card border-secondary" style="border-left-width: 4px;">
                    <div class="card-body">
                        <p class="text-muted mb-2 small text-uppercase fw-semibold">Total APBD Awal</p>
                        <h4 class="mb-0 fw-bold">Rp <?= number_format($totalPaguAwal ?? $totalPaguKeseluruhan ?? 0, 0, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card stat-card border-primary" style="border-left-width: 4px;">
                    <div class="card-body">
                        <p class="text-muted mb-2 small text-uppercase fw-semibold">Total PAPBD (Saat Ini)</p>
                        <h4 class="mb-0 fw-bold text-primary">Rp <?= number_format($totalPaguKeseluruhan ?? 0, 0, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card stat-card border-<?= (($totalPaguKeseluruhan ?? 0) - ($totalPaguAwal ?? 0)) >= 0 ? 'success' : 'danger' ?>" style="border-left-width: 4px;">
                    <div class="card-body">
                        <p class="text-muted mb-2 small text-uppercase fw-semibold">Selisih</p>
                        <h4 class="mb-0 fw-bold">Rp <?= number_format(($totalPaguKeseluruhan ?? 0) - ($totalPaguAwal ?? 0), 0, ',', '.') ?></h4>
                        <?php if (!empty($ringkasanPerubahan['jumlah_perubahan'])): ?>
                            <small class="text-muted"><?= (int) $ringkasanPerubahan['jumlah_perubahan'] ?>x perubahan di <?= (int) $ringkasanPerubahan['jumlah_rekening_berubah'] ?> rekening</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card stat-card border-warning" style="border-left-width: 4px;">
                    <div class="card-body">
                        <p class="text-muted mb-2 small text-uppercase fw-semibold">Filter Tahun</p>
                        <form method="GET" action="<?= base_url('pagu') ?>" class="d-flex gap-2">
                            <input type="number" name="tahun" class="form-control" min="2000" max="2100" value="<?= htmlspecialchars($_GET['tahun'] ?? '') ?>" placeholder="<?= date('Y') ?>">
                            <button class="btn btn-outline-primary" type="submit">Tampil</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>



    <!-- Budget Allocations Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($pagus)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>Belum ada data pagu</p>
                    <a href="<?= base_url('pagu/create') ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> Tambah Pagu Pertama
                    </a>
                </div>
            <?php else: ?>
                <!-- Search Box -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" 
                                   class="form-control" 
                                   id="searchInput" 
                                   placeholder="Cari berdasarkan kode rekening, nama rekening, program, kegiatan, atau sub kegiatan..."
                                   autocomplete="off">
                        </div>
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> Ketik untuk mencari data secara real-time
                        </small>
                    </div>
                    <div class="col-md-6 text-end">
                        <span id="searchInfo" class="text-muted me-2"></span>
                        <?php if (!empty($pagination) && $pagination['totalPages'] > 1): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="showAllBtn" onclick="window.location.href='<?= base_url('pagu') ?>?show_all=1'">
                                <i class="bi bi-eye"></i> Tampilkan Semua Data
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="3%">No</th>
                                <th width="7%">Program</th>
                                <th width="7%">Kegiatan</th>
                                <th width="14%">Sub Kegiatan</th>
                                <th width="9%">Kode Rekening</th>
                                <th width="16%">Nama Rekening</th>
                                <th width="10%" class="text-end">APBD Awal</th>
                                <th width="10%" class="text-end">PAPBD</th>
                                <th width="9%" class="text-end">Selisih</th>
                                <th width="9%" class="text-end">Sisa</th>
                                <th width="6%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagus as $index => $pagu): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <span class="badge bg-info"><?= htmlspecialchars($pagu['kode_program']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= htmlspecialchars($pagu['kode_kegiatan']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($pagu['nama_sub_kegiatan']) ?></td>
                                    <td>
                                        <span class="badge bg-warning text-dark"><?= htmlspecialchars($pagu['kode_rekening']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($pagu['nama_rekening']) ?></td>
                                    <td class="text-end text-muted">
                                        Rp <?= number_format($pagu['pagu_awal'] ?? $pagu['nilai_pagu'], 0, ',', '.') ?>
                                    </td>
                                    <td class="text-end">
                                        <strong>Rp <?= number_format($pagu['nilai_pagu'], 0, ',', '.') ?></strong>
                                        <?php if (!empty($pagu['over_realisasi'])): ?>
                                            <br><span class="badge bg-danger" title="Realisasi melebihi pagu">Over</span>
                                        <?php elseif (($pagu['status_ubah'] ?? 'tetap') === 'tambah'): ?>
                                            <br><span class="badge bg-success">+ Tambah</span>
                                        <?php elseif (($pagu['status_ubah'] ?? 'tetap') === 'kurang'): ?>
                                            <br><span class="badge bg-warning text-dark">− Kurang</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php $sel = (float) ($pagu['selisih'] ?? 0); ?>
                                        <span class="<?= $sel > 0 ? 'text-success' : ($sel < 0 ? 'text-danger' : 'text-muted') ?>">
                                            <?= $sel > 0 ? '+' : '' ?>Rp <?= number_format($sel, 0, ',', '.') ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?php $sisa = (float) ($pagu['sisa_baru'] ?? ($pagu['nilai_pagu'] - ($pagu['realisasi'] ?? 0))); ?>
                                        <span class="<?= $sisa < 0 ? 'text-danger fw-bold' : '' ?>">Rp <?= number_format($sisa, 0, ',', '.') ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a href="<?= base_url('pagu/riwayat/' . $pagu['id']) ?>" class="btn btn-sm btn-outline-info" title="Riwayat PAPBD">
                                                <i class="bi bi-clock-history"></i>
                                            </a>
                                            <a href="<?= base_url('pagu/edit/' . $pagu['id']) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="<?= base_url('pagu/delete/' . $pagu['id']) ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               title="Hapus"
                                               data-confirm-delete
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus pagu ini?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($pagination) && $pagination['totalPages'] > 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= $pagination['page'] <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $pagination['page'] <= 1 ? '#' : $pagination['baseUrl'] . '?page=' . ($pagination['page'] - 1) ?>">«</a>
                            </li>
                            <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
                                <li class="page-item <?= $p == $pagination['page'] ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $pagination['baseUrl'] . '?page=' . $p ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $pagination['page'] >= $pagination['totalPages'] ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $pagination['page'] >= $pagination['totalPages'] ? '#' : $pagination['baseUrl'] . '?page=' . ($pagination['page'] + 1) ?>">»</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Real-time search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const searchInfo = document.getElementById('searchInfo');
    const table = document.querySelector('.table tbody');
    const pagination = document.querySelector('.pagination');
    const rows = table ? Array.from(table.querySelectorAll('tr')) : [];
    const totalRows = rows.length;
    
    if (searchInput && rows.length > 0) {
        // Initialize search info
        updateSearchInfo(totalRows, totalRows);
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            let visibleCount = 0;
            
            // Hide pagination when searching
            if (pagination) {
                pagination.style.display = searchTerm.length > 0 ? 'none' : '';
            }
            
            rows.forEach(row => {
                // Skip the "no results" row if it exists
                if (row.id === 'noResultsRow') {
                    return;
                }
                
                const text = row.textContent.toLowerCase();
                const isMatch = searchTerm.length === 0 || text.includes(searchTerm);
                
                if (isMatch) {
                    row.style.display = '';
                    visibleCount++;
                    
                    // Highlight matching text
                    if (searchTerm.length > 0) {
                        row.classList.add('table-warning');
                    } else {
                        row.classList.remove('table-warning');
                    }
                } else {
                    row.style.display = 'none';
                    row.classList.remove('table-warning');
                }
            });
            
            // Update search info
            updateSearchInfo(visibleCount, totalRows);
            
            // Show "no results" message if needed
            showNoResultsMessage(visibleCount);
        });
        
        // Clear search on Escape key
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                this.dispatchEvent(new Event('input'));
                this.blur();
            }
        });
        
        // Focus search on Ctrl+F or Cmd+F
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            }
        });
    }
    
    function updateSearchInfo(visible, total) {
        if (searchInfo) {
            if (visible === total) {
                searchInfo.textContent = `Menampilkan ${total} data`;
            } else {
                searchInfo.innerHTML = `<i class="bi bi-funnel"></i> Menampilkan <strong>${visible}</strong> dari ${total} data`;
            }
        }
    }
    
    function showNoResultsMessage(visibleCount) {
        const tbody = document.querySelector('.table tbody');
        let noResultsRow = document.getElementById('noResultsRow');
        
        if (visibleCount === 0) {
            if (!noResultsRow) {
                noResultsRow = document.createElement('tr');
                noResultsRow.id = 'noResultsRow';
                noResultsRow.innerHTML = `
                    <td colspan="8" class="text-center py-4">
                        <i class="bi bi-search fs-1 text-muted"></i>
                        <p class="text-muted mt-2 mb-0">Tidak ada data yang sesuai dengan pencarian</p>
                        <small class="text-muted">Coba gunakan kata kunci yang berbeda</small>
                    </td>
                `;
                tbody.appendChild(noResultsRow);
            }
            noResultsRow.style.display = '';
        } else {
            if (noResultsRow) {
                noResultsRow.style.display = 'none';
            }
        }
    }
});
</script>


