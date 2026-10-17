<?php
$flash = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$configMap = $configMap ?? [];
$stIdByTransaksi = $stIdByTransaksi ?? [];
$namaListBySt = $namaListBySt ?? [];
$seksiList = $seksiList ?? [];
$batches = $batches ?? [];
$inputDari = $inputDari ?? null;
$inputSampai = $inputSampai ?? null;

$totalNilai = 0.0;
$totalPotongan = 0.0;
$configuredCount = 0;
foreach ($transaksiList as $t) {
    $totalNilai += (float) $t['nilai'];
    $cfg = $configMap[(int) $t['id']] ?? null;
    $totalPotongan += (float) ($cfg['potongan'] ?? 0);
}
foreach ($configMap as $c) {
    if (trim((string) ($c['transfer_ke'] ?? '')) !== '' || (float) ($c['potongan'] ?? 0) > 0) {
        $configuredCount++;
    }
}
$totalBersih = $totalNilai - $totalPotongan;
$tahunVal = (string) ($tahun ?? date('Y'));

$fmtTanggalID = static function ($tgl): string {
    $tgl = trim((string) $tgl);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $tgl, $m)) {
        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }
    return $tgl === '' ? '-' : $tgl;
};

// Kelompokkan baris per Surat Tugas untuk tampilan saja (urutan & data backend tidak diubah).
$grupSt = [];
foreach ($transaksiList as $t) {
    $tid = (int) $t['id'];
    $st = (int) ($stIdByTransaksi[$tid] ?? 0);
    $key = $st > 0 ? 'st-' . $st : 'trx-' . $tid;
    if (!isset($grupSt[$key])) {
        $grupSt[$key] = ['st' => $st, 'nomor' => '', 'tanggal' => '', 'kode_seksi' => '', 'uraian' => '', 'items' => []];
    }
    if ($grupSt[$key]['nomor'] === '' && trim((string) ($t['nomor_surat_tugas'] ?? '')) !== '') {
        $grupSt[$key]['nomor'] = trim((string) $t['nomor_surat_tugas']);
    }
    if ($grupSt[$key]['tanggal'] === '' && trim((string) ($t['tanggal'] ?? '')) !== '') {
        $grupSt[$key]['tanggal'] = trim((string) $t['tanggal']);
    }
    if ($grupSt[$key]['kode_seksi'] === '' && trim((string) ($t['kode_seksi'] ?? '')) !== '') {
        $grupSt[$key]['kode_seksi'] = trim((string) $t['kode_seksi']);
    }
    if ($grupSt[$key]['uraian'] === '' && trim((string) ($t['uraian'] ?? '')) !== '') {
        $grupSt[$key]['uraian'] = trim((string) $t['uraian']);
    }
    $grupSt[$key]['items'][] = $t;
}
$grupSt = array_values($grupSt);

