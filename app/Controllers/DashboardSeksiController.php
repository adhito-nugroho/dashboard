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

    /**
     * Ambil daftar rekening_id milik seksi ini
     */
    private function getRekeningIds(\PDO $db, int $seksi_id): array {
        $stmt = $db->prepare("
            SELECT rek.id FROM rekening rek
            INNER JOIN sub_kegiatan sk ON sk.id = rek.sub_kegiatan_id
            WHERE sk.seksi_id = ?
        ");
        $stmt->execute([$seksi_id]);
        return array_column($stmt->fetchAll(), 'id');
    }

    /**
     * Ambil daftar sub_kegiatan_id milik seksi ini
     */
    private function getSubKegiatanIds(\PDO $db, int $seksi_id): array {
        $stmt = $db->prepare("SELECT id FROM sub_kegiatan WHERE seksi_id = ?");
        $stmt->execute([$seksi_id]);
        return array_column($stmt->fetchAll(), 'id');
    }

    private function renderDashboard(string $kode_seksi, string $nama_seksi): void {
        try {
            $db = \Database::getConnection();
            $tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');
            
            $seksiStmt = $db->prepare("SELECT id, nama_seksi FROM seksi WHERE kode_seksi = ?");
            $seksiStmt->execute([$kode_seksi]);
            $seksi = $seksiStmt->fetch();
            
            if (!$seksi) {
                throw new \Exception("Seksi tidak ditemukan");
            }
            
            $seksi_id = $seksi['id'];
            $subKegiatanIds = $this->getSubKegiatanIds($db, $seksi_id);

            if (empty($subKegiatanIds)) {
                // Tidak ada sub_kegiatan untuk seksi ini
                $this->renderEmptyDashboard($nama_seksi, $tahun);
                return;
            }

            $placeholders = implode(',', array_fill(0, count($subKegiatanIds), '?'));

            // ===== STATISTIK (Total Pagu, RAK, Realisasi) =====
            // Hitung via subquery supaya tidak double-count akibat multi-join
            $query = "
                SELECT
                    (SELECT COALESCE(SUM(p.nilai_pagu), 0)
                     FROM rekening rek
                     INNER JOIN sub_kegiatan sk ON sk.id = rek.sub_kegiatan_id
                     INNER JOIN pagu p ON p.rekening_id = rek.id AND p.tahun = ?
                     WHERE sk.id IN ($placeholders)) AS total_pagu,

                    (SELECT COALESCE(SUM(r.nilai_rak), 0)
                     FROM rekening rek
                     INNER JOIN sub_kegiatan sk ON sk.id = rek.sub_kegiatan_id
                     INNER JOIN rak r ON r.rekening_id = rek.id AND r.tahun = ?
                     WHERE sk.id IN ($placeholders)) AS total_rak,

                    (SELECT COALESCE(SUM(t.nilai), 0)
                     FROM rekening rek
                     INNER JOIN sub_kegiatan sk ON sk.id = rek.sub_kegiatan_id
                     INNER JOIN transaksi t ON t.rekening_id = rek.id AND YEAR(t.tanggal) = ?
                     WHERE sk.id IN ($placeholders)) AS total_realisasi
            ";

            $params = array_merge([$tahun], $subKegiatanIds, [$tahun], $subKegiatanIds, [$tahun], $subKegiatanIds);
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $stats = $stmt->fetch();

            $stats['total_pagu'] = (float) $stats['total_pagu'];
            $stats['total_rak'] = (float) $stats['total_rak'];
            $stats['total_realisasi'] = (float) $stats['total_realisasi'];
            $stats['sisa_anggaran'] = $stats['total_pagu'] - $stats['total_realisasi'];
            $stats['percentage'] = $stats['total_pagu'] > 0 ? ($stats['total_realisasi'] / $stats['total_pagu']) * 100 : 0;
            $stats['tahun'] = $tahun;

            // ===== DATA BULANAN =====
            $monthlyQuery = "
                SELECT 
                    MONTH(t.tanggal) as bulan,
                    COALESCE(SUM(t.nilai), 0) as realisasi
                FROM transaksi t
                INNER JOIN rekening rek ON rek.id = t.rekening_id
                INNER JOIN sub_kegiatan sk ON sk.id = rek.sub_kegiatan_id
                WHERE YEAR(t.tanggal) = ? AND sk.id IN ($placeholders)
                GROUP BY MONTH(t.tanggal)
            ";
            $monthlyParams = array_merge([$tahun], $subKegiatanIds);
            $monthlyStmt = $db->prepare($monthlyQuery);
            $monthlyStmt->execute($monthlyParams);
            $monthlyResults = $monthlyStmt->fetchAll();

            $rakQuery = "
                SELECT 
                    r.bulan,
                    COALESCE(SUM(r.nilai_rak), 0) as total_rak
                FROM rak r
                INNER JOIN rekening rek ON rek.id = r.rekening_id
                INNER JOIN sub_kegiatan sk ON sk.id = rek.sub_kegiatan_id
                WHERE r.tahun = ? AND sk.id IN ($placeholders)
                GROUP BY r.bulan
            ";
            $rakParams = array_merge([$tahun], $subKegiatanIds);
            $rakStmt = $db->prepare($rakQuery);
            $rakStmt->execute($rakParams);
            $rakResults = $rakStmt->fetchAll();

            $monthlyData = ['realisasi' => [], 'rak' => []];
            for ($i = 1; $i <= 12; $i++) {
                $monthlyData['realisasi'][$i] = 0;
                $monthlyData['rak'][$i] = 0;
            }
            
            foreach ($monthlyResults as $row) {
                $monthlyData['realisasi'][(int)$row['bulan']] = (float)$row['realisasi'];
            }
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

    private function renderEmptyDashboard(string $nama_seksi, int $tahun): void {
        $stats = [
            'total_pagu' => 0, 'total_rak' => 0, 'total_realisasi' => 0,
            'sisa_anggaran' => 0, 'percentage' => 0, 'tahun' => $tahun
        ];
        $monthlyData = ['realisasi' => array_fill(1,12,0), 'rak' => array_fill(1,12,0)];
        $pageTitle = "Dashboard $nama_seksi";
        include __DIR__ . '/../../views/dashboard/seksi.php';
    }
}
