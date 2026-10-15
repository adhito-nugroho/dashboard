/* Dashboard CDK Bojonegoro — diekstrak dari views/dashboard/index.php (P4 refactor).
   Nilai dinamis disuplai via window.DashboardBoot (lihat _scripts.php). */
var DashboardBoot = window.DashboardBoot || {};

/* ---- blok baris 1291-1402 ---- */
    (function() {
        var table = document.getElementById('semesterRekapTable');
        if (!table) return;

        // Toggle rekening rows in semester rekap table
        window.toggleSemRekRows = function(skRow) {
            var next = skRow.nextElementSibling;
            while (next && next.classList.contains('sm-rek-row')) {
                if (skRow.classList.contains('sm-collapsed')) {
                    next.style.display = 'none';
                } else {
                    next.style.display = '';
                }
                next = next.nextElementSibling;
            }
        };

        // Initial: collapse all rekening rows
        document.querySelectorAll('#semesterRekapTable .sm-sk-row').forEach(function(row) {
            window.toggleSemRekRows(row);
        });

        // Tab semester switching
        var activeSemester = table.dataset.activeSem || '1';
        var contextLabel = document.getElementById('semesterFilterContextLabel');

        function updateFilterContextLabel() {
            if (contextLabel) contextLabel.textContent = 'S' + activeSemester;
        }

        document.querySelectorAll('.sm-tab-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.sm-tab-btn').forEach(function(b) {
                    b.classList.remove('active');
                    b.setAttribute('aria-selected', 'false');
                });
                this.classList.add('active');
                this.setAttribute('aria-selected', 'true');
                activeSemester = this.dataset.semester;
                table.dataset.activeSem = activeSemester;
                updateFilterContextLabel();
                applyFilters();
            });
        });

        // Search & filter
        var searchInput = document.getElementById('semesterSearchInput');
        var emptyMsg = document.getElementById('semesterRekapEmpty');
        var currentFilter = 'all';

        function applyFilters() {
            var q = (searchInput.value || '').toLowerCase().trim();
            var skRows = document.querySelectorAll('#semesterRekapTable .sm-sk-row');
            var anyVisible = false;

            // Active semester determines which has-flag to use for context-aware filters
            var sisaKey = activeSemester === '2' ? 'hasS2' : 'hasS1';
            var negKey  = activeSemester === '2' ? 'hasNegS2' : 'hasNegS1';

            skRows.forEach(function(skRow) {
                var rekRows = [];
                var n = skRow.nextElementSibling;
                while (n && n.classList.contains('sm-rek-row')) {
                    rekRows.push(n);
                    n = n.nextElementSibling;
                }

                var matchedRekenings = rekRows.filter(function(rek) {
                    var matchSearch = !q || rek.dataset.search.indexOf(q) >= 0;
                    var matchFilter = true;
                    if (currentFilter === 'sisa') matchFilter = rek.dataset[sisaKey] === '1';
                    else if (currentFilter === 'negatif') matchFilter = rek.dataset[negKey] === '1';
                    return matchSearch && matchFilter;
                });

                var skMatchSearch = !q || skRow.dataset.search.indexOf(q) >= 0;

                if (matchedRekenings.length > 0 || (skMatchSearch && currentFilter === 'all' && !q)) {
                    skRow.style.display = '';
                    anyVisible = true;
                    if (q || currentFilter !== 'all') {
                        skRow.classList.remove('sm-collapsed');
                    }
                    rekRows.forEach(function(rek) {
                        if (matchedRekenings.indexOf(rek) >= 0 && !skRow.classList.contains('sm-collapsed')) {
                            rek.style.display = '';
                        } else {
                            rek.style.display = 'none';
                        }
                    });
                } else {
                    skRow.style.display = 'none';
                    rekRows.forEach(function(rek) { rek.style.display = 'none'; });
                }
            });

            emptyMsg.style.display = anyVisible ? 'none' : 'block';
        }

        if (searchInput) {
            searchInput.addEventListener('input', applyFilters);
        }

        document.querySelectorAll('.semester-filter-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.semester-filter-btn').forEach(function(b) { b.classList.remove('active'); });
                this.classList.add('active');
                currentFilter = this.dataset.filter;
                applyFilters();
            });
        });
    })();

/* ---- blok baris 1869-1880 ---- */
    function filterDeviasiRisk(level, btn) {
        document.querySelectorAll('.deviasi-risk-btn').forEach(function(b) { b.classList.remove('active'); });
        btn.classList.add('active');
        var nodes = document.querySelectorAll('#section-deviasi .d-node[data-risk]');
        nodes.forEach(function(node) {
            if (level === 'all' || node.dataset.risk === level) {
                node.style.display = '';
            } else {
                node.style.display = 'none';
            }
        });
    }

