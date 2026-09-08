<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;

class KasBank
{
    private PDO $db;
    public const DEFAULT_PLAFOND_UP = 84117000.00;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Ambil semua transaksi mutasi kas/bank pada bulan & tahun tertentu
     */
    public function getByPeriode(int $bulan, int $tahun): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT k.*, u.username AS created_by_name
                FROM kas_bank k
                LEFT JOIN users u ON k.created_by = u.id
                WHERE k.bulan = :bulan AND k.tahun = :tahun
                ORDER BY k.tanggal ASC, k.id ASC
            ");
            $stmt->execute([':bulan' => $bulan, ':tahun' => $tahun]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching kas_bank: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Ambil data mutasi kas_bank berdasarkan ID
     */
    public function getById(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT k.*, u.username AS created_by_name
                FROM kas_bank k
                LEFT JOIN users u ON k.created_by = u.id
                WHERE k.id = :id
            ");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('Error fetching kas_bank by id: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Tambah mutasi penerimaan kas/bank baru
     */
    public function create(
        string $tanggal,
        string $jenis,
        ?string $nomorBukti,
        string $keterangan,
        float $nominal,
        int $createdBy
    ): int {
        try {
            $time = strtotime($tanggal) ?: time();
            $bulan = (int) date('m', $time);
            $tahun = (int) date('Y', $time);

            $stmt = $this->db->prepare("
                INSERT INTO kas_bank (tanggal, tahun, bulan, jenis, nomor_bukti, keterangan, nominal, created_by)
                VALUES (:tanggal, :tahun, :bulan, :jenis, :nomor_bukti, :keterangan, :nominal, :created_by)
            ");
            $stmt->execute([
                ':tanggal'     => $tanggal,
                ':tahun'       => $tahun,
                ':bulan'       => $bulan,
                ':jenis'       => $jenis,
                ':nomor_bukti' => !empty($nomorBukti) ? trim($nomorBukti) : null,
                ':keterangan'  => trim($keterangan),
                ':nominal'     => $nominal,
                ':created_by'  => $createdBy,
            ]);
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Error creating kas_bank: ' . $e->getMessage());
            throw new \RuntimeException('Gagal menambahkan mutasi kas: ' . $e->getMessage());
        }
    }

    /**
     * Update mutasi kas/bank
     */
    public function update(
        int $id,
        string $tanggal,
        string $jenis,
        ?string $nomorBukti,
        string $keterangan,
        float $nominal
    ): bool {
        try {
            $time = strtotime($tanggal) ?: time();
            $bulan = (int) date('m', $time);
            $tahun = (int) date('Y', $time);

            $stmt = $this->db->prepare("
                UPDATE kas_bank
                SET tanggal = :tanggal,
                    tahun = :tahun,
                    bulan = :bulan,
                    jenis = :jenis,
                    nomor_bukti = :nomor_bukti,
                    keterangan = :keterangan,
                    nominal = :nominal
                WHERE id = :id
            ");
            return $stmt->execute([
                ':tanggal'     => $tanggal,
                ':tahun'       => $tahun,
                ':bulan'       => $bulan,
                ':jenis'       => $jenis,
                ':nomor_bukti' => !empty($nomorBukti) ? trim($nomorBukti) : null,
                ':keterangan'  => trim($keterangan),
                ':nominal'     => $nominal,
                ':id'          => $id,
            ]);
        } catch (PDOException $e) {
            error_log('Error updating kas_bank: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Hapus mutasi kas/bank
     */
    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM kas_bank WHERE id = :id");
            return $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            error_log('Error deleting kas_bank: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Hitung ringkasan kas & bank, saldo berjalan, pengeluaran diverifikasi, dan estimasi GU
     */
    public function getRingkasan(int $bulan, int $tahun, float $plafondUp = self::DEFAULT_PLAFOND_UP): array
    {
        try {
            // 1. Ambil data penerimaan kas_bank
            $stmtKas = $this->db->prepare("
                SELECT jenis, SUM(nominal) as total
                FROM kas_bank
                WHERE bulan = :bulan AND tahun = :tahun
                GROUP BY jenis
            ");
            $stmtKas->execute([':bulan' => $bulan, ':tahun' => $tahun]);
            $kasRows = $stmtKas->fetchAll(PDO::FETCH_KEY_PAIR);

            $saldoAwal     = (float) ($kasRows['saldo_awal'] ?? 0);
            $pencairanUp   = (float) ($kasRows['up'] ?? 0);
            $pencairanGu   = (float) ($kasRows['gu'] ?? 0);
            $penerimaanLain = (float) (($kasRows['setoran'] ?? 0) + ($kasRows['lainnya'] ?? 0));
            $totalPenerimaan = $saldoAwal + $pencairanUp + $pencairanGu + $penerimaanLain;

            // 2. Ambil total pengeluaran transaksi diverifikasi di bulan & tahun ini
            $stmtTrx = $this->db->prepare("
                SELECT COALESCE(SUM(nilai), 0)
                FROM transaksi
                WHERE status = 'diverifikasi'
                  AND MONTH(tanggal) = :bulan
                  AND YEAR(tanggal) = :tahun
            ");
            $stmtTrx->execute([':bulan' => $bulan, ':tahun' => $tahun]);
            $totalPengeluaranDiverifikasi = (float) $stmtTrx->fetchColumn();

            // 3. Ambil total pengeluaran transaksi yang masih diajukan (pending)
            $stmtPending = $this->db->prepare("
                SELECT COALESCE(SUM(nilai), 0)
                FROM transaksi
                WHERE status = 'diajukan'
                  AND MONTH(tanggal) = :bulan
                  AND YEAR(tanggal) = :tahun
            ");
            $stmtPending->execute([':bulan' => $bulan, ':tahun' => $tahun]);
            $totalPengeluaranPending = (float) $stmtPending->fetchColumn();

            // 4. Hitung Saldo Kas / Bank saat ini
            $saldoKasSaatIni = $totalPenerimaan - $totalPengeluaranDiverifikasi;

            // 5. Estimasi GU yang dapat dimintakan / belum cair
            // Formula: Plafond UP - Saldo Kas saat ini (atau total pengeluaran diverifikasi yang belum di-GU)
            $estimasiGu = max(0, $plafondUp - $saldoKasSaatIni);

            // Persentase ketersediaan kas likuid terhadap Plafond UP
            $persenKasTersedia = $plafondUp > 0 ? max(0, min(100, ($saldoKasSaatIni / $plafondUp) * 100)) : 0;

            return [
                'bulan'                         => $bulan,
                'tahun'                         => $tahun,
                'plafond_up'                    => $plafondUp,
                'saldo_awal'                    => $saldoAwal,
                'pencairan_up'                  => $pencairanUp,
                'pencairan_gu'                  => $pencairanGu,
                'penerimaan_lain'               => $penerimaanLain,
                'total_penerimaan'              => $totalPenerimaan,
                'total_pengeluaran_diverifikasi' => $totalPengeluaranDiverifikasi,
                'total_pengeluaran_pending'     => $totalPengeluaranPending,
                'saldo_kas_saat_ini'            => $saldoKasSaatIni,
                'estimasi_gu_cair'              => $estimasiGu,
                'persen_kas_tersedia'           => $persenKasTersedia,
            ];
        } catch (PDOException $e) {
            error_log('Error calculating ringkasan kas: ' . $e->getMessage());
            return [
                'bulan'                         => $bulan,
                'tahun'                         => $tahun,
                'plafond_up'                    => $plafondUp,
                'saldo_awal'                    => 0,
                'pencairan_up'                  => 0,
                'pencairan_gu'                  => 0,
                'penerimaan_lain'               => 0,
                'total_penerimaan'              => 0,
                'total_pengeluaran_diverifikasi' => 0,
                'total_pengeluaran_pending'     => 0,
                'saldo_kas_saat_ini'            => 0,
                'estimasi_gu_cair'              => 0,
                'persen_kas_tersedia'           => 0,
            ];
        }
    }
}
