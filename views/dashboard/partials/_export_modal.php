
</div>

<!-- ========== Modal Export Excel ========== -->
<div class="modal fade" id="modalExportExcel" tabindex="-1" aria-labelledby="modalExportLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4" style="border:none;box-shadow:0 20px 60px rgba(0,0,0,.15);">

      <!-- Header -->
      <div class="modal-header border-0 pb-0" style="background:linear-gradient(135deg,#16a34a,#15803d);border-radius:1rem 1rem 0 0;padding:1.25rem 1.5rem;">
        <div class="d-flex align-items-center gap-2">
          <div style="width:38px;height:38px;background:rgba(255,255,255,.2);border-radius:8px;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-file-earmark-excel-fill" style="color:#fff;font-size:var(--fs-lg);"></i>
          </div>
          <div>
            <h5 class="modal-title mb-0" id="modalExportLabel" style="color:#fff;font-weight:700;font-size:var(--fs-md);">Export Laporan Excel</h5>
            <p class="mb-0" style="color:rgba(255,255,255,.8);font-size:var(--fs-sm);">Semua laporan Excel dalam satu tempat — pilih jenisnya</p>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="opacity:.8;"></button>
      </div>

      <!-- Body -->
      <div class="modal-body" style="padding:1.5rem;">
        <form id="formExportExcel">
          <div class="row g-3">

            <!-- Jenis Laporan -->
            <div class="col-12">
              <label class="form-label fw-semibold" for="exportJenis" style="font-size:var(--fs-base);color:#374151;"><i class="bi bi-file-earmark-excel me-1" style="color:#16a34a;"></i>Jenis Laporan</label>
              <select class="form-select" id="exportJenis" name="jenis">
                <optgroup label="Laporan (format resmi)">
                  <option value="laporan">Laporan Realisasi Anggaran</option>
                  <option value="bulanan">Serapan per Sub Kegiatan per Bulan</option>
                  <option value="triwulan">Serapan per Sub Kegiatan per Triwulan</option>
                  <option value="semester">Sisa Dana per Semester</option>
                  <option value="rak">RAK (Rencana Anggaran Kas)</option>
                </optgroup>
                <optgroup label="Tabel halaman ini (langsung unduh)">
                  <option value="detail-bulan">Detail Penyerapan per Bulan</option>
                  <option value="deviasi">Deviasi RAK</option>
                  <option value="breakdown">Ringkasan Serapan per Unit</option>
                </optgroup>
              </select>
            </div>

            <!-- Tahun -->
            <div class="col-6" id="exportTahunWrap">
              <label class="form-label fw-semibold" style="font-size:var(--fs-base);color:#374151;"><i class="bi bi-calendar-year me-1" style="color:#16a34a;"></i>Tahun</label>
              <select class="form-select" id="exportTahun" name="tahun">
                <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                <option value="<?= $y ?>" <?= $stats['tahun'] == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
              </select>
            </div>

            <!-- Bulan Sampai (khusus Laporan Realisasi) -->
            <div class="col-6" id="exportBulanWrap">
              <label class="form-label fw-semibold" style="font-size:var(--fs-base);color:#374151;"><i class="bi bi-calendar-month me-1" style="color:#16a34a;"></i>Realisasi s/d Bulan</label>
              <select class="form-select" id="exportBulan" name="bulan">
                <?php
                $bulanList = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                              7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
                foreach ($bulanList as $num => $nama):
                ?>
                <option value="<?= $num ?>" <?= $num == (int)date('n') ? 'selected' : '' ?>><?= $nama ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Grouping (khusus Laporan Realisasi) -->
            <div class="col-12" id="exportGroupingWrap">
              <label class="form-label fw-semibold" style="font-size:var(--fs-base);color:#374151;"><i class="bi bi-diagram-3 me-1" style="color:#16a34a;"></i>Pengelompokan Baris</label>
              <div class="d-flex flex-column gap-2">
                <div class="form-check" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:0.6rem 1rem 0.6rem 2.25rem;">
                  <input class="form-check-input" type="radio" name="grouping" id="exportGroupingProgram" value="program" checked>
                  <label class="form-check-label fw-semibold" for="exportGroupingProgram" style="font-size:var(--fs-base);color:#1e293b;">
                    Hierarki Program
                    <span class="d-block fw-normal" style="font-size:var(--fs-sm);color:#64748b;">Program &rarr; Kegiatan &rarr; Sub Kegiatan &rarr; Rekening (seperti biasa)</span>
                  </label>
                </div>
                <div class="form-check" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:0.6rem 1rem 0.6rem 2.25rem;">
                  <input class="form-check-input" type="radio" name="grouping" id="exportGroupingSeksi" value="seksi">
                  <label class="form-check-label fw-semibold" for="exportGroupingSeksi" style="font-size:var(--fs-base);color:#1e293b;">
                    Grouping per Seksi
                    <span class="d-block fw-normal" style="font-size:var(--fs-sm);color:#64748b;">Seksi &rarr; Program &rarr; Kegiatan &rarr; Sub Kegiatan &rarr; Rekening</span>
                  </label>
                </div>
              </div>
            </div>

            <!-- Filter Seksi (hanya dipakai saat grouping per seksi) -->
            <div class="col-12" id="exportSeksiWrap" style="display:none;">
              <label class="form-label fw-semibold" for="exportSeksi" style="font-size:var(--fs-base);color:#374151;"><i class="bi bi-building me-1" style="color:#16a34a;"></i>Seksi</label>
              <select class="form-select" id="exportSeksi" name="seksi_id">
                <option value="">Semua Seksi</option>
                <?php foreach(($filterOptions['seksi'] ?? []) as $s): ?>
                <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars(($s['kode_seksi'] ?? '') !== '' ? $s['kode_seksi'] . ' — ' . $s['nama_seksi'] : $s['nama_seksi']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Filter wilayah data (khusus Serapan Bulanan / Triwulan / Sisa Semester) -->
            <div class="col-12" id="exportFilterWrap" style="display:none;">
              <div class="row g-3">
                <div class="col-6">
                  <label class="form-label fw-semibold" for="exportFSeksi" style="font-size:var(--fs-base);color:#374151;">Seksi</label>
                  <select class="form-select" id="exportFSeksi">
                    <option value="">Semua Seksi</option>
                    <?php foreach(($filterOptions['seksi'] ?? []) as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= ($filters['seksi_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nama_seksi']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-6">
                  <label class="form-label fw-semibold" for="exportFProgram" style="font-size:var(--fs-base);color:#374151;">Program</label>
                  <select class="form-select" id="exportFProgram">
                    <option value="">Semua Program</option>
                    <?php foreach(($filterOptions['program'] ?? []) as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= ($filters['program_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars(($p['kode_program'] ?? '') . ' - ' . ($p['nama_program'] ?? '')) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-6">
                  <label class="form-label fw-semibold" for="exportFKegiatan" style="font-size:var(--fs-base);color:#374151;">Kegiatan</label>
                  <select class="form-select" id="exportFKegiatan">
                    <option value="">Semua Kegiatan</option>
                    <?php foreach(($filterOptions['kegiatan'] ?? []) as $k): ?>
                    <option value="<?= (int) $k['id'] ?>" <?= ($filters['kegiatan_id'] ?? '') == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars(($k['kode_kegiatan'] ?? '') . ' - ' . ($k['nama_kegiatan'] ?? '')) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-6">
                  <label class="form-label fw-semibold" for="exportFSub" style="font-size:var(--fs-base);color:#374151;">Sub Kegiatan</label>
                  <select class="form-select" id="exportFSub">
                    <option value="">Semua Sub Kegiatan</option>
                    <?php foreach(($filterOptions['sub_kegiatan'] ?? []) as $sk): ?>
                    <option value="<?= (int) $sk['id'] ?>" <?= ($filters['sub_kegiatan_id'] ?? '') == $sk['id'] ? 'selected' : '' ?>><?= htmlspecialchars(($sk['kode_sub_kegiatan'] ?? '') . ' - ' . ($sk['nama_sub_kegiatan'] ?? '')) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <!-- Info box -->
            <div class="col-12">
              <div id="exportInfoBox" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:0.75rem 1rem;font-size:var(--fs-sm);color:#166534;">
                <i class="bi bi-info-circle-fill me-1"></i>
                File Excel akan berisi data realisasi <strong>s/d bulan yang dipilih</strong> untuk tahun yang dipilih, dicatat pada <strong>bulan verifikasi/pembayaran</strong>, dengan struktur hierarki Program &rarr; Kegiatan &rarr; Sub Kegiatan &rarr; Rekening.
              </div>
            </div>
          </div>
        </form>
      </div>

      <!-- Footer -->
      <div class="modal-footer border-0 pt-0" style="padding:0 1.5rem 1.5rem;">
        <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal" style="font-size:var(--fs-base);">Batal</button>
        <button type="button" class="btn fw-semibold rounded-3" id="btnDownloadExcel"
          style="background:var(--status-success);color:#fff;font-size:var(--fs-base);padding:0.5rem 1.5rem;">
          <i class="bi bi-download me-1"></i> Download Excel
        </button>
      </div>

    </div>
  </div>
</div>