/* ---- blok baris 2142-2240 ---- */
document.getElementById('btnDownloadExcel').addEventListener('click', function() {
    var jenis = document.getElementById('exportJenis').value;
    var tahun = document.getElementById('exportTahun').value;

    // Laporan tabel halaman ini: langsung unduh via SheetJS (data sesuai filter layar).
    if (jenis === 'detail-bulan' || jenis === 'deviasi' || jenis === 'breakdown') {
        if (jenis === 'detail-bulan') {
            exportTableToExcel('table-detail-bulan', 'detail-bulan-' + tahun + '.xlsx');
        } else if (jenis === 'deviasi') {
            exportDeviasiToExcel('deviasi-rak-' + tahun + '.xlsx');
        } else {
            exportBreakdownToExcel('breakdown-seksi-' + tahun + '.xlsx');
        }
        setTimeout(function() {
            var modal = bootstrap.Modal.getInstance(document.getElementById('modalExportExcel'));
            if (modal) modal.hide();
        }, 500);
        return;
    }

    var url = '';
    if (jenis === 'laporan') {
        var bulan = document.getElementById('exportBulan').value;
        var groupingEl = document.querySelector('input[name="grouping"]:checked');
        var grouping = groupingEl ? groupingEl.value : 'program';
        var seksiEl = document.getElementById('exportSeksi');
        var seksiId = seksiEl ? seksiEl.value : '';
        url = DashboardBoot.urls.laporan + '?tahun=' + encodeURIComponent(tahun)
            + '&bulan=' + encodeURIComponent(bulan)
            + '&grouping=' + encodeURIComponent(grouping);
        if (grouping === 'seksi' && seksiId !== '') {
            url += '&seksi_id=' + encodeURIComponent(seksiId);
        }
    } else if (jenis === 'rak') {
        url = DashboardBoot.urls.rak + '?tahun=' + encodeURIComponent(tahun);
    } else {
        // bulanan / triwulan / semester: tahun + filter wilayah data
        var params = {
            tahun: tahun,
            seksi_id: document.getElementById('exportFSeksi').value,
            program_id: document.getElementById('exportFProgram').value,
            kegiatan_id: document.getElementById('exportFKegiatan').value,
            sub_kegiatan_id: document.getElementById('exportFSub').value
        };
        var qs = Object.keys(params)
            .filter(function(k) { return params[k] !== '' && params[k] !== null; })
            .map(function(k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); })
            .join('&');
        var base = jenis === 'bulanan'
            ? DashboardBoot.urls.bulanan
            : (jenis === 'triwulan'
                ? DashboardBoot.urls.triwulan
                : DashboardBoot.urls.semester);
        url = base + '?' + qs;
    }
    window.location.href = url;
    // Close modal after short delay
    setTimeout(function() {
        var modal = bootstrap.Modal.getInstance(document.getElementById('modalExportExcel'));
        if (modal) modal.hide();
    }, 500);
});

// Toggle blok parameter + info box sesuai jenis laporan & grouping
(function() {
    var jenisEl = document.getElementById('exportJenis');
    var bulanWrap = document.getElementById('exportBulanWrap');
    var groupingWrap = document.getElementById('exportGroupingWrap');
    var radioSeksi = document.getElementById('exportGroupingSeksi');
    var seksiWrap = document.getElementById('exportSeksiWrap');
    var filterWrap = document.getElementById('exportFilterWrap');
    var infoBox = document.getElementById('exportInfoBox');
    var info = {
        laporan_program: 'File Excel akan berisi data realisasi <strong>s/d bulan yang dipilih</strong> untuk tahun yang dipilih, dicatat pada <strong>bulan verifikasi/pembayaran</strong>, dengan struktur hierarki Program &rarr; Kegiatan &rarr; Sub Kegiatan &rarr; Rekening.',
        laporan_seksi: 'File Excel akan berisi data realisasi <strong>s/d bulan yang dipilih</strong> untuk tahun yang dipilih, dicatat pada <strong>bulan verifikasi/pembayaran</strong>, dengan struktur <strong>Seksi &rarr; Program &rarr; Kegiatan &rarr; Sub Kegiatan &rarr; Rekening</strong>. Pilih "Semua Seksi" untuk seluruh seksi, atau satu seksi tertentu.',
        bulanan: 'File Excel berisi <strong>realisasi per bulan (Jan–Des) per rekening</strong> per sub kegiatan untuk tahun yang dipilih, dengan filter wilayah data di atas.',
        triwulan: 'File Excel berisi <strong>realisasi per triwulan (TW I–IV) per rekening</strong> per sub kegiatan untuk tahun yang dipilih, dengan filter wilayah data di atas.',
        semester: 'File Excel berisi <strong>sisa dana (RAK &minus; realisasi) per semester</strong> per rekening untuk tahun yang dipilih, dengan filter wilayah data di atas.',
        rak: 'File Excel berisi <strong>Rencana Anggaran Kas (RAK)</strong> tahun yang dipilih.',
        'detail-bulan': 'Mengunduh <strong>tabel Detail Penyerapan per Bulan yang tampil di halaman ini</strong> (mengikuti filter layar aktif).',
        deviasi: 'Mengunduh <strong>daftar Deviasi RAK yang tampil di halaman ini</strong> (mengikuti filter layar aktif).',
        breakdown: 'Mengunduh <strong>ringkasan serapan per unit yang tampil di halaman ini</strong> (mengikuti filter layar aktif).'
    };
    function refreshExportModal() {
        var jenis = jenisEl ? jenisEl.value : 'laporan';
        var isLaporan = jenis === 'laporan';
        var isFiltered = jenis === 'bulanan' || jenis === 'triwulan' || jenis === 'semester';
        var isSeksiGrouping = isLaporan && radioSeksi && radioSeksi.checked;
        if (bulanWrap) bulanWrap.style.display = isLaporan ? '' : 'none';
        if (groupingWrap) groupingWrap.style.display = isLaporan ? '' : 'none';
        if (seksiWrap) seksiWrap.style.display = isSeksiGrouping ? '' : 'none';
        if (filterWrap) filterWrap.style.display = isFiltered ? '' : 'none';
        if (infoBox) infoBox.innerHTML = '<i class="bi bi-info-circle-fill me-1"></i>' + (info[isSeksiGrouping ? 'laporan_seksi' : (isLaporan ? 'laporan_program' : jenis)] || info.laporan_program);
    }
    if (jenisEl) jenisEl.addEventListener('change', refreshExportModal);
    var radios = document.querySelectorAll('input[name="grouping"]');
    radios.forEach(function(r) { r.addEventListener('change', refreshExportModal); });
    refreshExportModal();
})();

