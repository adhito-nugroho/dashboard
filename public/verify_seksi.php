<?php
// Verify angka dashboard per seksi vs total
session_start();
require_once __DIR__ . '/../config/load_env.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== VERIFIKASI ANGGARAN PER SEKSI ===\n\n";

try {
    $db = Database::getConnection();
    $tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
    
    echo "Tahun: $tahun\n\n";
    
    $seksis = $db->query("SELECT id, kode_seksi, nama_seksi FROM seksi ORDER BY id")->fetchAll();
    
    echo "--- Ringkasan per Seksi ---\n";
    
    $grandPagu = 0; $grandRak = 0; $grandReal = 0;
    
    foreach ($seksis as $seksi) {
        $skIds = array_column(
            $db->query("SELECT id FROM sub_kegiatan WHERE seksi_id = {$seksi['id']}")->fetchAll(),
            'id'
        );
        
        if (empty($skIds)) {
            echo "\n[{$seksi['kode_seksi']}] {$seksi['nama_seksi']}\n";
            echo "  Sub kegiatan: 0\n";
            continue;
        }
        
        $ph = implode(',', array_fill(0, count($skIds), '?'));
        
        // Pagu
        $st = $db->prepare("SELECT COALESCE(SUM(p.nilai_pagu),0) v FROM rekening rek INNER JOIN sub_kegiatan sk ON sk.id=rek.sub_kegiatan_id INNER JOIN pagu p ON p.rekening_id=rek.id AND p.tahun=? WHERE sk.id IN ($ph)");
        $st->execute(array_merge([$tahun], $skIds));
        $pagu = (float)$st->fetch()['v'];
        
        // RAK
        $st = $db->prepare("SELECT COALESCE(SUM(r.nilai_rak),0) v FROM rekening rek INNER JOIN sub_kegiatan sk ON sk.id=rek.sub_kegiatan_id INNER JOIN rak r ON r.rekening_id=rek.id AND r.tahun=? WHERE sk.id IN ($ph)");
        $st->execute(array_merge([$tahun], $skIds));
        $rak = (float)$st->fetch()['v'];
        
        // Realisasi
        $st = $db->prepare("SELECT COALESCE(SUM(t.nilai),0) v FROM rekening rek INNER JOIN sub_kegiatan sk ON sk.id=rek.sub_kegiatan_id INNER JOIN transaksi t ON t.rekening_id=rek.id AND YEAR(t.tanggal)=? WHERE sk.id IN ($ph)");
        $st->execute(array_merge([$tahun], $skIds));
        $real = (float)$st->fetch()['v'];
        
        $pct = $pagu > 0 ? ($real/$pagu)*100 : 0;
        
        echo "\n[{$seksi['kode_seksi']}] {$seksi['nama_seksi']} (sub kegiatan: " . count($skIds) . ")\n";
        echo "  Pagu     : Rp " . number_format($pagu,0,',','.') . "\n";
        echo "  RAK      : Rp " . number_format($rak,0,',','.') . "\n";
        echo "  Realisasi: Rp " . number_format($real,0,',','.') . "\n";
        echo "  Serapan  : " . number_format($pct,2) . "%\n";
        
        $grandPagu += $pagu; $grandRak += $rak; $grandReal += $real;
    }
    
    echo "\n" . str_repeat('-', 45) . "\n";
    echo "TOTAL SEMUA SEKSI:\n";
    echo "  Pagu     : Rp " . number_format($grandPagu,0,',','.') . "\n";
    echo "  RAK      : Rp " . number_format($grandRak,0,',','.') . "\n";
    echo "  Realisasi: Rp " . number_format($grandReal,0,',','.') . "\n";
    echo "  Serapan  : " . number_format($grandPagu>0?($grandReal/$grandPagu)*100:0,2) . "%\n";
    
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}
