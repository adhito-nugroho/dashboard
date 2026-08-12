<?php
// Start session for flash messages (reuse if needed later)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get flash message (for future use; currently no CRUD actions)
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
                    <i class="bi bi-wallet2 text-primary me-2"></i>Data Rekening
                </h2>
                <p class="text-muted mb-0">
                    Daftar rekening per sub kegiatan
                    <?php if (!empty($rekenings)): ?>
                        <?php 
                        $tahunList = array_unique(array_column($rekenings, 'tahun'));
                        sort($tahunList);
                        if (count($tahunList) > 0):
                        ?>
                            <span class="ms-2">
                                <span class="badge bg-warning text-dark">
                                    <i class="bi bi-calendar3 me-1"></i>
                                    Tahun: <?= implode(', ', $tahunList) ?>
                                </span>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
            </div>
            <a href="<?= base_url('rekening/create') ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Tambah Rekening
            </a>
        </div>
    </div>

    <!-- Accounts Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($rekenings)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>Belum ada data rekening</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="3%">No</th>
                                <th width="20%">Hierarki Program</th>
                                <th width="20%">Hierarki Kegiatan</th>
                                <th width="20%">Sub Kegiatan</th>
                                <th width="27%">Rekening</th>
                                <th width="10%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $pagination = $pagination ?? ['page' => 1, 'perPage' => 10];
                            $currentProgram = null;
                            $currentKegiatan = null;
                            $currentSubKegiatan = null;
                            $rowNum = 0;
                            foreach ($rekenings as $index => $rekening): 
                                $rowNum++;
                                $isNewProgram = ($currentProgram !== $rekening['program_id']);
                                
                                // Kegiatan baru jika program baru ATAU kegiatan berbeda
                                $isNewKegiatan = $isNewProgram || ($currentKegiatan !== $rekening['kegiatan_id']);
                                
                                // Sub kegiatan baru jika program/kegiatan baru ATAU sub kegiatan berbeda
                                $isNewSubKegiatan = $isNewProgram || $isNewKegiatan || ($currentSubKegiatan !== $rekening['sub_kegiatan_id']);
                                
                                if ($isNewProgram) {
                                    $currentProgram = $rekening['program_id'];
                                    $currentKegiatan = null;
                                    $currentSubKegiatan = null;
                                }
                                if ($isNewKegiatan) {
                                    $currentKegiatan = $rekening['kegiatan_id'];
                                    $currentSubKegiatan = null;
                                }
                                if ($isNewSubKegiatan) {
                                    $currentSubKegiatan = $rekening['sub_kegiatan_id'];
                                }
                            ?>
                                <tr class="<?= $isNewProgram ? 'table-group-separator' : '' ?>">
                                    <td class="text-muted fw-normal"><?= $rowNum + (($pagination['page'] ?? 1) - 1) * ($pagination['perPage'] ?? 10) ?></td>
                                    <td class="<?= !$isNewProgram ? 'table-cell-empty' : '' ?>">
                                        <?php if ($isNewProgram): ?>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-diagram-3-fill text-info me-2" style="font-size: 1.1rem;"></i>
                                                <div>
                                                    <span class="badge bg-info mb-1"><?= htmlspecialchars($rekening['kode_program']) ?></span>
                                                    <div class="small text-muted mt-1" style="line-height: 1.3;">
                                                        <?= htmlspecialchars($rekening['nama_program']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="<?= !$isNewKegiatan ? 'table-cell-empty' : '' ?>">
                                        <?php if ($isNewKegiatan): ?>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-diagram-2-fill text-primary me-2" style="font-size: 1rem;"></i>
                                                <div>
                                                    <span class="badge bg-primary mb-1"><?= htmlspecialchars($rekening['kode_kegiatan']) ?></span>
                                                    <div class="small text-muted mt-1" style="line-height: 1.3;">
                                                        <?= htmlspecialchars($rekening['nama_kegiatan']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="<?= !$isNewSubKegiatan ? 'table-cell-empty' : '' ?>">
                                        <?php if ($isNewSubKegiatan): ?>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-diagram-2 text-success me-2" style="font-size: 0.9rem;"></i>
                                                <div>
                                                    <span class="badge bg-success mb-1"><?= htmlspecialchars($rekening['kode_sub_kegiatan']) ?></span>
                                                    <div class="small text-muted mt-1" style="line-height: 1.3;">
                                                        <?= htmlspecialchars($rekening['nama_sub_kegiatan']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <span class="badge bg-warning text-dark mb-1"><?= htmlspecialchars($rekening['kode_rekening']) ?></span>
                                            <div class="fw-medium mt-1" style="line-height: 1.4; color: #1e293b;">
                                                <?= htmlspecialchars($rekening['nama_rekening']) ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a href="<?= base_url('rekening/edit/' . $rekening['id']) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="<?= base_url('rekening/delete/' . $rekening['id']) ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               title="Hapus"
                                               data-confirm-delete
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus rekening ini?')">
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