/* ---- blok baris 2246-2356 ---- */
document.addEventListener('DOMContentLoaded', function () {
    const absorptionTable = document.querySelector('.monthly-absorption-table');
    if (!absorptionTable) return;

    function setAbsorptionGroupState(row, expanded) {
        const key = row.getAttribute('data-absorption-toggle');
        if (!key) return;

        row.classList.toggle('is-expanded', expanded);
        row.classList.toggle('is-collapsed', !expanded);
        row.setAttribute('aria-expanded', expanded ? 'true' : 'false');

        const button = row.querySelector('[data-absorption-toggle-button]');
        if (button) {
            button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            button.setAttribute('aria-label', expanded ? 'Tutup rincian rekening' : 'Buka rincian rekening');
        }

        absorptionTable.querySelectorAll('[data-absorption-child="' + key + '"]').forEach(function (childRow) {
            childRow.hidden = !expanded;
        });
    }

    absorptionTable.querySelectorAll('[data-absorption-toggle]').forEach(function (row) {
        row.addEventListener('click', function (event) {
            if (event.target.closest('a')) return;
            setAbsorptionGroupState(row, row.getAttribute('aria-expanded') !== 'true');
            syncToggleAllBtn();
        });

        row.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                setAbsorptionGroupState(row, row.getAttribute('aria-expanded') !== 'true');
                syncToggleAllBtn();
            }
        });
    });

    // Expand All / Collapse All
    var toggleAllBtn = document.getElementById('btnAbsorptionToggleAll');
    function syncToggleAllBtn() {
        if (!toggleAllBtn) return;
        var groups = absorptionTable.querySelectorAll('[data-absorption-toggle]');
        var expandedCount = absorptionTable.querySelectorAll('[data-absorption-toggle][aria-expanded="true"]').length;
        var allExpanded = groups.length > 0 && expandedCount === groups.length;
        toggleAllBtn.setAttribute('aria-pressed', allExpanded ? 'true' : 'false');
        toggleAllBtn.innerHTML = allExpanded
            ? '<i class="bi bi-arrows-collapse me-1"></i><span>Collapse All</span>'
            : '<i class="bi bi-arrows-expand me-1"></i><span>Expand All</span>';
    }
    if (toggleAllBtn) {
        toggleAllBtn.addEventListener('click', function() {
            var groups = absorptionTable.querySelectorAll('[data-absorption-toggle]');
            var allExpanded = groups.length > 0
                && absorptionTable.querySelectorAll('[data-absorption-toggle][aria-expanded="true"]').length === groups.length;
            groups.forEach(function(row) { setAbsorptionGroupState(row, !allExpanded); });
            syncToggleAllBtn();
        });
        syncToggleAllBtn();
    }

    // Real-time search: filter baris sub kegiatan & rekening (client-side)
    var searchInput = document.getElementById('absorptionSearchInput');
    var emptyMsg = document.getElementById('absorptionEmptyMsg');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var q = (searchInput.value || '').toLowerCase().trim();
            var groups = absorptionTable.querySelectorAll('[data-absorption-toggle]');
            var anyVisible = false;
            groups.forEach(function(groupRow) {
                var key = groupRow.getAttribute('data-absorption-toggle');
                var childRows = absorptionTable.querySelectorAll('[data-absorption-child="' + key + '"]');
                if (!q) {
                    groupRow.style.display = '';
                    // Kembalikan ke state collapse/expand masing-masing (jangan paksa tampil semua child)
                    var isExp = groupRow.getAttribute('aria-expanded') === 'true';
                    childRows.forEach(function(ch) { ch.hidden = !isExp; if (!ch.hidden) ch.style.display = ''; else ch.style.display = ''; });
                    anyVisible = true;
                    return;
                }
                var groupText = (groupRow.textContent || '').toLowerCase();
                var groupMatch = groupText.indexOf(q) >= 0;
                var matchedChildren = [];
                childRows.forEach(function(ch) {
                    var childText = (ch.textContent || '').toLowerCase();
                    if (childText.indexOf(q) >= 0) matchedChildren.push(ch);
                });
                if (groupMatch) {
                    // Grup cocok: tampilkan grup + semua child-nya, expand otomatis
                    groupRow.style.display = '';
                    setAbsorptionGroupState(groupRow, true);
                    anyVisible = true;
                } else if (matchedChildren.length > 0) {
                    // Hanya sebagian child cocok: tampilkan grup + child yang cocok
                    groupRow.style.display = '';
                    setAbsorptionGroupState(groupRow, true);
                    childRows.forEach(function(ch) {
                        ch.hidden = matchedChildren.indexOf(ch) < 0;
                    });
                    anyVisible = true;
                } else {
                    groupRow.style.display = 'none';
                    childRows.forEach(function(ch) { ch.hidden = true; });
                }
            });
            if (emptyMsg) emptyMsg.style.display = anyVisible ? 'none' : 'block';
            syncToggleAllBtn();
        });
    }
});

