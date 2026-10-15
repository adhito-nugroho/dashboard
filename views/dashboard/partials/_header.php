<div class="container-fluid" style="padding: 1.75rem 1.5rem;">
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 animate-fade-in-up">
        <div>
            <h2 style="font-weight:800;letter-spacing:-0.03em;color:var(--gray-900);font-size:var(--fs-xl);margin-bottom:0.2rem;">
                <i class="bi bi-speedometer2 me-2" style="color:var(--primary);"></i>Dashboard
            </h2>
            <p style="color:var(--gray-400);font-size:var(--fs-base);margin:0;">Monitoring Realisasi Anggaran Cabang Dinas Kehutanan Wilayah Bojonegoro &mdash; Tahun <strong style="color:var(--gray-600);"><?= $stats['tahun'] ?></strong></p>
        </div>
        <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
        <form method="GET" action="<?= base_url() ?>" class="" style="background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-full);padding:0.35rem 0.5rem 0.35rem 1rem;display:inline-flex;align-items:center;gap:0.5rem;box-shadow:var(--shadow-xs);">
            <i class="bi bi-calendar3" style="color:var(--primary);font-size:var(--fs-md);"></i>
            <select class="form-select form-select-sm border-0 bg-transparent fw-bold py-0" id="tahun" name="tahun" onchange="this.form.submit()" style="width:auto;cursor:pointer;box-shadow:none;color:var(--primary);font-size:var(--fs-base);">
                <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                <option value="<?= $y ?>" <?= $stats['tahun'] == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
        <button type="button" class="btn btn-sm fw-semibold btn-export-global"
           data-bs-toggle="modal" data-bs-target="#modalExportExcel"
           data-bs-toggle="tooltip" data-bs-placement="bottom" title="Export laporan lengkap semua section ke Excel"
           style="background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;border-radius:var(--radius-full);padding:0.4rem 1.1rem;font-size:var(--fs-sm, 0.75rem);box-shadow:0 2px 8px rgba(22,163,74,.3);display:inline-flex;align-items:center;gap:0.4rem;border:none;cursor:pointer;">
            <i class="bi bi-file-earmark-excel-fill"></i> Export Excel
        </button>
        </div>
    </div>

