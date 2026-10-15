    <!-- Filter Panel -->
    <?php
        $activeFilterCount = (!empty($filters['seksi_id'])?1:0) + (!empty($filters['program_id'])?1:0) + (!empty($filters['kegiatan_id'])?1:0) + (!empty($filters['sub_kegiatan_id'])?1:0);
        $hasActiveFilter = $activeFilterCount > 0;
    ?>
    <div class="d-md-none mb-3"><button class="btn btn-primary w-100 rounded-3" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse"><i class="bi bi-sliders me-2"></i>Filter Data<?php if($hasActiveFilter): ?> <span style="background:#fff;color:#3b82f6;font-size:var(--fs-xs);font-weight:700;padding:0.1rem 0.45rem;border-radius:999px;margin-left:0.4rem;"><?= $activeFilterCount ?></span><?php endif; ?></button></div>
    <div class="collapse d-md-block mb-4 animate-fade-in-up delay-2" id="filterCollapse">
        <div class="card filter-card rounded-4" style="box-shadow:var(--shadow-sm);<?= $hasActiveFilter?'border:1.5px solid #3b82f6;':'' ?>">
            <div class="card-body" style="padding:1.25rem 1.5rem;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-sliders" style="color:var(--primary);font-size:var(--fs-md);"></i>
                        <span style="font-weight:700;font-size:var(--fs-base);color:var(--gray-700);">Filter Data</span>
                        <?php if($hasActiveFilter): ?>
                        <span style="font-size:var(--fs-xs);font-weight:700;padding:0.2rem 0.6rem;border-radius:999px;background:#3b82f6;color:#fff;">Filter Aktif: <?= $activeFilterCount ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if($hasActiveFilter): ?>
                    <button class="btn btn-sm" style="background:#fef2f2;color:#dc2626;font-size:var(--fs-sm);font-weight:600;border:1px solid #fecaca;border-radius:999px;padding:0.25rem 0.75rem;" onclick="resetFilters()"><i class="bi bi-x-circle-fill me-1"></i>Reset Filter</button>
                    <?php else: ?>
                    <button class="btn btn-sm" style="color:var(--gray-400);font-size:var(--fs-sm);" onclick="resetFilters()"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
                    <?php endif; ?>
                </div>
                <form method="GET" action="<?= base_url() ?>" id="mainFilterForm">
                    <input type="hidden" name="tahun" value="<?= $stats['tahun'] ?>">
                    <div class="row g-2">
                        <div class="col-md-3"><label for="seksi_id" class="form-label">Seksi</label><select class="form-select<?= !empty($filters['seksi_id'])?' border-primary':'' ?>" id="seksi_id" name="seksi_id" onchange="this.form.submit()" style="<?= !empty($filters['seksi_id'])?'border-color:#3b82f6;background-color:#eff6ff;box-shadow:0 0 0 1px #3b82f6;':'' ?>"><option value="">Semua Seksi</option><?php foreach($filterOptions['seksi'] as $s): ?><option value="<?= $s['id'] ?>" <?= ($filters['seksi_id']??'')==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['nama_seksi']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-3"><label for="program_id" class="form-label">Program</label><select class="form-select" id="program_id" name="program_id" onchange="this.form.submit()" style="<?= !empty($filters['program_id'])?'border-color:#3b82f6;background-color:#eff6ff;box-shadow:0 0 0 1px #3b82f6;':'' ?>"><option value="">Semua Program</option><?php foreach($filterOptions['program'] as $p): ?><option value="<?= $p['id'] ?>" <?= ($filters['program_id']??'')==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['kode_program'].' - '.$p['nama_program']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-3"><label for="kegiatan_id" class="form-label">Kegiatan</label><select class="form-select" id="kegiatan_id" name="kegiatan_id" onchange="this.form.submit()" style="<?= !empty($filters['kegiatan_id'])?'border-color:#3b82f6;background-color:#eff6ff;box-shadow:0 0 0 1px #3b82f6;':'' ?>"><option value="">Semua Kegiatan</option><?php foreach($filterOptions['kegiatan'] as $k): ?><option value="<?= $k['id'] ?>" <?= ($filters['kegiatan_id']??'')==$k['id']?'selected':'' ?>><?= htmlspecialchars($k['kode_kegiatan'].' - '.$k['nama_kegiatan']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-3"><label for="sub_kegiatan_id" class="form-label">Sub Kegiatan</label><select class="form-select" id="sub_kegiatan_id" name="sub_kegiatan_id" onchange="this.form.submit()" style="<?= !empty($filters['sub_kegiatan_id'])?'border-color:#3b82f6;background-color:#eff6ff;box-shadow:0 0 0 1px #3b82f6;':'' ?>"><option value="">Semua Sub Kegiatan</option><?php foreach($filterOptions['sub_kegiatan'] as $sk): ?><option value="<?= $sk['id'] ?>" <?= ($filters['sub_kegiatan_id']??'')==$sk['id']?'selected':'' ?>><?= htmlspecialchars($sk['kode_sub_kegiatan'].' - '.$sk['nama_sub_kegiatan']) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <?php if($hasActiveFilter): ?>
                    <div class="mt-2 d-flex flex-wrap gap-1 align-items-center">
                        <span style="font-size:var(--fs-xs);color:#475569;font-weight:600;margin-right:0.25rem;">Menampilkan data untuk:</span>
                        <?php if(!empty($filters['seksi_id'])): ?><span class="filter-chip" style="background:#eff6ff;border-color:#bfdbfe;color:#1e40af;"><i class="bi bi-building"></i>Seksi</span><?php endif; ?>
                        <?php if(!empty($filters['program_id'])): ?><span class="filter-chip" style="background:#eff6ff;border-color:#bfdbfe;color:#1e40af;"><i class="bi bi-folder"></i>Program</span><?php endif; ?>
                        <?php if(!empty($filters['kegiatan_id'])): ?><span class="filter-chip" style="background:#eff6ff;border-color:#bfdbfe;color:#1e40af;"><i class="bi bi-list-task"></i>Kegiatan</span><?php endif; ?>
                        <?php if(!empty($filters['sub_kegiatan_id'])): ?><span class="filter-chip" style="background:#eff6ff;border-color:#bfdbfe;color:#1e40af;"><i class="bi bi-list-nested"></i>Sub Kegiatan</span><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