/* ---- blok baris 2367-2872 ---- */
function resetFilters() { window.location.href = DashboardBoot.resetUrl; }

// Helper: Export HTML Table to Excel using SheetJS
function exportTableToExcel(tableId, filename) {
    var tbl = document.getElementById(tableId);
    if (!tbl) {
        alert('Tabel data tidak ditemukan.');
        return;
    }
    if (typeof XLSX !== 'undefined') {
        var wb = XLSX.utils.table_to_book(tbl, { sheet: "Data" });
        XLSX.writeFile(wb, filename || 'export.xlsx');
    } else {
        alert('Library Excel sedang dimuat, silakan coba lagi.');
    }
}

// Helper: Export Breakdown Cards to Excel
function exportBreakdownToExcel(filename) {
    var data = [
        ["Nama Unit / Seksi", "Pagu (Rp)", "Realisasi (Rp)", "Sisa (Rp)", "% Capaian"]
    ];
    DashboardBoot.breakdownRows.forEach(function(r) { data.push(r); });
    if (typeof XLSX !== 'undefined') {
        var ws = XLSX.utils.aoa_to_sheet(data);
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Breakdown");
        XLSX.writeFile(wb, filename || 'breakdown-seksi.xlsx');
    }
}

// Helper: Export Deviasi RAK Tree to Excel
function exportDeviasiToExcel(filename) {
    var data = [
        ["Sub Kegiatan", "Rekening", "Kode Rekening", "Bulan", "RAK Target (Rp)", "Realisasi (Rp)", "Selisih (Rp)", "Status", "Tingkat Risiko", "Catatan"]
    ];
    var tahun = DashboardBoot.tahun;
    DashboardBoot.deviasiRows.forEach(function(r) {
        var note = localStorage.getItem("cdk_deviasi_note_" + tahun + "_" + r[0]) || "";
        data.push([r[1], r[2], r[3], r[4], r[5], r[6], r[7], r[8], r[9], note]);
    });
    if (typeof XLSX !== 'undefined') {
        var ws = XLSX.utils.aoa_to_sheet(data);
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Deviasi_RAK");
        XLSX.writeFile(wb, filename || 'deviasi-rak.xlsx');
    }
}

// Helper: Save & Load Deviasi Notes
function saveDeviasiNote(input) {
    var skId = input.dataset.skId;
    var val = input.value.trim();
    var tahun = DashboardBoot.tahun;
    var key = "cdk_deviasi_note_" + tahun + "_" + skId;
    if (val) {
        localStorage.setItem(key, val);
    } else {
        localStorage.removeItem(key);
    }
}

