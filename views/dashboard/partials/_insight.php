    <!-- Insight Banner -->
    <?php
    // Hitung jumlah bulan under-RAK & over-RAK untuk bulan yang sudah berjalan
    $underAlertsCount = 0;
    $overAlertsCount = 0;
    if (!empty($monthlyData['alerts'])) {
        $underAlertsCount = count(array_filter($monthlyData['alerts'], fn($a) =>
            $a['type'] === 'under' && $a['bulan'] <= $bulanBerjalan
        ));
        $overAlertsCount = count(array_filter($monthlyData['alerts'], fn($a) =>
            $a['type'] === 'over' && $a['bulan'] <= $bulanBerjalan
        ));
    }

    // Cek apakah ada over-RAK signifikan (selisih > 20% dari RAK bulan tsb) dalam 2 bulan terakhir
    $hasSignificantOverLast2Months = false;
    $startCheckMonth = max(1, $bulanBerjalan - 1);
    for ($m = $startCheckMonth; $m <= $bulanBerjalan; $m++) {
        $mRak = (float)($monthlyData['rak'][$m] ?? 0);
        $mReal = (float)($monthlyData['realisasi'][$m] ?? 0);
        if ($mRak > 0 && (($mReal - $mRak) / $mRak) > 0.20) {
            $hasSignificantOverLast2Months = true;
            break;
        }
    }

    // Tentukan pesan dan styling banner berdasarkan capaian terhadap target RAK kumulatif
    $iTextColor = '#ffffff';
    $iIconBg = 'rgba(255,255,255,0.2)';

    if ($stats['percentage'] > 100) {
        $iIcon = 'exclamation-octagon-fill';
        $iText = 'Perhatian! Realisasi anggaran telah <strong>melebihi pagu</strong>.';
        $iBg = 'linear-gradient(135deg, #dc2626 0%, #ef4444 100%)';
        $iBorder = '#dc2626';
    } elseif ($targetRakBulanBerjalan > 0) {
        // Jika ada target RAK s/d bulan berjalan
        if ($capaianRakBulanBerjalan >= 90 && $underAlertsCount < 3) {
            $iIcon = 'check-circle-fill';
            $iText = 'Realisasi anggaran berjalan sesuai target.';
            $iBg = 'linear-gradient(135deg, #059669 0%, #10b981 100%)';
            $iBorder = '#059669';
        } elseif ($capaianRakBulanBerjalan >= 70 && $underAlertsCount < 3) {
            $iIcon = 'exclamation-triangle-fill';
            $iText = 'Realisasi anggaran sedikit di bawah target, perlu percepatan.';
            $iBg = 'linear-gradient(135deg, #d97706 0%, #f59e0b 100%)';
            $iBorder = '#d97706';
        } elseif ($underAlertsCount >= 3 && $capaianRakBulanBerjalan >= 70) {
            $iIcon = 'exclamation-triangle-fill';
            $iText = 'Realisasi anggaran sedikit di bawah target, perlu percepatan.';
            $iBg = 'linear-gradient(135deg, #d97706 0%, #f59e0b 100%)';
            $iBorder = '#d97706';
        } else {
            $iIcon = 'exclamation-octagon-fill';
            $iText = 'Realisasi anggaran tertinggal signifikan dari target RAK.';
            $iBg = 'linear-gradient(135deg, #dc2626 0%, #ef4444 100%)';
            $iBorder = '#dc2626';
        }
    } else {
        // Fallback jika belum ada konfigurasi RAK bulan berjalan
        if ($stats['percentage'] >= 90) {
            $iIcon = 'exclamation-triangle-fill';
            $iText = 'Realisasi mendekati pagu total! Harap tinjau pengeluaran.';
            $iBg = 'linear-gradient(135deg, #d97706 0%, #f59e0b 100%)';
            $iBorder = '#d97706';
        } elseif ($stats['percentage'] < 20 && $bulanBerjalan > 3) {
            $iIcon = 'exclamation-octagon-fill';
            $iText = 'Realisasi anggaran tertinggal signifikan dari target.';
            $iBg = 'linear-gradient(135deg, #dc2626 0%, #ef4444 100%)';
            $iBorder = '#dc2626';
        } else {
            $iIcon = 'check-circle-fill';
            $iText = 'Realisasi anggaran berjalan normal.';
            $iBg = 'linear-gradient(135deg, #059669 0%, #10b981 100%)';
            $iBorder = '#059669';
        }
    }

    // Tambahkan catatan jika ada bulan over-RAK signifikan dalam 2 bulan terakhir
    if ($hasSignificantOverLast2Months && $stats['percentage'] <= 100) {
        $iText = rtrim($iText, '.') . ', beberapa bulan terakhir realisasi melebihi RAK.';
    }
    ?>
    <div class="insight-banner mb-4 animate-fade-in-up delay-1" style="background:<?= $iBg ?>;border-color:<?= $iBorder ?>;color:<?= $iTextColor ?>;">
        <div class="insight-icon" style="background:<?= $iIconBg ?>;color:#fff;border:1.5px solid rgba(255,255,255,0.3);"><i class="bi bi-<?= $iIcon ?>"></i></div>
        <div style="flex:1;">
            <strong>Insight:</strong> <?= $iText ?>
            <span class="d-none d-md-inline ms-2" style="opacity:0.9;">&mdash; Serapan saat ini <strong style="font-size:var(--fs-md);"><?= number_format($stats['percentage'], 2) ?>%</strong></span>
        </div>
        <button type="button" class="btn-close btn-close-white" style="font-size:var(--fs-xs);opacity:0.8;" onclick="this.closest('.insight-banner').remove()"></button>
    </div>

