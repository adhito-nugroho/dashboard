<?php
// Define custom tree-table CSS if not already in style.css
$additionalCSS[] = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css';
?>
<style>
.tree-table { width: 100%; border-collapse: collapse; }
.tree-table th, .tree-table td { padding: 10px; border: 1px solid #dee2e6; }
.tree-table thead th { background-color: #f8f9fa; position: sticky; top: 0; z-index: 10; font-weight: 600; text-align: center; vertical-align: middle; }
.tree-row { cursor: pointer; transition: background-color 0.2s; }
.tree-row:hover { background-color: #f1f5f9; }
.level-kegiatan { font-weight: bold; background-color: #e2e8f0; }
.level-subkegiatan { font-weight: bold; background-color: #f1f5f9; }
.level-rekening { background-color: #ffffff; }

.toggle-icon {
    display: inline-block;
    width: 24px;
    height: 24px;
    line-height: 24px;
    text-align: center;
    border-radius: 50%;
    background-color: rgba(0,0,0,0.05);
    margin-right: 8px;
    transition: transform 0.3s;
}
.tree-row[aria-expanded="true"] .toggle-icon { transform: rotate(90deg); }
.tree-row.collapsed .toggle-icon { transform: rotate(0deg); }

.indent-1 { padding-left: 1rem !important; }
.indent-2 { padding-left: 2.5rem !important; }
.indent-3 { padding-left: 4rem !important; }

.text-right { text-align: right; }
.month-col { min-width: 100px; }
.total-col { font-weight: bold; background-color: #f8f9fa; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0 text-gray-800">Rekap RAK</h1>
    
    <form method="GET" class="d-flex gap-2">
        <select name="tahun" class="form-select" onchange="this.form.submit()">
            <?php 
            $currentYear = date('Y');
            $selectedYear = $filterTahun ?? $currentYear;
            for($y = $currentYear - 2; $y <= $currentYear + 2; $y++): ?>
                <option value="<?= $y ?>" <?= $y == $selectedYear ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
        <button type="submit" class="btn btn-primary d-none">Filter</button>
    </form>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Rekapitulasi RAK Tahun <?= htmlspecialchars($selectedYear) ?></h6>
        <button class="btn btn-sm btn-outline-secondary" onclick="toggleAll()">
            <i class="bi bi-arrows-expand"></i> Expand/Collapse All
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
            <table class="tree-table table-hover">
                <thead>
                    <tr>
                        <th rowspan="2" style="min-width: 350px; text-align: left;">Kode - Uraian</th>
                        <th colspan="12">Bulan</th>
                        <th rowspan="2" class="total-col" style="min-width: 150px;">Total</th>
                    </tr>
                    <tr>
                        <?php for($i=1; $i<=12; $i++): ?>
                            <th class="month-col"><?= str_pad($i, 2, '0', STR_PAD_LEFT) ?></th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rekap)): ?>
                        <tr>
                            <td colspan="14" class="text-center py-4">Data RAK tidak ditemukan untuk tahun <?= htmlspecialchars($selectedYear) ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($rekap as $kId => $kegiatan): ?>
                            <!-- Kegiatan Row -->
                            <tr class="tree-row level-kegiatan collapsed" data-bs-toggle="collapse" data-bs-target=".kegiatan-<?= $kId ?>-children" aria-expanded="false">
                                <td class="indent-1">
                                    <span class="toggle-icon"><i class="bi bi-chevron-right"></i></span>
                                    <?= htmlspecialchars($kegiatan['kode_kegiatan'] . ' - ' . $kegiatan['nama_kegiatan']) ?>
                                </td>
                                <?php for($i=1; $i<=12; $i++): ?>
                                    <td class="text-right"><?= number_format($kegiatan['months'][$i], 0, ',', '.') ?></td>
                                <?php endfor; ?>
                                <td class="text-right total-col"><?= number_format($kegiatan['total'], 0, ',', '.') ?></td>
                            </tr>
                            
                            <?php foreach($kegiatan['sub_kegiatan'] as $skId => $subkeg): ?>
                                <!-- Sub Kegiatan Row -->
                                <tr class="tree-row level-subkegiatan collapse kegiatan-<?= $kId ?>-children collapsed" data-bs-toggle="collapse" data-bs-target=".subkegiatan-<?= $skId ?>-children" aria-expanded="false">
                                    <td class="indent-2">
                                        <span class="toggle-icon"><i class="bi bi-chevron-right"></i></span>
                                        <?= htmlspecialchars($subkeg['kode_sub_kegiatan'] . ' - ' . $subkeg['nama_sub_kegiatan']) ?>
                                    </td>
                                    <?php for($i=1; $i<=12; $i++): ?>
                                        <td class="text-right"><?= number_format($subkeg['months'][$i], 0, ',', '.') ?></td>
                                    <?php endfor; ?>
                                    <td class="text-right total-col"><?= number_format($subkeg['total'], 0, ',', '.') ?></td>
                                </tr>
                                
                                <?php foreach($subkeg['rekening'] as $rId => $rek): ?>
                                    <!-- Rekening Row -->
                                    <tr class="level-rekening collapse kegiatan-<?= $kId ?>-children subkegiatan-<?= $skId ?>-children">
                                        <td class="indent-3">
                                            <span style="display:inline-block; width:32px;"></span>
                                            <?= htmlspecialchars($rek['kode_rekening'] . ' - ' . $rek['nama_rekening']) ?>
                                        </td>
                                        <?php for($i=1; $i<=12; $i++): ?>
                                            <td class="text-right text-muted"><?= number_format($rek['months'][$i], 0, ',', '.') ?></td>
                                        <?php endfor; ?>
                                        <td class="text-right total-col"><?= number_format($rek['total'], 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        
                        <!-- Grand Total Row -->
                        <tr style="font-weight: bold; background-color: #d1ecf1;">
                            <td class="text-center">GRAND TOTAL</td>
                            <?php for($i=1; $i<=12; $i++): ?>
                                <td class="text-right"><?= number_format($monthTotals[$i], 0, ',', '.') ?></td>
                            <?php endfor; ?>
                            <td class="text-right total-col"><?= number_format($grandTotal, 0, ',', '.') ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let expandedAll = false;
function toggleAll() {
    expandedAll = !expandedAll;
    
    // Select only collapsible rows
    const toggles = document.querySelectorAll('.tree-row[data-bs-toggle="collapse"]');
    toggles.forEach(row => {
        const targetSelector = row.getAttribute('data-bs-target');
        const targets = document.querySelectorAll(targetSelector);
        
        if (expandedAll) {
            row.classList.remove('collapsed');
            row.setAttribute('aria-expanded', 'true');
            targets.forEach(t => t.classList.add('show'));
        } else {
            row.classList.add('collapsed');
            row.setAttribute('aria-expanded', 'false');
            targets.forEach(t => t.classList.remove('show'));
        }
    });

    // We also need to add 'show' to nested elements to keep the display correctly synced with bootstrap's collapse,
    // though bootstrap collapse event handlers handle their own state. Direct DOM manipulation might interfere with BS,
    // so let's use the BS API safely.
}

document.addEventListener('DOMContentLoaded', () => {
    // Custom handling for nested collapses so expanding a parent doesn't auto-expand children
    // Bootstrap sometimes expands nested elements by default if we add .show, let's keep it clean
    const collapseElements = document.querySelectorAll('.collapse');
    
    // When a kegiatan is mapped, we might need to toggle it.
});
</script>
