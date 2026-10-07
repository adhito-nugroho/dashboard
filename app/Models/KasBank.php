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
     * Baseline saldo awal: baris jenis 'saldo_awal' CAIR terakhir dengan
     * tanggal efektif (tanggal_cair fallback tanggal) <= $batasAkhir ('Y-m-d').
     * Return null bila belum pernah ada saldo_awal.
     *
     * @return array{id:int, tanggal:string, nominal:float}|null
     */
    public function getBaselineSaldoAwal(string $batasAkhir): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, COALESCE(tanggal_cair, tanggal) AS tgl_efektif, nominal
                FROM kas_bank
                WHERE jenis = 'saldo_awal' AND status = 'cair'
                  AND COALESCE(tanggal_cair, tanggal) <= :batas
                ORDER BY COALESCE(tanggal_cair, tanggal) DESC, id DESC
                LIMIT 1
            ");
            $stmt->execute([':batas' => $batasAkhir]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }
            return [
                'id'      => (int) $row['id'],
                'tanggal' => (string) $row['tgl_efektif'],
                'nominal' => (float) $row['nominal'],
            ];
        } catch (PDOException $e) {
            error_log('Error fetching baseline saldo awal: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Posisi saldo kas berjalan s/d tanggal $batasAkhir (inklusif, 'Y-m-d').
     * Satu-satunya sumber kebenaran saldo — dipakai dashboard (getRingkasan)
     * maupun BKU agar angkanya selalu sama:
     * - Bila ada baseline saldo_awal: nominal baseline + penerimaan CAIR
     *   sejak baseline - belanja UP diverifikasi sejak baseline (belanja lama
     *   sebelum baseline dianggap sudah tercermin di baseline).
     * - Bila belum ada: kumulatif murni seluruh histori.
     */
    public function getSaldoPerTanggal(string $batasAkhir): float
    {
        try {
            $baseline = $this->getBaselineSaldoAwal($batasAkhir);
            if ($baseline !== null) {
                $stmtIn = $this->db->prepare("
                    SELECT COALESCE(SUM(nominal), 0)
                    FROM kas_bank
                    WHERE status = 'cair'
                      AND id != :base_id
                      AND COALESCE(tanggal_cair, tanggal) >= :base_tgl
                      AND COALESCE(tanggal_cair, tanggal) <= :batas
                ");
                $stmtIn->execute([
                    ':base_id'  => $baseline['id'],
                    ':base_tgl' => $baseline['tanggal'],
                    ':batas'    => $batasAkhir,
                ]);
                $masuk = (float) $stmtIn->fetchColumn();

                $stmtOut = $this->db->prepare("
                    SELECT COALESCE(SUM(nilai), 0)
                    FROM transaksi
                    WHERE status = 'diverifikasi'
                      AND sumber_dana = 'UP'
                      AND COALESCE(tanggal_lunas_dibayar, DATE(diverifikasi_at), tanggal) >= :base_tgl
                      AND COALESCE(tanggal_lunas_dibayar, DATE(diverifikasi_at), tanggal) <= :batas
                ");
                $stmtOut->execute([':base_tgl' => $baseline['tanggal'], ':batas' => $batasAkhir]);
                $keluar = (float) $stmtOut->fetchColumn();

                return $baseline['nominal'] + $masuk - $keluar;
            }

            $stmtIn = $this->db->prepare("
                SELECT COALESCE(SUM(nominal), 0)
                FROM kas_bank
                WHERE status = 'cair'
                  AND COALESCE(tanggal_cair, tanggal) <= :batas
            ");
            $stmtIn->execute([':batas' => $batasAkhir]);
            $masuk = (float) $stmtIn->fetchColumn();

            $stmtOut = $this->db->prepare("
                SELECT COALESCE(SUM(nilai), 0)
                FROM transaksi
                WHERE status = 'diverifikasi'
                  AND sumber_dana = 'UP'
                  AND COALESCE(tanggal_lunas_dibayar, DATE(diverifikasi_at), tanggal) <= :batas
            ");
            $stmtOut->execute([':batas' => $batasAkhir]);
            $keluar = (float) $stmtOut->fetchColumn();

            return $masuk - $keluar;
        } catch (PDOException $e) {
            error_log('Error calculating saldo per tanggal: ' . $e->getMessage());
            return 0.0;
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

            // 2. Belanja UP diverifikasi periode berjalan yang BELUM di-SPJ-kan
            //    (siap diajukan GU berikutnya; yang sudah tercakup pengajuan
            //    tidak dihitung lagi agar tidak klaim ganda).
            $stmtTrx = $this->db->prepare("
                SELECT COALESCE(SUM(nilai), 0)
                FROM transaksi
                WHERE status = 'diverifikasi'
                  AND sumber_dana = 'UP'
                  AND pengajuan_gu_id IS NULL
                  AND MONTH(COALESCE(tanggal_lunas_dibayar, DATE(diverifikasi_at), tanggal)) = :bulan
                  AND YEAR(COALESCE(tanggal_lunas_dibayar, DATE(diverifikasi_at), tanggal)) = :tahun
            ");
            $stmtTrx->execute([':bulan' => $bulan, ':tahun' => $tahun]);
            $totalPengeluaranDiverifikasi = (float) $stmtTrx->fetchColumn();

            // 3. Saldo berjalan kumulatif s/d akhir periode diminta.
            //    Satu sumber kebenaran dengan BKU (getSaldoPerTanggal).
            $cutoff = sprintf('%04d-%02d-%02d', $tahun, $bulan, (int) date('t', mktime(0, 0, 0, $bulan, 1, $tahun)));

            $baselineInfo    = $this->getBaselineSaldoAwal($cutoff);
            $baselineTanggal = $baselineInfo['tanggal'] ?? null;
            $baselineNominal = $baselineInfo['nominal'] ?? 0.0;
            $saldoKasSaatIni = $this->getSaldoPerTanggal($cutoff);

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
                'cutoff'                         => sprintf('%04d-%02d-01', $tahun, $bulan),
                'baseline_tanggal'               => null,
                'baseline_nominal'               => 0.0,
            ];
        }
    }
}