function loadDeviasiNotes() {
    var tahun = DashboardBoot.tahun;
    document.querySelectorAll('.deviasi-catatan-input').forEach(function(input) {
        var skId = input.dataset.skId;
        var key = "cdk_deviasi_note_" + tahun + "_" + skId;
        var saved = localStorage.getItem(key);
        if (saved) {
            input.value = saved;
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Load notes
    loadDeviasiNotes();

    // Tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

    // ================================================================
    // Chart global defaults — font lebih besar & kontras
    // ================================================================
    Chart.defaults.font.family = "'Inter', 'Segoe UI', sans-serif";
    Chart.defaults.font.size   = 12;
    Chart.defaults.color       = '#475569';

    // ================================================================
    // Monthly Chart (Line + Area — interactive)
    // ================================================================
    const mData = {
        rak:       DashboardBoot.monthly.rak,
        realisasi: DashboardBoot.monthly.realisasi,
        labels:    DashboardBoot.monthly.labels
    };
    const alertM = DashboardBoot.monthly.alertBulan;
    const alertTypes = DashboardBoot.monthly.alertTypes;

    // Bulan berjalan (1–12). Future months = index >= bulanBerjalan
    const bulanBerjalan = DashboardBoot.bulanBerjalan;
    const isTahunIni = DashboardBoot.isTahunIni;

    // Split RAK dataset: actual (s/d bulan berjalan) vs proyeksi (future)
    const rakAktual    = mData.rak.map((v, i) => (i < bulanBerjalan || !isTahunIni) ? v : null);
    const rakProyeksi  = mData.rak.map((v, i) => (i >= bulanBerjalan - 1 && isTahunIni) ? v : null);

    // Determine point colors for Realisasi based on alert status & anomaly
    const realisasiPointBg = mData.realisasi.map((v, i) => {
        const bulan = i + 1;
        const rak = mData.rak[i] || 0;
        const pct = rak > 0 ? (v / rak) * 100 : 100;
        if (pct < 20 && rak > 0 && (i < bulanBerjalan || !isTahunIni)) return '#dc2626'; // Anomali ekstrem
        if (alertTypes[bulan] === 'over') return '#2563eb';
        if (alertTypes[bulan] === 'under') return '#dc2626';
        return '#059669';
    });
    const realisasiPointRadius = mData.realisasi.map((v, i) => {
        const rak = mData.rak[i] || 0;
        const pct = rak > 0 ? (v / rak) * 100 : 100;
        if (pct < 20 && rak > 0 && (i < bulanBerjalan || !isTahunIni)) return 9; // Extreme anomaly
        return alertM.includes(i + 1) ? 7 : 4;
    });
    const realisasiPointBorder = mData.realisasi.map((v, i) => {
        const bulan = i + 1;
        const rak = mData.rak[i] || 0;
        const pct = rak > 0 ? (v / rak) * 100 : 100;
        if (pct < 20 && rak > 0 && (i < bulanBerjalan || !isTahunIni)) return '#fecaca';
        if (alertTypes[bulan] === 'over') return '#bfdbfe';
        if (alertTypes[bulan] === 'under') return '#fecaca';
        return '#bbf7d0';
    });

    // Format rupiah helper
    function fmtRp(v) {
        if (v >= 1e9) return 'Rp ' + (v/1e9).toFixed(1) + ' M';
        if (v >= 1e6) return 'Rp ' + (v/1e6).toFixed(0) + ' jt';
        if (v >= 1e3) return 'Rp ' + (v/1e3).toFixed(0) + ' rb';
        return 'Rp ' + v;
    }

    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');

    // Gradient fill for realisasi area
    const areaGradient = monthlyCtx.createLinearGradient(0, 0, 0, 320);
    areaGradient.addColorStop(0, 'rgba(5,150,105,0.25)');
    areaGradient.addColorStop(1, 'rgba(5,150,105,0.02)');

    const monthlyChartInstance = new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: mData.labels,
            datasets: [{
                label: 'RAK (Target)',
                data: rakAktual,
                borderColor: '#94a3b8',
                borderWidth: 2,
                borderDash: [6, 4],
                pointBackgroundColor: '#94a3b8',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 6,
                fill: false,
                tension: 0.3,
                order: 2,
                spanGaps: false,
            },{
                label: 'RAK (Proyeksi)',
                data: rakProyeksi,
                borderColor: 'rgba(148,163,184,0.35)',
                borderWidth: 1.5,
                borderDash: [3, 6],
                pointBackgroundColor: 'rgba(148,163,184,0.35)',
                pointBorderColor: '#fff',
                pointBorderWidth: 1,
                pointRadius: 2,
                pointHoverRadius: 4,
                fill: false,
                tension: 0.3,
                order: 3,
                spanGaps: false,
            },{
                label: 'Realisasi',
                data: mData.realisasi,
                borderColor: '#059669',
                borderWidth: 2.5,
                pointBackgroundColor: realisasiPointBg,
                pointBorderColor: realisasiPointBorder,
                pointBorderWidth: 2,
                pointRadius: realisasiPointRadius,
                pointHoverRadius: 8,
                fill: true,
                backgroundColor: areaGradient,
                tension: 0.3,
                order: 1,
                spanGaps: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 20,
                        font: { size: 12, weight: '600' },
                        color: '#1e293b',
                    }
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleFont: { size: 13, weight: '700' },
                    bodyFont: { size: 12 },
                    titleColor: '#f1f5f9',
                    bodyColor: '#cbd5e1',
                    padding: 14,
                    cornerRadius: 10,
                    boxPadding: 5,
                    callbacks: {
                        title: function(items) {
                            return items[0].label;
                        },
                        label: function(ctx) {
                            return '  ' + ctx.dataset.label + ': Rp ' + ctx.parsed.y.toLocaleString('id-ID');
                        },
                        afterBody: function(items) {
                            const idx = items[0].dataIndex;
                            const bulan = idx + 1;
                            // Jika bulan future (belum berjalan), jangan hitung deviasi
                            if (isTahunIni && bulan > bulanBerjalan) {
                                return ['', '  📅 Belum Berjalan (Proyeksi RAK)'];
                            }
                            const rak = mData.rak[idx] || 0;
                            const real = mData.realisasi[idx] || 0;
                            const selisih = real - rak;
                            const pct = rak > 0 ? ((real / rak) * 100).toFixed(2) : '0.00';
                            const sign = selisih >= 0 ? '+' : '';
                            const lines = [
                                '',
                                '  RAK Target : Rp ' + Number(rak).toLocaleString('id-ID'),
                                '  Realisasi  : Rp ' + Number(real).toLocaleString('id-ID'),
                                '  Selisih    : ' + sign + 'Rp ' + Math.abs(selisih).toLocaleString('id-ID'),
                                '  Capaian    : ' + pct + '%'
                            ];
                            if (rak > 0 && parseFloat(pct) < 20) {
                                lines.push('  🚨 ⚠️ Realisasi jauh di bawah target RAK');
                            } else if (alertTypes[bulan] === 'over') {
                                lines.push('  ℹ️ Over RAK');
                            } else if (alertTypes[bulan] === 'under') {
                                lines.push('  ⚠️ Under RAK');
                            }
                            return lines;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(71,85,105,0.09)', drawBorder: false },
                    border: { display: false },
                    ticks: {
                        font: { size: 11, weight: '500' },
                        color: '#475569',
                        padding: 8,
                        maxTicksLimit: 6,
                        callback: function(v) {
                            if (v >= 1e9) return 'Rp ' + (v/1e9).toFixed(1) + ' M';
                            if (v >= 1e6) return 'Rp ' + (v/1e6).toFixed(0) + ' jt';
                            if (v >= 1e3) return 'Rp ' + (v/1e3).toFixed(0) + ' rb';
                            return 'Rp ' + v;
                        }
                    }
                },
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        font: { size: 12, weight: '600' },
                        color: '#334155',
                        padding: 6,
                    }
                }
            },
            layout: {
                padding: { top: 4, bottom: 0, left: 0, right: 8 }
            }
        }
    });

    // Toggle buttons for semester zoom + log scale mode
    // datasets: [0]=RAK Aktual, [1]=RAK Proyeksi, [2]=Realisasi
    var isLogMode = false;
    function applyMonthlyRange(range) {
        let labels, rakA, rakP, real, ptBg, ptRadius, ptBorder;
        if (range === 'h1') {
            labels = mData.labels.slice(0, 6);
            rakA = rakAktual.slice(0, 6);
            rakP = rakProyeksi.slice(0, 6);
            real = mData.realisasi.slice(0, 6);
            ptBg = realisasiPointBg.slice(0, 6);
            ptRadius = realisasiPointRadius.slice(0, 6);
            ptBorder = realisasiPointBorder.slice(0, 6);
        } else if (range === 'h2') {
            labels = mData.labels.slice(6, 12);
            rakA = rakAktual.slice(6, 12);
            rakP = rakProyeksi.slice(6, 12);
            real = mData.realisasi.slice(6, 12);
            ptBg = realisasiPointBg.slice(6, 12);
            ptRadius = realisasiPointRadius.slice(6, 12);
            ptBorder = realisasiPointBorder.slice(6, 12);
        } else {
            labels = mData.labels;
            rakA = rakAktual;
            rakP = rakProyeksi;
            real = mData.realisasi;
            ptBg = realisasiPointBg;
            ptRadius = realisasiPointRadius;
            ptBorder = realisasiPointBorder;
        }
        monthlyChartInstance.data.labels = labels;
        monthlyChartInstance.data.datasets[0].data = rakA;
        monthlyChartInstance.data.datasets[1].data = rakP;
        monthlyChartInstance.data.datasets[2].data = real;
        monthlyChartInstance.data.datasets[2].pointBackgroundColor = ptBg;
        monthlyChartInstance.data.datasets[2].pointRadius = ptRadius;
        monthlyChartInstance.data.datasets[2].pointBorderColor = ptBorder;
        // Re-calculate skala Y mengikuti nilai maksimum rentang yang dipilih
        monthlyChartInstance.options.scales.y.beginAtZero = !isLogMode;
        monthlyChartInstance.update();
    }
    document.querySelectorAll('#monthlyChartToggle .monthly-toggle-btn[data-range]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('#monthlyChartToggle .monthly-toggle-btn[data-range]').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            applyMonthlyRange(this.dataset.range);
        });
    });

    // Mode Skala Logaritmik: membantu melihat perbandingan saat ada lonjakan (mis. Okt Rp 4,28 M)
    var logToggleBtn = document.getElementById('monthlyLogToggle');
    if (logToggleBtn) {
        logToggleBtn.addEventListener('click', function() {
            isLogMode = !isLogMode;
            this.classList.toggle('active', isLogMode);
            this.setAttribute('aria-pressed', isLogMode ? 'true' : 'false');
            var yScale = monthlyChartInstance.options.scales.y;
            if (isLogMode) {
                yScale.type = 'logarithmic';
                yScale.beginAtZero = false;
            } else {
                yScale.type = 'linear';
                yScale.beginAtZero = true;
            }
            monthlyChartInstance.update();
        });
    }

    // ================================================================
    // Breakdown Doughnut
    // ================================================================
    const bdData    = DashboardBoot.breakdown;
    // Donut chart: distribusi REALISASI per unit (outer ring) + Sisa (inner ring)
    const bdEntries = Object.entries(bdData);
    const bdLabels  = bdEntries.map(([l]) => l.length > 22 ? l.substring(0,22)+'…' : l);
    const bdReal    = bdEntries.map(([,i]) => i.realisasi);
    const bdPagu    = bdEntries.map(([,i]) => i.pagu);
    const bdSisa    = bdEntries.map(([,i]) => Math.max(0, i.pagu - i.realisasi));


    const dColorsReal = [
        'rgba(67,56,202,0.90)','rgba(2,132,199,0.90)','rgba(5,150,105,0.90)',
        'rgba(217,119,6,0.90)','rgba(220,38,38,0.90)','rgba(147,51,234,0.90)',
        'rgba(219,39,119,0.90)','rgba(20,184,166,0.90)','rgba(234,88,12,0.90)',
        'rgba(71,85,105,0.90)'
    ];
    const dColorsSisa = dColorsReal.map(c => c.replace('0.90','0.22'));

    var breakdownChartInstance = new Chart(document.getElementById('breakdownChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: bdLabels,
            datasets: [{
                // Outer ring: Realisasi per unit
                label: 'Realisasi',
                data: bdReal,
                backgroundColor: dColorsReal,
                borderColor: '#fff',
                borderWidth: 2,
                hoverBorderWidth: 0,
                hoverOffset: 8,
                weight: 2,
            },{
                // Inner ring: Sisa per unit (faded)
                label: 'Sisa',
                data: bdSisa,
                backgroundColor: dColorsSisa,
                borderColor: '#fff',
                borderWidth: 1,
                hoverBorderWidth: 0,
                hoverOffset: 4,
                weight: 1,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '52%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 10,
                        padding: 8,
                        font: { size: 10, weight: '600' },
                        color: '#334155',
                        usePointStyle: true,
                        pointStyle: 'circle',
                        // Hanya tampilkan legend dari dataset pertama (outer ring)
                        filter: (item) => item.datasetIndex === 0,
                    }
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleFont: { size: 12, weight: '700' },
                    bodyFont: { size: 11 },
                    titleColor: '#f1f5f9',
                    bodyColor: '#cbd5e1',
                    padding: 12,
                    cornerRadius: 10,
                    callbacks: {
                        label: ctx => {
                            const dsLabel = ctx.datasetIndex === 0 ? 'Realisasi' : 'Sisa';
                            const total = bdPagu[ctx.dataIndex] || 1;
                            const pct = ((ctx.parsed / total) * 100).toFixed(1);
                            return ` ${dsLabel}: Rp ${ctx.parsed.toLocaleString('id-ID')}  (${pct}% dari pagu)`;
                        }
                    }
                }
            }
        }
    });

    // Custom legend donut: titik warna + nama unit + persentase realisasi
    (function renderBreakdownLegend() {
        var legendEl = document.getElementById('breakdownCustomLegend');
        if (!legendEl) return;
        var totalReal = bdReal.reduce(function(a, b) { return a + (Number(b) || 0); }, 0) || 1;
        var html = '';
        bdEntries.forEach(function(entry, idx) {
            var fullName = entry[0];
            var shortName = bdLabels[idx];
            var val = Number(entry[1].realisasi) || 0;
            var pct = ((val / totalReal) * 100).toFixed(1);
            var color = dColorsReal[idx % dColorsReal.length];
            html += '<div class="breakdown-legend__item" title="' + fullName.replace(/"/g, '&quot;') + ' — Rp ' + val.toLocaleString('id-ID') + ' (' + pct + '%)">'
                + '<span class="breakdown-legend__dot" style="background:' + color + ';"></span>'
                + '<span class="breakdown-legend__name">' + shortName.replace(/</g, '&lt;') + '</span>'
                + '<span class="breakdown-legend__pct">' + pct + '%</span>'
                + '</div>';
        });
        legendEl.innerHTML = html;
    })();
});



