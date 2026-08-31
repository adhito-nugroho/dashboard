<?php
/**
 * Partial: satu baris komponen biaya perjalanan dinas.
 * Variabel yang diharapkan: $idx (int|string), $d (array baris)
 *
 * Dipakai oleh:
 *  - views/spj/form.php  (loop foreach $details)
 *  - Template JS clone    (idx = '__IDX__')
 */
$dNama    = htmlspecialchars($d['nama_komponen'] ?? '');
$dHarga   = ($d['harga_satuan'] ?? '') !== '' ? number_format((float)($d['harga_satuan']), 0, ',', '.') : '';
$dHari    = $d['jumlah_hari'] ?? '';
$dJumlah  = ($d['jumlah'] ?? '') !== '' ? number_format((float)($d['jumlah']), 0, ',', '.') : '';
$dKet     = htmlspecialchars($d['keterangan'] ?? '');
?>
<div class="komponen-row row g-1 align-items-center">
    <!-- Nama Komponen -->
    <div class="col-12 col-md-3">
        <div class="komponen-label d-md-none">Nama Komponen</div>
        <input type="text"
               name="komponen[<?= $idx ?>][nama_komponen]"
               class="form-control spj-form-input row-nama"
               placeholder="Contoh: Uang Harian"
               value="<?= $dNama ?>"
               style="font-size:.85rem;">
    </div>
    <!-- Harga Satuan -->
    <div class="col-6 col-md-2">
        <div class="komponen-label d-md-none">Harga Satuan (Rp)</div>
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-light" style="font-size:.75rem;padding:.3rem .45rem;">Rp</span>
            <input type="text"
                   name="komponen[<?= $idx ?>][harga_satuan]"
                   class="form-control spj-form-input row-harga"
                   placeholder="0"
                   value="<?= $dHarga ?>"
                   inputmode="numeric"
                   style="font-size:.85rem;">
        </div>
    </div>
    <!-- Jumlah Hari -->
    <div class="col-6 col-md-1">
        <div class="komponen-label d-md-none">Hari / Unit</div>
        <input type="number"
               name="komponen[<?= $idx ?>][jumlah_hari]"
               class="form-control spj-form-input row-hari"
               placeholder="—"
               value="<?= htmlspecialchars((string)$dHari) ?>"
               min="0" step="0.5"
               style="font-size:.85rem;"
               title="Kosongkan jika tidak dihitung per hari">
    </div>
    <!-- Jumlah -->
    <div class="col-6 col-md-2">
        <div class="komponen-label d-md-none">Jumlah (Rp)</div>
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-light" style="font-size:.75rem;padding:.3rem .45rem;">Rp</span>
            <input type="text"
                   name="komponen[<?= $idx ?>][jumlah]"
                   class="form-control spj-form-input row-jumlah"
                   placeholder="0"
                   value="<?= $dJumlah ?>"
                   inputmode="numeric"
                   style="font-size:.85rem;"
                   title="Dihitung otomatis (Harga × Hari), atau isi manual">
        </div>
    </div>
    <!-- Keterangan -->
    <div class="col-6 col-md-3">
        <div class="komponen-label d-md-none">Keterangan</div>
        <input type="text"
               name="komponen[<?= $idx ?>][keterangan]"
               class="form-control spj-form-input row-ket"
               placeholder="Opsional…"
               value="<?= $dKet ?>"
               style="font-size:.85rem;">
    </div>
    <!-- Hapus -->
    <div class="col-12 col-md-1 text-end text-md-center">
        <button type="button" class="btn-remove-row" title="Hapus baris ini">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
</div>
