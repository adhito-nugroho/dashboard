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
     * Hitung ringkasan kas & bank (SALDO BERJALAN / KUMULATIF):
     * - Saldo kas riil = saldo awal baseline (saldo_awal terakhir <= akhir periode)
     *   + seluruh penerimaan CAIR sejak baseline s/d akhir periode
     *   - seluruh belanja UP diverifikasi sejak baseline s/d akhir periode.
     *   Baseline dipakai agar belanja lama sebelum saldo awal (mis. Jan) yang
     *   sudah tercermin di saldo awal Sept tidak mengurangi saldo dua kali.
     *   Jika belum ada baris saldo_awal, fallback ke kumulatif murni
     *   (seluruh cair - seluruh keluar) agar minus data yang hilang tetap terlihat.
     * - GU menunggu cair = kumulatif (seluruh pengajuan s/d akhir periode),
     *   agar SPJ bulan lalu tetap tampil saat filter bulan berjalan.
     * - Rincian per-bulan (saldo_awal, pencairan_up/gu, total_penerimaan_cair,
     *   total_pengeluaran_diverifikasi, belanja_siap_gu) tetap dipertahankan
     *   untuk tabel & badge periode berjalan.
     */
    public function getRingkasan(int $bulan, int $tahun, float $plafondUp = self::DEFAULT_PLAFOND_UP): array
    {
        try {
            // 1. Rincian penerimaan CAIR periode berjalan (untuk tabel & badge)
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

            // 2. Belanja UP diverifikasi periode berjalan (siap SPJ / GU berikutnya)
            $stmtTrx = $this->db->prepare("
                SELECT COALESCE(SUM(nilai), 0)
                FROM transaksi
                WHERE status = 'diverifikasi'
                  AND sumber_dana = 'UP'
                  AND MONTH(COALESCE(tanggal_lunas_dibayar, DATE(diverifikasi_at), tanggal)) = :bulan
                  AND YEAR(COALESCE(tanggal_lunas_dibayar, DATE(diverifikasi_at), tanggal)) = :tahun
            ");
            $stmtTrx->execute([':bulan' => $bulan, ':tahun' => $tahun]);
            $totalPengeluaranDiverifikasi = (float) $stmtTrx->fetchColumn();

            // 3. Saldo berjalan kumulatif s/d akhir periode diminta.
            //    Basis tanggal cair = COALESCE(tanggal_cair, tanggal) agar baris
            //    lama yang tanggal_cair-nya NULL tetap terhitung.
            $cutoff = sprintf('%04d-%02d-%02d', $tahun, $bulan, (int) date('t', mktime(0, 0, 0, $bulan, 1, $tahun)));

            // Cari baseline: saldo_awal CAIR terakhir <= cutoff.
            $stmtBase = $this->db->prepare("
                SELECT id, COALESCE(tanggal_cair, tanggal) AS tgl_efektif, nominal
                FROM kas_bank
                WHERE jenis = 'saldo_awal' AND status = 'cair'
                  AND COALESCE(tanggal_cair, tanggal) <= :cutoff
                ORDER BY COALESCE(tanggal_cair, tanggal) DESC, id DESC
                LIMIT 1
            ");
            $stmtBase->execute([':cutoff' => $cutoff]);
            $baseline = $stmtBase->fetch(PDO::FETCH_ASSOC);

            if ($baseline) {
                $baselineId      = (int) $baseline['id'];
                $baselineTanggal = (string) $baseline['tgl_efektif'];
                $baselineNominal = (float) $baseline['nominal'];

                $stmtCumIn = $this->db->prepare("
                    SELECT COALESCE(SUM(nominal), 0)
                    FROM kas_bank
                    WHERE status = 'cair'
                      AND id != :base_id
                      AND COALESCE(tanggal_cair, tanggal) >= :base_tgl
                      AND COALESCE(tanggal_cair, tanggal) <= :cutoff
                ");
                $stmtCumIn->execute([
                    ':base_id'   => $baselineId,
                    ':base_tgl'  => $baselineTanggal,
                    ':cutoff'    => $cutoff,
                ]);
                $kumulatifPenerimaan = (float) $stmtCumIn->fetchColumn();

                $stmtCumOut = $this->db->prepare("
                    SELECT COALESCE(SUM(nilai), 0)
                    FROM transaksi
                    WHERE status = 'diverifikasi'
                      AND sumber_dana = 'UP'
                      AND COALESCE(tanggal_lunas_dibayar, DATE(diverifikasi_at), tanggal) >= :base_tgl
                      AND COALESCE(tanggal_lunas_dibayar, DATE(diverifikasi_at), tanggal) <= :cutoff
                ");
                $stmtCumOut->execute([':base_tgl' => $baselineTanggal, ':cutoff' => $cutoff]);
                $kumulatifPengeluaran = (float) $stmtCumOut->fetchColumn();

                $saldoKasSaatIni = $baselineNominal + $kumulatifPenerimaan - $kumulatifPengeluaran;
            } else {
                // Fallback: belum ada saldo_awal -> kumulatif murni seluruh histori.
                $baselineTanggal = null;
                $baselineNominal = 0.0;

                $stmtCumIn = $this->db->prepare("
                    SELECT COALESCE(SUM(nominal), 0)
                    FROM kas_bank
                    WHERE status = 'cair'
                      AND COALESCE(tanggal_cair, tanggal) <= :cutoff
                ");
                $stmtCumIn->execute([':cutoff' => $cutoff]);
                $kumulatifPenerimaan = (float) $stmtCumIn->fetchColumn();

                $stmtCumOut = $this->db->prepare("
                    SELECT COALESCE(SUM(nilai), 0)
                    FROM transaksi
                    WHERE status = 'diverifikasi'
                      AND sumber_dana = 'UP'
                      AND COALESCE(tanggal_lunas_dibayar, DATE(diverifikasi_at), tanggal) <= :cutoff
                ");
                $stmtCumOut->execute([':cutoff' => $cutoff]);
                $kumulatifPengeluaran = (float) $stmtCumOut->fetchColumn();

                $saldoKasSaatIni = $kumulatifPenerimaan - $kumulatifPengeluaran;
            }

            // 4. GU menunggu cair KUMULATIF s/d cutoff (bukan hanya bulan ini),
            //    agar pengajuan SPJ bulan lalu tetap tampil di bulan berjalan.
            $stmtPending = $this->db->prepare("
                SELECT COALESCE(SUM(nominal), 0)
                FROM kas_bank
                WHERE status = 'menunggu_cair'
                  AND tanggal <= :cutoff
            ");
            $stmtPending->execute([':cutoff' => $cutoff]);
            $guMenungguCair = (float) $stmtPending->fetchColumn();

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
                'cutoff'                         => $cutoff,
                'baseline_tanggal'               => $baselineTanggal,
                'baseline_nominal'               => $baselineNominal,
                'kumulatif_penerimaan'           => $kumulatifPenerimaan,
                'kumulatif_pengeluaran'          => $kumulatifPengeluaran,
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
