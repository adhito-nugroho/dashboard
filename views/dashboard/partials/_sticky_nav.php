<?php
// Chip filter aktif ringkas — ditampilkan di area sticky agar konteks data
// tetap terlihat saat halaman digulir. Dihitung dari $filters + $filterOptions.
$stickyChips = [];
$stickyFilterDefs = [
    'seksi_id'        => ['icon' => 'bi-building',      'label' => 'Seksi',        'options' => 'seksi',        'name' => 'nama_seksi'],
    'program_id'      => ['icon' => 'bi-folder2-open',  'label' => 'Program',      'options' => 'program',      'name' => 'nama_program',      'code' => 'kode_program'],
    'kegiatan_id'     => ['icon' => 'bi-list-task',     'label' => 'Kegiatan',     'options' => 'kegiatan',     'name' => 'nama_kegiatan',     'code' => 'kode_kegiatan'],
    'sub_kegiatan_id' => ['icon' => 'bi-list-nested',   'label' => 'Sub Kegiatan', 'options' => 'sub_kegiatan', 'name' => 'nama_sub_kegiatan', 'code' => 'kode_sub_kegiatan'],
];
foreach ($stickyFilterDefs as $key => $def) {
    if (empty($filters[$key])) continue;
    $value = '';
    foreach (($filterOptions[$def['options']] ?? []) as $opt) {
        if ((int) ($opt['id'] ?? 0) === (int) $filters[$key]) {
            $value = (string) ($opt[$def['name']] ?? '');
            if (!empty($def['code']) && !empty($opt[$def['code']])) {
                $value = $opt[$def['code']] . ' - ' . $value;
            }
            $value = trim($value);
            break;
        }
    }
    $stickyChips[] = [
        'icon'  => $def['icon'],
        'label' => $def['label'],
        'value' => $value !== '' ? $value : ('ID ' . (int) $filters[$key]),
    ];
}
?>
<!-- Sticky Section Navigation (3 Logical Groups) -->
<nav id="stickySecNav" class="sticky-sec-nav" aria-label="Navigasi seksi halaman">
    <div class="sticky-sec-nav__inner">
        <!-- Grup 1: Ringkasan -->
        <div class="sticky-sec-nav__group">
            <span class="sticky-sec-nav__group-tag">Ringkasan</span>
            <a href="#section-ringkasan" class="sticky-sec-nav__link active"><i class="bi bi-speedometer2"></i> Ringkasan</a>
            <a href="#section-grafik" class="sticky-sec-nav__link"><i class="bi bi-graph-up"></i> Grafik Bulanan</a>
            <a href="#section-komposisi" class="sticky-sec-nav__link"><i class="bi bi-pie-chart"></i> Komposisi</a>
        </div>

        <span class="sticky-sec-nav__divider" aria-hidden="true"></span>

        <!-- Grup 2: Analisis -->
        <div class="sticky-sec-nav__group">
            <span class="sticky-sec-nav__group-tag">Analisis</span>
            <a href="#section-detail-bulan" class="sticky-sec-nav__link"><i class="bi bi-calendar3"></i> Detail per Bulan</a>
            <a href="#section-serapan-rekening" class="sticky-sec-nav__link"><i class="bi bi-grid-3x3-gap"></i> Serapan Bulanan</a>
            <a href="#section-sisa-semester" class="sticky-sec-nav__link"><i class="bi bi-wallet2"></i> Sisa per Semester</a>
            <a href="#section-tagar" class="sticky-sec-nav__link" title="Rekap pagu &amp; realisasi per tagar rekening (mis. #PAD)"><i class="bi bi-tags"></i> Tagar</a>
            <a href="#section-breakdown" class="sticky-sec-nav__link" title="Ringkasan pagu &amp; realisasi per seksi/program/kegiatan sesuai filter aktif"><i class="bi bi-bar-chart-steps"></i> Ringkasan per Unit</a>
        </div>

        <span class="sticky-sec-nav__divider" aria-hidden="true"></span>

        <!-- Grup 3: Perencanaan -->
        <div class="sticky-sec-nav__group">
            <span class="sticky-sec-nav__group-tag">Perencanaan</span>
            <a href="#section-struktur" class="sticky-sec-nav__link"><i class="bi bi-diagram-3"></i> Struktur Anggaran</a>
            <a href="#section-deviasi" class="sticky-sec-nav__link" title="Bulan &amp; rekening yang realisasinya di bawah rencana RAK (bulan yang sudah selesai)"><i class="bi bi-exclamation-triangle"></i> Deviasi RAK</a>
        </div>

        <?php if (!empty($stickyChips)): ?>
        <span class="sticky-sec-nav__divider" aria-hidden="true"></span>
        <div class="sticky-sec-nav__filters" role="group" aria-label="Filter aktif">
            <span class="sticky-sec-nav__filter-prefix"><i class="bi bi-funnel-fill"></i> Filter:</span>
            <?php foreach ($stickyChips as $chip): ?>
            <span class="sticky-sec-nav__filter-chip" title="<?= htmlspecialchars($chip['label'] . ': ' . $chip['value']) ?>">
                <i class="bi <?= $chip['icon'] ?>" aria-hidden="true"></i>
                <span class="sticky-sec-nav__filter-chip-text"><b><?= htmlspecialchars($chip['label']) ?>:</b> <?= htmlspecialchars($chip['value']) ?></span>
            </span>
            <?php endforeach; ?>
            <a class="sticky-sec-nav__filter-reset" href="<?= base_url() ?>?tahun=<?= (int) ($stats['tahun'] ?? date('Y')) ?>"
               title="Reset semua filter" aria-label="Reset semua filter">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>
</nav>
