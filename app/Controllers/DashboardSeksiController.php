<?php

namespace App\Controllers;

require_once __DIR__ . '/../../config/database.php';

class DashboardSeksiController {
    
    public function showTU(): void {
        $this->checkRole('tu');
        $this->renderDashboard('TU', 'Tata Usaha');
    }

    public function showRLPM(): void {
        $this->checkRole('rlpm');
        $this->renderDashboard('RLPM', 'Rencana, Laporan dan Pemanfaatan Hutan');
    }

    public function showTKUK(): void {
        $this->checkRole('tkuk');
        $this->renderDashboard('TKUK', 'Teknik Konservasi dan Usaha Kehutanan');
    }

    private function checkRole(string $requiredRole): void {
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== $requiredRole) {
            header('Location: ' . base_url('login'));
            exit;
        }
    }

    private function renderDashboard(string $kode_seksi, string $nama_seksi): void {
        try {
            $db = \Database::getConnection();
            $tahun = $_GET['tahun'] ?? date('Y');
            
            $seksiStmt = $db->prepare("SELECT id FROM seksi WHERE kode_seksi = ?");
            $seksiStmt->execute([$kode_seksi]);
            $seksi = $seksiStmt->fetch();
            
            if (!$seksi) {
                throw new \Exception("Seksi tidak ditemukan");
            }
            
            $seksi_id = $seksi['id'];
            
            $query = "
                SELECT 
                    COALESCE(SUM(p.nilai_pagu), 0) as total_pagu,
                    COALESCE(SUM(r.nilai_rak), 0) as total_rak,
                    COALESCE(SUM(t.nilai), 0) as total_realisasi
                FROM sub_kegiatan sk
                LEFT JOIN rekening rek ON rek.sub_kegiatan_id = sk.id
                LEFT JOIN pagu p ON p.rekening_id = rek.id AND p.tahun = ?
                LEFT JOIN rak r ON r.rekening_id = rek.id AND r.tahun = ?
                LEFT JOIN transaksi t ON t.rekening_id = rek.id AND YEAR(t.tanggal) = ?
                WHERE sk.seksi_id = ?
            ";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$tahun, $tahun, $tahun, $seksi_id]);
            $stats = $stmt->fetch();
            
            $stats['total_pagu'] = (float) $stats['total_pagu'];
            $stats['total_rak'] = (float) $stats['total_rak'];
            $stats['total_realisasi'] = (float) $stats['total_realisasi'];
            $stats['sisa_anggaran'] = $stats['total_pagu'] - $stats['total_realisasi'];
            $stats['percentage'] = $stats['total_pagu'] > 0 ? ($stats['total_realisasi'] / $stats['total_pagu']) * 100 : 0;
            $stats['tahun'] = $tahun;
            
            $monthlyQuery = "
                SELECT 
                    MONTH(t.tanggal) as bulan,
                    COALESCE(SUM(t.nilai), 0) as realisasi
                FROM transaksi t
                INNER JOIN rekening rek ON rek.id = t.rekening_id
                INNER JOIN sub_kegiatan sk ON sk.id = rek.sub_kegiatan_id
                WHERE YEAR(t.tanggal) = ? AND sk.seksi_id = ?
                GROUP BY MONTH(t.tanggal)
            ";
            
            $monthlyStmt = $db->prepare($monthlyQuery);
            $monthlyStmt->execute([$tahun, $seksi_id]);
            $monthlyResults = $monthlyStmt->fetchAll();
            
            $monthlyData = ['realisasi' => [], 'rak' => []];
            for ($i = 1; $i <= 12; $i++) {
                $monthlyData['realisasi'][$i] = 0;
                $monthlyData['rak'][$i] = 0;
            }
            
            foreach ($monthlyResults as $row) {
                $monthlyData['realisasi'][(int)$row['bulan']] = (float)$row['realisasi'];
            }
            
            $rakQuery = "
                SELECT 
                    r.bulan,
                    COALESCE(SUM(r.nilai_rak), 0) as total_rak
                FROM rak r
                INNER JOIN rekening rek ON rek.id = r.rekening_id
                INNER JOIN sub_kegiatan sk ON sk.id = rek.sub_kegiatan_id
                WHERE r.tahun = ? AND sk.seksi_id = ?
                GROUP BY r.bulan
            ";
            
            $rakStmt = $db->prepare($rakQuery);
            $rakStmt->execute([$tahun, $seksi_id]);
            $rakResults = $rakStmt->fetchAll();
            
            foreach ($rakResults as $row) {
                $monthlyData['rak'][(int)$row['bulan']] = (float)$row['total_rak'];
            }
            
            $pageTitle = "Dashboard $nama_seksi";
            include __DIR__ . '/../../views/dashboard/seksi.php';
            
        } catch (\Exception $e) {
            error_log('Dashboard error: ' . $e->getMessage());
            die('Terjadi kesalahan saat memuat dashboard');
        }
    }
}
