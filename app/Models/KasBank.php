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
        int $createdBy,
        string $status = 'cair',
        ?string $tanggalCair = null
    ): int {
        try {
            $time = strtotime($tanggal) ?: time();
            $bulan = (int) date('m', $time);
            $tahun = (int) date('Y', $time);

            if ($status === 'cair' && empty($tanggalCair)) {
                $tanggalCair = $tanggal;
            }

            $stmt = $this->db->prepare("
                INSERT INTO kas_bank (tanggal, tanggal_cair, tahun, bulan, jenis, status, nomor_bukti, keterangan, nominal, created_by)
                VALUES (:tanggal, :tanggal_cair, :tahun, :bulan, :jenis, :status, :nomor_bukti, :keterangan, :nominal, :created_by)
            ");
            $stmt->execute([
                ':tanggal'      => $tanggal,
                ':tanggal_cair' => $tanggalCair,
                ':tahun'        => $tahun,
                ':bulan'        => $bulan,
                ':jenis'        => $jenis,
                ':status'       => $status,
                ':nomor_bukti'  => !empty($nomorBukti) ? trim($nomorBukti) : null,
                ':keterangan'   => trim($keterangan),
                ':nominal'      => $nominal,
                ':created_by'   => $createdBy,
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
        float $nominal,
        string $status = 'cair',
        ?string $tanggalCair = null
    ): bool {
        try {
            $time = strtotime($tanggal) ?: time();
            $bulan = (int) date('m', $time);
            $tahun = (int) date('Y', $time);

            if ($status === 'cair' && empty($tanggalCair)) {
                $tanggalCair = $tanggal;
            }

            $stmt = $this->db->prepare("
                UPDATE kas_bank
                SET tanggal = :tanggal,
                    tanggal_cair = :tanggal_cair,
                    tahun = :tahun,
                    bulan = :bulan,
                    jenis = :jenis,
                    status = :status,
                    nomor_bukti = :nomor_bukti,
                    keterangan = :keterangan,
                    nominal = :nominal
                WHERE id = :id
            ");
            return $stmt->execute([
                ':tanggal'      => $tanggal,
                ':tanggal_cair' => $tanggalCair,
                ':tahun'        => $tahun,
                ':bulan'        => $bulan,
                ':jenis'        => $jenis,
                ':status'       => $status,
                ':nomor_bukti'  => !empty($nomorBukti) ? trim($nomorBukti) : null,
                ':keterangan'   => trim($keterangan),
                ':nominal'      => $nominal,
                ':id'           => $id,
            ]);
        } catch (PDOException $e) {
            error_log('Error updating kas_bank: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Tandai mutasi kas (GU) sebagai sudah cair
     */
    public function tandaiCair(int $id, ?string $tanggalCair = null): bool
    {
        try {
            $tgl = !empty($tanggalCair) ? $tanggalCair : date('Y-m-d');
            $stmt = $this->db->prepare("
                UPDATE kas_bank
                SET status = 'cair',
                    tanggal_cair = :tanggal_cair
                WHERE id = :id
            ");
            return $stmt->execute([
                ':tanggal_cair' => $tgl,
                ':id'           => $id,
            ]);
        } catch (PDOException $e) {
            error_log('Error marking kas_bank as cair: ' . $e->getMessage());
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
     * Hitung ringkasan kas & bank:
     * - Saldo kas riil saat ini (hanya dari mutasi yang sudah CAIR dikurangi pengeluaran diverifikasi)
     * - Total GU yang sedang MENUNGGU CAIR (SPJ bulan lalu / periode sebelumnya)
     * - Total Belanja terverifikasi bulan berjalan (siap di-SPJ-kan untuk GU berikutnya)
     * - Proyeksi Saldo Kas setelah GU cair
     */
    public function getRingkasan(int $bulan, int $tahun, float $plafondUp = self::DEFAULT_PLAFOND_UP): array
    {
        try {
            // 1. Ambil data penerimaan kas_bank yang SUDAH CAIR
            $stmtCair = $this->db->prepare("
                SELECT jenis, SUM(nominal) as total
                FROM kas_bank
                WHERE bulan = :bulan AND tahun = :tahun AND status = 'cair'
                GROUP BY jenis
            ");
            $stmtCair->execute([':bulan' => $bulan, ':tahun' => $tahun]);
            $cairRows = $stmtCair->fetchAll(PDO::FETCH_KEY_PAIR);

            $saldoAwal      = (float) ($cairRows['saldo_awal'] ?? 0);
            $pencairanUp    = (float) ($cairRows['up'] ?? 0);
            $pencairanGu    = (float) ($cairRows['gu'] ?? 0);
            $penerimaanLain = (float) (($cairRows['setoran'] ?? 0) + ($cairRows['lainnya'] ?? 0));
            $totalPenerimaanCair = $saldoAwal + $pencairanUp + $pencairanGu + $penerimaanLain;

            // 2. Ambil data penerimaan kas_bank yang MENUNGGU CAIR
            $stmtPending = $this->db->prepare("
                SELECT COALESCE(SUM(nominal), 0)
                FROM kas_bank
                WHERE bulan = :bulan AND tahun = :tahun AND status = 'menunggu_cair'
            ");
            $stmtPending->execute([':bulan' => $bulan, ':tahun' => $tahun]);
            $guMenungguCair = (float) $stmtPending->fetchColumn();

            // 3. Ambil total pengeluaran transaksi diverifikasi di bulan & tahun ini
            $stmtTrx = $this->db->prepare("
                SELECT COALESCE(SUM(nilai), 0)
                FROM transaksi
                WHERE status = 'diverifikasi'
                  AND MONTH(tanggal) = :bulan
                  AND YEAR(tanggal) = :tahun
            ");
            $stmtTrx->execute([':bulan' => $bulan, ':tahun' => $tahun]);
            $totalPengeluaranDiverifikasi = (float) $stmtTrx->fetchColumn();

            // 4. Saldo Kas Riil saat ini
            $saldoKasSaatIni = $totalPenerimaanCair - $totalPengeluaranDiverifikasi;

            // 5. Proyeksi Saldo Kas setelah GU yang menunggu cair masuk
            $proyeksiKasSetelahCair = $saldoKasSaatIni + $guMenungguCair;

            // 6. Belanja bulan ini yang siap di-SPJ-kan untuk pengajuan GU berikutnya
            $belanjaSiapGu = $totalPengeluaranDiverifikasi;

            return [
                'bulan'                          => $bulan,
                'tahun'                          => $tahun,
                'plafond_up'                     => $plafondUp,
                'saldo_awal'                     => $saldoAwal,
                'pencairan_up'                   => $pencairanUp,
                'pencairan_gu'                   => $pencairanGu,
                'penerimaan_lain'                => $penerimaanLain,
                'total_penerimaan_cair'          => $totalPenerimaanCair,
                'total_pengeluaran_diverifikasi' => $totalPengeluaranDiverifikasi,
                'saldo_kas_saat_ini'             => $saldoKasSaatIni,
                'gu_menunggu_cair'               => $guMenungguCair,
                'belanja_siap_gu'                => $belanjaSiapGu,
                'proyeksi_kas_setelah_cair'      => $proyeksiKasSetelahCair,
            ];
        } catch (PDOException $e) {
            error_log('Error calculating ringkasan kas: ' . $e->getMessage());
            return [
                'bulan'                          => $bulan,
                'tahun'                          => $tahun,
                'plafond_up'                     => $plafondUp,
                'saldo_awal'                     => 0,
                'pencairan_up'                   => 0,
                'pencairan_gu'                   => 0,
                'penerimaan_lain'                => 0,
                'total_penerimaan_cair'          => 0,
                'total_pengeluaran_diverifikasi' => 0,
                'saldo_kas_saat_ini'             => 0,
                'gu_menunggu_cair'               => 0,
                'belanja_siap_gu'                => 0,
                'proyeksi_kas_setelah_cair'      => 0,
            ];
        }
    }
}
