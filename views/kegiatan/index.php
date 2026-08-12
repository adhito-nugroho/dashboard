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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0">Data Kegiatan</h2>
            <p class="text-muted mb-0">
                Kelola data kegiatan anggaran
                <?php if (!empty($kegiatans)): ?>
                    <?php 
                    $tahunList = array_unique(array_column($kegiatans, 'tahun'));
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
        <a href="<?= base_url('kegiatan/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Tambah Kegiatan
        </a>
    </div>

    <!-- Activities Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($kegiatans)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>Belum ada data kegiatan</p>
                    <a href="<?= base_url('kegiatan/create') ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> Tambah Kegiatan Pertama
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="3%">No</th>
                                <th width="25%">Program</th>
                                <th width="47%">Kegiatan</th>
                                <th width="25%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $pagination = $pagination ?? ['page' => 1, 'perPage' => 10];
                            $currentProgram = null;
                            $currentKegiatan = null;
                            $rowNum = 0;
                            foreach ($kegiatans as $index => $kegiatan): 
                                $rowNum++;
                                $isNewProgram = ($currentProgram !== $kegiatan['program_id']);
                                
                                // Kegiatan baru jika program baru ATAU kegiatan berbeda
                                $isNewKegiatan = $isNewProgram || ($currentKegiatan !== $kegiatan['id']);
                                
                                if ($isNewProgram) {
                                    $currentProgram = $kegiatan['program_id'];
                                    $currentKegiatan = null;
                                }
                                if ($isNewKegiatan) {
                                    $currentKegiatan = $kegiatan['id'];
                                }
                            ?>
                                <tr class="<?= $isNewProgram ? 'table-group-separator' : '' ?>">
                                    <td class="text-muted fw-normal"><?= $rowNum + (($pagination['page'] ?? 1) - 1) * ($pagination['perPage'] ?? 10) ?></td>
                                    <td class="<?= !$isNewProgram ? 'table-cell-empty' : '' ?>">
                                        <?php if ($isNewProgram): ?>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-diagram-3-fill text-info me-2" style="font-size: 1.1rem;"></i>
                                                <div>
                                                    <span class="badge bg-info mb-1"><?= htmlspecialchars($kegiatan['kode_program']) ?></span>
                                                    <div class="small text-muted mt-1" style="line-height: 1.3;">
                                                        <?= htmlspecialchars($kegiatan['nama_program']) ?>
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
                                                    <span class="badge bg-primary mb-1"><?= htmlspecialchars($kegiatan['kode_kegiatan']) ?></span>
                                                    <div class="fw-medium mt-1" style="line-height: 1.4; color: #1e293b;">
                                                        <?= htmlspecialchars($kegiatan['nama_kegiatan']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a href="<?= base_url('kegiatan/edit/' . $kegiatan['id']) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="<?= base_url('kegiatan/delete/' . $kegiatan['id']) ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               title="Hapus"
                                               data-confirm-delete
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus kegiatan ini?')">
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