/* ---- blok baris 3425-3622 ---- */
(function() {
    const nav = document.getElementById('stickySecNav');
    if (!nav) return;

    // Sejajarkan dengan konten: hanya geser kanan bila sidebar ada (mode admin)
    if (document.getElementById('sidebar')) {
        nav.classList.add('with-sidebar');
    }

    const SCROLL_THRESHOLD = 80;
    const links = nav.querySelectorAll('.sticky-sec-nav__link');
    
    // Mapping between section anchor IDs and tab button IDs
    const tabTargetMap = {
        'section-serapan-rekening': 'tab-serapan-rekening-btn',
        'section-sisa-semester': 'tab-sisa-semester-btn',
        'section-struktur': 'tab-struktur-btn',
        'section-deviasi': 'tab-deviasi-btn'
    };

    const tabSectionMap = {
        '#tab-serapan-rekening': 'section-serapan-rekening',
        '#tab-sisa-semester': 'section-sisa-semester',
        '#tab-struktur': 'section-struktur',
        '#tab-deviasi': 'section-deviasi'
    };

    // Show/hide nav based on scroll position
    let ticking = false;
    function onScroll() {
        if (!ticking) {
            window.requestAnimationFrame(function() {
                if (window.scrollY > SCROLL_THRESHOLD) {
                    nav.classList.add('visible');
                } else {
                    nav.classList.remove('visible');
                }
                ticking = false;
            });
            ticking = true;
        }
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    // Helper: switch to tab and scroll to tab container
    // Klik di sticky sub-navbar atas => smooth-scroll SEKALIGUS aktifkan tab Bootstrap yang sesuai
    function activateTabAndScroll(targetId) {
        var isTabbedSection = !!tabTargetMap[targetId];
        if (isTabbedSection) {
            var tabBtn = document.getElementById(tabTargetMap[targetId]);
            if (tabBtn && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                var tabInstance = bootstrap.Tab.getOrCreateInstance(tabBtn);
                tabInstance.show();
            }
        }
        // Untuk section di dalam tab, scroll ke container tab agar posisi selalu terlihat
        // (elemen di dalam tab yang hidden punya rect 0 sehingga harus ke wrapper-nya)
        var target = isTabbedSection
            ? document.getElementById('section-detail-tabs')
            : (document.getElementById(targetId) || document.getElementById('section-detail-tabs'));
        if (target) {
            // Offset = header aplikasi + sticky nav + jarak napas
            var headerEl = document.querySelector('.header');
            var headerHeight = headerEl ? headerEl.offsetHeight : 64;
            var navHeight = nav.offsetHeight || 48;
            var top = target.getBoundingClientRect().top + window.pageYOffset - headerHeight - navHeight - 12;
            window.scrollTo({ top: top, behavior: 'smooth' });
        }
    }

    // Smooth scroll & tab trigger on sticky nav link click
    links.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var targetId = this.getAttribute('href').substring(1);
            activateTabAndScroll(targetId);
        });
    });

    // Handle any in-page link pointing to tabbed sections (e.g. from breakdown section)
    document.querySelectorAll('a[href="#section-struktur"], a[href="#section-deviasi"], a[href="#section-serapan-rekening"], a[href="#section-sisa-semester"]').forEach(function(a) {
        if (a.classList.contains('sticky-sec-nav__link')) return;
        a.addEventListener('click', function(e) {
            e.preventDefault();
            var targetId = this.getAttribute('href').substring(1);
            activateTabAndScroll(targetId);
        });
    });

    // Sync sticky nav active state when user manually switches tab via tab pills
    document.querySelectorAll('#budgetDetailTabs button[data-bs-toggle="tab"]').forEach(function(btn) {
        btn.addEventListener('shown.bs.tab', function() {
            var targetPaneId = this.getAttribute('data-bs-target');
            var secId = tabSectionMap[targetPaneId];
            if (secId) {
                var tabSection = document.getElementById('section-detail-tabs');
                if (tabSection) {
                    var rect = tabSection.getBoundingClientRect();
                    if (rect.top < window.innerHeight && rect.bottom > 0) {
                        links.forEach(function(l) { l.classList.remove('active'); });
                        var activeLink = nav.querySelector('a[href="#' + secId + '"]');
                        if (activeLink) {
                            activeLink.classList.add('active');
                            activeLink.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
                        }
                    }
                }
            }
        });
    });

    // IntersectionObserver to highlight active link
    var observerOptions = {
        root: null,
        rootMargin: '-80px 0px -60% 0px',
        threshold: 0
    };

    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                var id = entry.target.id;
                if (id === 'section-detail-tabs') {
                    var activeTabBtn = document.querySelector('#budgetDetailTabs .nav-link.active');
                    if (activeTabBtn) {
                        var targetPaneId = activeTabBtn.getAttribute('data-bs-target');
                        var secId = tabSectionMap[targetPaneId];
                        if (secId) {
                            links.forEach(function(l) { l.classList.remove('active'); });
                            var activeLink = nav.querySelector('a[href="#' + secId + '"]');
                            if (activeLink) {
                                activeLink.classList.add('active');
                                activeLink.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
                            }
                        }
                    }
                } else {
                    links.forEach(function(l) { l.classList.remove('active'); });
                    var activeLink = nav.querySelector('a[href="#' + id + '"]');
                    if (activeLink) {
                        activeLink.classList.add('active');
                        activeLink.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
                    }
                }
            }
        });
    }, observerOptions);

    ['section-ringkasan', 'section-grafik', 'section-detail-bulan', 'section-detail-tabs', 'section-breakdown'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) observer.observe(el);
    });
})();

