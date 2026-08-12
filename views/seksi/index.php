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
                    <i class="bi bi-building text-primary me-2"></i>Data Seksi
                </h2>
                <p class="text-muted mb-0">Kelola data seksi/divisi</p>
            </div>
            <a href="<?= base_url('seksi/create') ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Tambah Seksi
            </a>
        </div>
    </div>

    <!-- Sections Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($seksis)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>Belum ada data seksi</p>
                    <a href="<?= base_url('seksi/create') ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> Tambah Seksi Pertama
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">No</th>
                                <th width="15%">Kode Seksi</th>
                                <th width="50%">Nama Seksi</th>
                                <th width="15%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($seksis as $index => $seksi): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?= htmlspecialchars($seksi['kode_seksi']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($seksi['nama_seksi']) ?></td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a href="<?= base_url('seksi/edit/' . $seksi['id']) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="<?= base_url('seksi/delete/' . $seksi['id']) ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               title="Hapus"
                                               data-confirm-delete
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus seksi ini?')">
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