$daftarSeksiCepat = [];
foreach ($transaksiList as $t) {
    $ks = trim((string) ($t['kode_seksi'] ?? ''));
    if ($ks !== '') {
        $daftarSeksiCepat[$ks] = true;
    }
}
$daftarSeksiCepat = array_keys($daftarSeksiCepat);
sort($daftarSeksiCepat, SORT_NATURAL | SORT_FLAG_CASE);
?>
<div class="container-fluid px-4 py-3">
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : ($flashType === 'success' ? 'success' : 'info') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flash) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="bi bi-arrow-left-right text-primary me-2"></i>Transfer Perjalanan Dinas
            </h1>
            <p class="text-muted small mb-0">
                Isi <strong>Transfer Ke</strong> dan <strong>Potongan</strong> di salah satu baris per Surat Tugas (otomatis tersebar ke seluruh transaksi pada ST itu),
                beri <strong>Nama Konfigurasi</strong>, lalu simpan. Transaksi yang sudah masuk konfigurasi tidak ditampilkan lagi dan bisa diunduh per nama konfigurasi di bawah.
                Hanya transaksi berstatus <strong>diverifikasi</strong> yang diinput mulai <strong>Oktober 2026</strong> yang tampil; yang diinput sebelumnya dianggap sudah punya file konfigurasi.
            </p>
        </div>
    </div>

    <div class="alert alert-light border shadow-sm rounded-3 py-2 px-3 mb-4 d-flex align-items-center gap-2 flex-wrap" style="font-size:0.85rem;">
        <i class="bi bi-info-circle-fill text-primary"></i>
        <span>Belum dikonfigurasi: <strong><?= count($transaksiList) ?> transaksi</strong></span>
        <span class="text-muted">·</span>
        <span>Sudah dikonfigurasi transfer: <strong><?= $configuredCount ?> transaksi</strong></span>
        <span class="text-muted">·</span>
        <span>Total penerimaan: <strong class="font-monospace">Rp <?= number_format($totalNilai, 0, ',', '.') ?></strong></span>
    </div>

    <form method="GET" action="<?= base_url('transfer-perjadin') ?>" class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label small text-muted mb-1">Tahun</label>
                <input type="number" name="tahun" class="form-control form-control-sm" style="width:110px;" value="<?= htmlspecialchars($tahunVal) ?>">
            </div>
            <div>
                <label class="form-label small text-muted mb-1">Diinput Dari</label>
                <input type="date" name="input_dari" class="form-control form-control-sm" style="width:150px;" value="<?= htmlspecialchars((string) ($inputDari ?? '')) ?>">
            </div>
            <div>
                <label class="form-label small text-muted mb-1">Diinput Sampai</label>
                <input type="date" name="input_sampai" class="form-control form-control-sm" style="width:150px;" value="<?= htmlspecialchars((string) ($inputSampai ?? '')) ?>">
            </div>
            <div>
                <label class="form-label small text-muted mb-1">Seksi</label>
                <select name="seksi_id" class="form-select form-select-sm" style="width:220px;">
                    <option value="">Semua</option>
                    <?php foreach ($seksiList as $sx): ?>
                        <option value="<?= (int) $sx['id'] ?>" <?= ((int) ($seksiId ?? 0) === (int) $sx['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars(($sx['kode_seksi'] ?? '') . ' - ' . ($sx['nama_seksi'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-grow-1" style="min-width:180px;">
                <label class="form-label small text-muted mb-1">Cari</label>
                <input type="text" name="q" class="form-control form-control-sm" value="<?= htmlspecialchars((string) ($q ?? '')) ?>" placeholder="Uraian / nama / nomor ST">
            </div>
            <button type="submit" class="btn btn-sm btn-primary px-3"><i class="bi bi-filter me-1"></i>Tampilkan</button>
            <a href="<?= base_url('transfer-perjadin') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>

<style>
.tp-uraian-clamp{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
tr.st-flash > td{animation:stFlashCell 1.4s ease;}
@keyframes stFlashCell{0%,45%{background-color:#c9ecd6 !important;}100%{background-color:transparent;}}
</style>

    <form method="POST" action="<?= base_url('transfer-perjadin/save') ?>" id="formTransferPerjadin">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="min-w-0">
                    <h5 class="card-title fw-bold mb-0 text-dark"><i class="bi bi-list-check text-success me-2"></i>Daftar Transaksi Perjalanan Dinas</h5>
                    <small class="text-muted">Centang transaksi yang ingin dikonfigurasi (boleh sebagian saja). Cukup isi sekali per Surat Tugas — Transfer Ke &amp; Potongan otomatis tersebar ke semua baris ST yang sama. Potongan berlaku per orang.</small>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                    <span id="stSyncHint" class="badge bg-success-subtle text-success border border-success-subtle text-nowrap d-none" role="status"></span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace text-nowrap" id="badgeBersih">
                        Total bersih: Rp <?= number_format($totalBersih, 0, ',', '.') ?>
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($transaksiList)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-inbox display-6 d-block mb-2 opacity-50"></i>
                        Tidak ada transaksi perjalanan dinas pada filter ini.
                    </div>
                <?php else: ?>
                    <div class="px-4 pt-3 pb-3 border-bottom">
                        <div class="d-flex flex-wrap align-items-end gap-2">
                            <div class="flex-grow-1" style="min-width:220px;">
                                <label class="form-label small text-muted mb-1" for="cariCepat">Pencarian cepat</label>
                                <input type="text" id="cariCepat" class="form-control form-control-sm" placeholder="Cari Nama Penerima / No. ST / Uraian..." autocomplete="off">
                            </div>
                            <div style="min-width:160px;">
                                <label class="form-label small text-muted mb-1" for="filterSeksiCepat">Seksi</label>
                                <select id="filterSeksiCepat" class="form-select form-select-sm">
                                    <option value="">Semua Seksi</option>
                                    <?php foreach ($daftarSeksiCepat as $ks): ?>
                                        <option value="<?= htmlspecialchars($ks) ?>"><?= htmlspecialchars($ks) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <span class="small text-muted ms-auto text-nowrap" id="infoCariCepat"></span>
                        </div>
                        <div class="small text-muted mt-1">Filter cepat hanya menyembunyikan tampilan; baris TOTAL tetap mencakup semua baris yang dimuat.</div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-top mb-0" id="tabelTransaksi">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 small text-muted align-top text-nowrap" style="width:44px;">
                                        <input type="checkbox" class="form-check-input" id="pilihSemua" checked title="Pilih / lepas semua">
                                    </th>
                                    <th class="small text-muted align-top text-nowrap" style="width:52px;">No</th>
                                    <th class="small align-top" style="min-width:150px;">Nama Penerima</th>
                                    <th class="text-end small align-top text-nowrap" style="width:128px;">Nilai</th>
                                    <th class="small align-top" style="min-width:190px;max-width:230px;">Transfer Ke</th>
                                    <th class="text-end small align-top text-nowrap" style="width:168px;">Potongan</th>
                                    <th class="text-end pe-4 small align-top text-nowrap" style="width:150px;">Penerimaan Bersih</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $gi = 0; ?>
                                <?php foreach ($grupSt as $g):
                                    $gi++;
                                    $gkey = 'g' . $gi;
                                    $stG = (int) $g['st'];
                                    $badgeST = $g['nomor'] !== '' ? 'ST: ' . $g['nomor'] : ($stG > 0 ? 'ST #' . $stG : '');
                                    $tglFmt = $fmtTanggalID($g['tanggal']);
                                    $seksiG = (string) $g['kode_seksi'];
                                    $uraianG = trim((string) $g['uraian']) !== '' ? (string) $g['uraian'] : '-';
                                    ?>
                                    <tr class="st-group-header table-light border-top border-2" data-group="<?= $gkey ?>" data-seksi="<?= htmlspecialchars($seksiG) ?>">
                                        <td colspan="7" class="ps-4 py-2 align-top">
                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                <?php if ($stG > 0): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold text-nowrap st-nomor"><?= htmlspecialchars($badgeST) ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fw-semibold text-nowrap st-nomor">Tanpa ST</span>
                                                <?php endif; ?>
                                                <span class="text-muted small text-nowrap"><i class="bi bi-calendar3 me-1"></i><?= htmlspecialchars($tglFmt) ?></span>
                                                <?php if ($seksiG !== ''): ?>
                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle text-nowrap"><?= htmlspecialchars($seksiG) ?></span>
                                                <?php endif; ?>
                                                <span class="text-muted small text-nowrap"><?= count($g['items']) ?> transaksi</span>
                                            </div>
                                            <div class="tp-uraian-clamp text-dark mt-1" style="font-size:.82rem;" title="<?= htmlspecialchars($uraianG) ?>"><?= htmlspecialchars($uraianG) ?></div>
                                        </td>
                                    </tr>
                                    <?php $no = 0; ?>
                                    <?php foreach ($g['items'] as $t):
                                        $no++;
                                        $tid = (int) $t['id'];
                                        $nilai = (float) $t['nilai'];
                                        $cfg = $configMap[$tid] ?? null;
                                        $transferKe = (string) ($cfg['transfer_ke'] ?? '');
                                        $potongan = (float) ($cfg['potongan'] ?? 0);
                                        $isConfigured = $transferKe !== '' || $potongan > 0;
                                        $stRow = (int) ($stIdByTransaksi[$tid] ?? 0);
                                        $seksiRow = (string) ($t['kode_seksi'] ?? '');
                                        ?>
                                        <tr data-nilai="<?= $nilai ?>" data-st="<?= $stRow ?>" data-group="<?= $gkey ?>" data-seksi="<?= htmlspecialchars($seksiRow) ?>" class="st-item <?= $isConfigured ? 'table-success-subtle' : '' ?>">
                                            <td class="ps-4 align-top">
                                                <input type="checkbox" class="form-check-input input-pilih" name="pilih[<?= $tid ?>]" value="1" checked aria-label="Pilih transaksi">
                                            </td>
                                            <td class="align-top text-muted small text-nowrap" style="width:52px;"><?= $no ?><?php if ($isConfigured): ?> <i class="bi bi-check-circle-fill text-success" title="Sudah dikonfigurasi"></i><?php endif; ?></td>
                                            <td class="align-top text-dark cell-nama"><?= htmlspecialchars((string) ($t['nama_penerima'] ?? '-')) ?></td>
                                            <td class="align-top text-end text-nowrap font-monospace fw-medium">Rp <?= number_format($nilai, 0, ',', '.') ?></td>
                                            <td class="align-top">
                                                <input type="text" class="form-control form-control-sm input-transfer-ke"
                                                    style="min-width:190px;max-width:230px;"
                                                    name="transfer_ke[<?= $tid ?>]" list="listKe_<?= $stRow ?>"
                                                    value="<?= htmlspecialchars($transferKe) ?>" placeholder="Nama penerima transfer">
                                                <?php if ($stRow <= 0): ?>
                                                    <small class="text-warning-emphasis" style="font-size:.68rem;"><i class="bi bi-exclamation-triangle me-1"></i>ST transaksi tidak ditemukan</small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="align-top">
                                                <div class="input-group input-group-sm text-nowrap" style="width:140px;margin-left:auto;">
                                                    <span class="input-group-text">Rp</span>
                                                    <input type="text" inputmode="numeric" class="form-control text-end input-potongan"
                                                        name="potongan[<?= $tid ?>]"
                                                        value="<?= $potongan > 0 ? number_format($potongan, 0, ',', '.') : '' ?>"
                                                        placeholder="0">
                                                </div>
                                            </td>
                                            <td class="align-top text-end pe-4 font-monospace fw-medium text-dark cell-bersih text-nowrap">Rp <?= number_format($nilai - $potongan, 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                                <tr id="barisKosongCari" class="d-none">
                                    <td colspan="7" class="text-center text-muted py-4"><i class="bi bi-search me-1"></i>Tidak ada baris yang cocok dengan pencarian cepat.</td>
                                </tr>
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td class="ps-4 align-top" colspan="3">TOTAL</td>
                                    <td class="text-end font-monospace align-top text-nowrap" id="totalNilai">Rp <?= number_format($totalNilai, 0, ',', '.') ?></td>
                                    <td class="align-top"></td>
                                    <td class="text-end font-monospace align-top text-nowrap" id="totalPotongan">Rp <?= number_format($totalPotongan, 0, ',', '.') ?></td>
                                    <td class="text-end pe-4 font-monospace align-top text-nowrap" id="totalBersih">Rp <?= number_format($totalBersih, 0, ',', '.') ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($transaksiList)): ?>
                <div class="card-footer bg-white py-3 px-4 d-flex justify-content-end gap-2 flex-wrap align-items-center">
                    <input type="text" name="nama_konfigurasi" class="form-control form-control-sm" style="max-width:300px;" placeholder="Nama konfigurasi (mis. Transfer Oktober I)" maxlength="150" required>
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i>Simpan Konfigurasi</button>
                </div>
            <?php endif; ?>
        </div>
    </form>

    <div class="card border-0 shadow-sm rounded-4 mt-3">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="card-title fw-bold mb-0 text-dark"><i class="bi bi-archive text-success me-2"></i>Konfigurasi Tersimpan</h5>
                <small class="text-muted">Setiap konfigurasi tersimpan dapat diunduh sebagai Excel (sheet Detail + Rekap).</small>
            </div>
            <span class="badge bg-success-subtle text-success border border-success-subtle text-nowrap"><?= count($batches ?? []) ?> konfigurasi</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($batches)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-inbox d-block mb-2 opacity-50"></i>
                    Belum ada konfigurasi tersimpan.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-top mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4 small text-muted align-top text-nowrap" style="width:52px;">No</th>
                                <th class="small align-top" style="min-width:200px;">Nama Konfigurasi</th>
                                <th class="small align-top text-nowrap" style="width:150px;">Disimpan</th>
                                <th class="text-end small align-top text-nowrap" style="width:120px;">Transaksi</th>
                                <th class="text-end small align-top text-nowrap" style="width:150px;">Total Bersih</th>
                                <th class="text-end pe-4 small align-top text-nowrap" style="width:130px;">Unduh</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $nb = 0; ?>
                            <?php foreach ($batches as $b):
                                $nb++;
                                $bersihBatch = (float) $b['total_nilai'] - (float) $b['total_potongan'];
                                ?>
                                <tr>
                                    <td class="ps-4 align-top text-muted small"><?= $nb ?></td>
                                    <td class="align-top fw-semibold text-dark"><?= htmlspecialchars((string) $b['nama']) ?></td>
                                    <td class="align-top text-muted small text-nowrap"><?= htmlspecialchars($fmtTanggalID(substr((string) $b['created_at'], 0, 10)) . ' ' . substr((string) $b['created_at'], 11, 5)) ?></td>
                                    <td class="align-top text-end font-monospace text-nowrap"><?= (int) $b['jml'] ?></td>
                                    <td class="align-top text-end font-monospace fw-medium text-nowrap">Rp <?= number_format($bersihBatch, 0, ',', '.') ?></td>
                                    <td class="align-top text-end pe-4">
                                        <a href="<?= base_url('transfer-perjadin/export') ?>?batch_id=<?= (int) $b['id'] ?>" class="btn btn-sm btn-success text-nowrap">
                                            <i class="bi bi-file-earmark-excel me-1"></i>Unduh
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php foreach ($namaListBySt as $stId => $daftarNama): ?>
    <?php if (empty($daftarNama)) continue; ?>
    <datalist id="listKe_<?= (int) $stId ?>">
        <?php foreach ($daftarNama as $nama): ?>
            <option value="<?= htmlspecialchars($nama, ENT_QUOTES) ?>"></option>
        <?php endforeach; ?>
    </datalist>
<?php endforeach; ?>

<script>
(function () {
    const form = document.getElementById('formTransferPerjadin');
    if (!form) return;

    const rp = (n) => 'Rp ' + Math.round(n).toLocaleString('id-ID');
    const parseAngka = (s) => {
        const d = (s || '').replace(/[^0-9]/g, '');
        return d === '' ? 0 : parseInt(d, 10);
    };

    function recalc() {
        let totalNilai = 0, totalPotongan = 0, totalBersih = 0;
        form.querySelectorAll('#tabelTransaksi tbody tr').forEach((tr) => {
            const nilai = parseFloat(tr.dataset.nilai || '0');
            const input = tr.querySelector('.input-potongan');
            const potongan = parseAngka(input ? input.value : '');
            const cell = tr.querySelector('.cell-bersih');
            if (cell) cell.textContent = rp(nilai - potongan);
            totalNilai += nilai;
            totalPotongan += potongan;
            totalBersih += (nilai - potongan);
        });
        const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        set('totalNilai', rp(totalNilai));
        set('totalPotongan', rp(totalPotongan));
        set('totalBersih', rp(totalBersih));
        set('badgeBersih', 'Total bersih: ' + rp(totalBersih));
    }

    // Satu Surat Tugas = satu Transfer Ke & satu Potongan per orang.
    // Nilai yang diisi pada satu baris disebarkan ke baris lain pada ST yang sama.
    function syncSt(tr, selector, value) {
        const st = tr.dataset.st;
        if (!st || st === '0') return;
        form.querySelectorAll('#tabelTransaksi tbody tr[data-st="' + st + '"]').forEach((other) => {
            if (other === tr) return;
            const inp = other.querySelector(selector);
            if (inp) inp.value = value;
        });
    }

    // Pilih transaksi: hanya baris tercentang yang disimpan. Mengisi/sebar
    // pada satu ST otomatis mencentang baris ST tsb.
    const pilihSemua = document.getElementById('pilihSemua');
    const pickOf = (tr) => tr.querySelector('.input-pilih');
    function setStChecked(tr) {
        const st = tr.dataset.st;
        if (st && st !== '0') {
            form.querySelectorAll('#tabelTransaksi tbody tr.st-item[data-st="' + st + '"]').forEach((other) => {
                const c = pickOf(other); if (c) c.checked = true;
            });
            return;
        }
        const c = pickOf(tr); if (c) c.checked = true;
    }
    function syncHeaderPilih() {
        if (!pilihSemua) return;
        const vis = Array.from(form.querySelectorAll('#tabelTransaksi tbody tr.st-item'))
            .filter((tr) => !tr.classList.contains('d-none'));
        const checked = vis.filter((tr) => { const c = pickOf(tr); return c && c.checked; }).length;
        pilihSemua.checked = vis.length > 0 && checked === vis.length;
        pilihSemua.indeterminate = checked > 0 && checked < vis.length;
    }
    if (pilihSemua) {
        pilihSemua.addEventListener('change', function () {
            form.querySelectorAll('#tabelTransaksi tbody tr.st-item').forEach((tr) => {
                if (tr.classList.contains('d-none')) return;
                const c = pickOf(tr); if (c) c.checked = this.checked;
            });
            syncHeaderPilih();
        });
    }
    form.addEventListener('change', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('input-pilih')) {
            syncHeaderPilih();
        }
    });

    form.querySelectorAll('.input-transfer-ke').forEach((input) => {
        input.addEventListener('input', function () {
            syncSt(this.closest('tr'), '.input-transfer-ke', this.value);
            setStChecked(this.closest('tr'));
            syncHeaderPilih();
        });
    });

    form.querySelectorAll('.input-potongan').forEach((input) => {
        input.addEventListener('input', function () {
            const digits = this.value.replace(/[^0-9]/g, '');
            this.value = digits === '' ? '' : Number(digits).toLocaleString('id-ID');
            syncSt(this.closest('tr'), '.input-potongan', this.value);
            setStChecked(this.closest('tr'));
            syncHeaderPilih();
            recalc();
        });
    });

    syncHeaderPilih();
    recalc();
})();
</script>

<script>
(function () {
    // Aditif & visual saja: filter cepat + indikator sebar per ST. Tidak mengubah nilai/input.
    const form = document.getElementById('formTransferPerjadin');
    if (!form) return;
    const tbody = form.querySelector('#tabelTransaksi tbody');
    if (!tbody) return;

    // ── Indikator feedback: highlight hijau sesaat + pill di header ──
    const hint = document.getElementById('stSyncHint');
    let hintTimer = null;
    const flashTimers = new WeakMap();
    form.addEventListener('input', function (e) {
        const target = e.target;
        if (!target || !target.classList) return;
        if (!target.classList.contains('input-transfer-ke') && !target.classList.contains('input-potongan')) return;
        const tr = target.closest('tr');
        if (!tr) return;
        const st = tr.dataset.st;
        if (!st || st === '0') return;
        const peers = Array.from(tbody.querySelectorAll('tr.st-item[data-st="' + st + '"]'));
        peers.forEach((row) => {
            row.classList.remove('st-flash');
            void row.offsetWidth;
            row.classList.add('st-flash');
            if (flashTimers.has(row)) clearTimeout(flashTimers.get(row));
            flashTimers.set(row, setTimeout(() => row.classList.remove('st-flash'), 1500));
        });
        let label = 'ST ' + st;
        const g = tr.getAttribute('data-group');
        if (g) {
            const badge = tbody.querySelector('tr.st-group-header[data-group="' + g + '"] .st-nomor');
            if (badge && badge.textContent.trim() !== '') label = badge.textContent.trim();
        }
        if (hint) {
            hint.textContent = '✓ Tersebar ke ' + peers.length + ' baris • ' + label;
            hint.classList.remove('d-none');
            if (hintTimer) clearTimeout(hintTimer);
            hintTimer = setTimeout(() => hint.classList.add('d-none'), 2600);
        }
    });

    // ── Filter cepat client-side (nama / no. ST / uraian + seksi) ──
    const q = document.getElementById('cariCepat');
    const fSeksi = document.getElementById('filterSeksiCepat');
    const info = document.getElementById('infoCariCepat');
    const emptyRow = document.getElementById('barisKosongCari');
    function applyQuickFilter() {
        const needle = ((q && q.value) || '').trim().toLowerCase();
        const sk = (fSeksi && fSeksi.value) || '';
        const headers = Array.from(tbody.querySelectorAll('tr.st-group-header'));
        let visItems = 0, visGroups = 0, totalItems = 0;
        headers.forEach((h) => {
            const g = h.getAttribute('data-group');
            const htext = (h.textContent || '').toLowerCase();
            const hMatch = needle === '' || htext.indexOf(needle) !== -1;
            const items = Array.from(tbody.querySelectorAll('tr.st-item[data-group="' + g + '"]'));
            let shown = 0;
            items.forEach((r) => {
                totalItems++;
                const namaEl = r.querySelector('.cell-nama');
                const nama = ((namaEl && namaEl.textContent) || '').toLowerCase();
                const sRow = r.getAttribute('data-seksi') || '';
                const ok = (needle === '' || nama.indexOf(needle) !== -1 || hMatch) && (sk === '' || sRow === sk);
                r.classList.toggle('d-none', !ok);
                if (ok) { shown++; visItems++; }
            });
            h.classList.toggle('d-none', shown === 0);
            if (shown > 0) visGroups++;
        });
        if (emptyRow) emptyRow.classList.toggle('d-none', visItems !== 0);
        if (info) info.textContent = 'Menampilkan ' + visItems + ' dari ' + totalItems + ' baris • ' + visGroups + ' ST';
    }
    if (q) q.addEventListener('input', applyQuickFilter);
    if (fSeksi) fSeksi.addEventListener('change', applyQuickFilter);
    applyQuickFilter();
})();
</script>