// =========================================================================
// Tree Accessibility & Collapsible Helpers
// =========================================================================
function toggleTreeNode(el) {
    if (!el || !el.parentElement) return;
    var parent = el.parentElement;
    parent.classList.toggle('tree-collapsed');
    var isExpanded = !parent.classList.contains('tree-collapsed');
    el.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
}

function toggleDeviasiNode(el) {
    if (!el || !el.parentElement) return;
    var parent = el.parentElement;
    parent.classList.toggle('d-collapsed');
    var isExpanded = !parent.classList.contains('d-collapsed');
    el.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
}

function handleTreeKey(e, el) {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        el.click();
    }
}

// =========================================================================
// Mobile Responsive Toggle for Monthly Absorption Table
// =========================================================================
document.addEventListener('DOMContentLoaded', function() {
    var toggleBtn = document.getElementById('toggleMonthlyDetailMobile');
    var table = document.querySelector('.monthly-absorption-table');
    var toggleText = document.getElementById('toggleMonthlyDetailText');
    if (toggleBtn && table) {
        toggleBtn.addEventListener('click', function() {
            var expanded = table.classList.toggle('is-expanded-months');
            if (toggleText) {
                toggleText.textContent = expanded ? 'Sembunyikan Detail Bulan' : 'Lihat Semua Bulan (12 Bln)';
            }
            toggleBtn.classList.toggle('active', expanded);
        });
    }
});
