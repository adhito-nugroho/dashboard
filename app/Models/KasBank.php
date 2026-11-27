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
     * Ambil semua transaksi mutasi kas/bank pada bulan & tahun tertentu.
     * Basis bulan = tanggal efektif kas (COALESCE(tanggal_cair, tanggal)),
     * sama seperti BKU & ringkasan saldo, bukan bulan input pengajuan.
     */
    public function getByPeriode(int $bulan, int $tahun): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT k.*, u.username AS created_by_name
                FROM kas_bank k
                LEFT JOIN users u ON k.created_by = u.id
                WHERE MONTH(COALESCE(k.tanggal_cair, k.tanggal)) = :bulan
                  AND YEAR(COALESCE(k.tanggal_cair, k.tanggal)) = :tahun
                ORDER BY COALESCE(k.tanggal_cair, k.tanggal) ASC, k.id ASC
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
     * - Penerimaan = baris CAIR dengan arah 'masuk' (pencairan GU/UP/TU).
     * - Pengeluaran kas = baris CAIR dengan arah 'keluar' (mis. setoran
     *   sisa TU kembali ke Kasda) + belanja UP diverifikasi.
     * - Bila ada baseline saldo_awal: nominal baseline + masuk - keluar sejak
     *   baseline (belanja lama sebelum baseline dianggap sudah tercermin).
     * - Bila belum ada: kumulatif murni seluruh histori.
     */
    public function getSaldoPerTanggal(string $batasAkhir): float
    {
        try {
            $baseline = $this->getBaselineSaldoAwal($batasAkhir);
            $sejakSql = $baseline !== null
                ? 'AND COALESCE(tanggal_cair, tanggal) >= :base_tgl'
                : '';
            $sejakTrxSql = $baseline !== null
                ? 'AND COALESCE(tanggal_lunas_dibayar, DATE(diverifikasi_at), tanggal) >= :base_tgl'
                : '';
            $params = [':batas' => $batasAkhir];
            if ($baseline !== null) {
                $params[':base_tgl'] = $baseline['tanggal'];
            }

            $stmtIn = $this->db->prepare("
                SELECT COALESCE(SUM(nominal), 0)
                FROM kas_bank
                WHERE status = 'cair'
                  AND arah   = 'masuk'
                  {$sejakSql}
                  " . ($baseline !== null ? 'AND id != :base_id' : '') . "
                  AND COALESCE(tanggal_cair, tanggal) <= :batas
            ");
            $stmtIn->execute($baseline !== null
                ? $params + [':base_id' => $baseline['id']]
                : $params);
            $masuk = (float) $stmtIn->fetchColumn();

            // Setoran/pengembalian ke Kasda mengurangi kas — dulu ikut terjumlah
            // sebagai penerimaan sehingga saldo membengkak 2x nilainya.
            $stmtKasKeluar = $this->db->prepare("
                SELECT COALESCE(SUM(nominal), 0)
                FROM kas_bank
                WHERE status = 'cair'
                  AND arah   = 'keluar'
                  {$sejakSql}
                  AND COALESCE(tanggal_cair, tanggal) <= :batas
            ");
            $stmtKasKeluar->execute($params);
            $keluarKas = (float) $stmtKasKeluar->fetchColumn();

            $stmtOut = $this->db->prepare("
                SELECT COALESCE(SUM(nilai), 0)
                FROM transaksi
                WHERE status = 'diverifikasi'
                  AND sumber_dana = 'UP'
                  {$sejakTrxSql}
                  AND COALESCE(tanggal_lunas_dibayar, DATE(diverifikasi_at), tanggal) <= :batas
            ");
            $stmtOut->execute($params);
            $keluar = (float) $stmtOut->fetchColumn();

            return ($baseline !== null ? $baseline['nominal'] : 0.0) + $masuk - $keluarKas - $keluar;
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
     * Tambah mutasi penerimaan kas/bank baru.
     *
     * @param string $arah      'masuk' (menambah kas) atau 'keluar' (mengurangi kas)
     * @param string $jenisDana 'UP_GU' (operasional, kena plafon) atau 'TU'
     */
    public function create(
        string $tanggal,
        string $jenis,
        ?string $nomorBukti,
        string $keterangan,
        float $nominal,
        int $createdBy,
        string $status = 'cair',
        ?string $tanggalCair = null,
        string $arah = 'masuk',
        string $jenisDana = 'UP_GU'
    ): int {
        try {
            $time = strtotime($tanggal) ?: time();
            $bulan = (int) date('m', $time);
            $tahun = (int) date('Y', $time);

            if ($status === 'cair' && empty($tanggalCair)) {
                $tanggalCair = $tanggal;
            }

            $arah      = in_array($arah, ['masuk', 'keluar'], true) ? $arah : 'masuk';
            $jenisDana = in_array($jenisDana, ['UP_GU', 'TU'], true) ? $jenisDana : 'UP_GU';

            // Dana operasional tidak boleh melebihi plafon UP/GU — baik saat
            // dicatat langsung cair maupun saat diajukan (akan cair nanti,
            // dicek juga di tandaiCair()).
            if ($jenisDana === 'UP_GU' && $arah === 'masuk') {
                $this->assertPlafonUpGu($nominal, (string) ($tanggalCair ?? $tanggal));
            }

            $stmt = $this->db->prepare("
                INSERT INTO kas_bank (tanggal, tanggal_cair, tahun, bulan, jenis, arah, jenis_dana, status, nomor_bukti, keterangan, nominal, created_by)
                VALUES (:tanggal, :tanggal_cair, :tahun, :bulan, :jenis, :arah, :jenis_dana, :status, :nomor_bukti, :keterangan, :nominal, :created_by)
            ");
            $stmt->execute([
                ':tanggal'      => $tanggal,
                ':tanggal_cair' => $tanggalCair,
                ':tahun'        => $tahun,
                ':bulan'        => $bulan,
                ':jenis'        => $jenis,
                ':arah'         => $arah,
                ':jenis_dana'   => $jenisDana,
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
     * Dana UP/GU tidak boleh melebihi plafon — kelebihannya wajib dicatat
     * sebagai TU (Tambah Uang). Dilempar sebagai exception agar input
     * ditolak, bukan sekadar diperingatkan.
     *
     * Dipanggil dari seluruh jalur yang bisa MENAMBAH kas UP/GU: create()
     * saat sudah cair, tandaiCair(), dan update(). Tanpa itu aturan ini bisa
     * dilewati lewat alur normal GU (diajukan dulu, cair belakangan).
     *
     * @param int|null $kecualiId baris yang sedang diedit — kontribusinya
     *                            dikeluarkan dulu agar tidak dihitung ganda
     * @throws \RuntimeException bila penerimaan baru melampaui plafon
     */
    private function assertPlafonUpGu(
        float $nominalBaru,
        string $tanggal,
        float $plafond = self::DEFAULT_PLAFOND_UP,
        ?int $kecualiId = null
    ): void {
        $time  = strtotime($tanggal) ?: time();
        $bulan = (int) date('m', $time);
        $tahun = (int) date('Y', $time);

        $ringkasan = $this->getRingkasan($bulan, $tahun, $plafond);
        $saldoGu   = max(0.0, (float) $ringkasan['dana_gu_di_kas']);

        // GU yang masih menunggu cair belum masuk saldo, tapi sudah komitmen:
        // dua pengajuan terpisah bisa lolos sendiri-sendiri lalu bersama-sama
        // melewati plafon kalau tidak ikut dihitung.
        $stmtPending = $this->db->prepare("
            SELECT COALESCE(SUM(nominal), 0)
            FROM kas_bank
            WHERE status = 'menunggu_cair'
              AND arah = 'masuk'
              AND jenis_dana = 'UP_GU'
              AND COALESCE(tanggal_cair, tanggal) <= :batas
        ");
        $stmtPending->execute([':batas' => (string) $tanggal]);
        $komitmen = (float) $stmtPending->fetchColumn();

        // Baris yang diedit/dicairkan sudah ikut terhitung di salah satu
        // keranjang — keluarkan dulu agar tidak dihitung ganda.
        if ($kecualiId !== null) {
            $lama = $this->getById($kecualiId);
            if ($lama
                && ($lama['arah'] ?? 'masuk') === 'masuk'
                && ($lama['jenis_dana'] ?? 'UP_GU') === 'UP_GU'
            ) {
                if (($lama['status'] ?? '') === 'cair') {
                    $saldoGu = max(0.0, $saldoGu - (float) $lama['nominal']);
                } elseif (($lama['status'] ?? '') === 'menunggu_cair') {
                    $komitmen = max(0.0, $komitmen - (float) $lama['nominal']);
                }
            }
        }

        $setelah = $saldoGu + $komitmen + $nominalBaru;
        if ($setelah <= $plafond + 0.01) {
            return;
        }

        $kelebihan = $setelah - $plafond;
        throw new \RuntimeException(sprintf(
            'Penerimaan Rp %s membuat dana UP/GU menjadi Rp %s, melebihi plafon Rp %s. '
            . 'Catat kelebihan Rp %s sebagai TU (Tambah Uang), bukan GU.',
            number_format($nominalBaru, 0, ',', '.'),
            number_format($setelah, 0, ',', '.'),
            number_format($plafond, 0, ',', '.'),
            number_format($kelebihan, 0, ',', '.')
        ));
    }

    /**
     * Update mutasi kas/bank.
     */
    public function update(
        int $id,
        string $tanggal,
        string $jenis,
        ?string $nomorBukti,
        string $keterangan,
        float $nominal,
        string $status = 'cair',
        ?string $tanggalCair = null,
        string $arah = 'masuk',
        string $jenisDana = 'UP_GU'
    ): bool {
        try {
            $time = strtotime($tanggal) ?: time();
            $bulan = (int) date('m', $time);
            $tahun = (int) date('Y', $time);

            if ($status === 'cair' && empty($tanggalCair)) {
                $tanggalCair = $tanggal;
            }

            $arah      = in_array($arah, ['masuk', 'keluar'], true) ? $arah : 'masuk';
            $jenisDana = in_array($jenisDana, ['UP_GU', 'TU'], true) ? $jenisDana : 'UP_GU';

            // Edit bisa menaikkan nominal dana operasional melewati plafon.
            if ($jenisDana === 'UP_GU' && $arah === 'masuk' && $status === 'cair') {
                $this->assertPlafonUpGu($nominal, (string) $tanggalCair, self::DEFAULT_PLAFOND_UP, $id);
            }

            $stmt = $this->db->prepare("
                UPDATE kas_bank
                SET tanggal = :tanggal,
                    tanggal_cair = :tanggal_cair,
                    tahun = :tahun,
                    bulan = :bulan,
                    jenis = :jenis,
                    arah = :arah,
                    jenis_dana = :jenis_dana,
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
                ':arah'         => $arah,
                ':jenis_dana'   => $jenisDana,
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
     * Tandai mutasi kas (GU) sebagai sudah cair.
     *
     * Tidak ada pemeriksaan plafon di sini: dana yang menunggu cair sudah
     * dihitung sebagai komitmen UP/GU saat dicatat, jadi mencairkannya hanya
     * memindahkan angka dari komitmen ke saldo — totalnya tidak berubah.
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
     * - Komposisi saldo: dana_tu_di_kas = pencairan TU - belanja dari TU -
     *   setoran sisa TU; dana_gu_di_kas = saldo kas - dana TU (dana
     *   operasional UP/GU). Keduanya kumulatif s/d akhir periode.
     * - Rincian per-bulan (saldo_awal, pencairan_up/gu, total_penerimaan_cair,
     *   total_pengeluaran_diverifikasi, belanja_siap_gu) tetap dipertahankan
     *   untuk tabel & badge periode berjalan.
     */
    public function getRingkasan(int $bulan, int $tahun, float $plafondUp = self::DEFAULT_PLAFOND_UP): array
    {
        try {
            // 1. Rincian penerimaan CAIR periode berjalan (untuk tabel & badge).
            //    Basis bulan = tanggal efektif kas (COALESCE(tanggal_cair, tanggal)),
            //    konsisten dengan getByPeriode, BKU, dan sumber saldo dashboard.
            $stmtCair = $this->db->prepare("
                SELECT jenis, SUM(nominal) as total
                FROM kas_bank
                WHERE MONTH(COALESCE(tanggal_cair, tanggal)) = :bulan
                  AND YEAR(COALESCE(tanggal_cair, tanggal)) = :tahun
                  AND status = 'cair'
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
            //    tidak dihitung lagi agar tidak klaim ganda). Belanja yang
            //    dibayar dari dana TU dikecualikan — TU diselesaikan lewat
            //    SPJ TU/setoran sisa, bukan diajukan sebagai GU.
            $stmtTrx = $this->db->prepare("
                SELECT COALESCE(SUM(nilai), 0)
                FROM transaksi
                WHERE status = 'diverifikasi'
                  AND sumber_dana = 'UP'
                  AND pengajuan_gu_id IS NULL
                  AND pengajuan_tu_id IS NULL
                  AND MONTH(COALESCE(tanggal_lunas_dibayar, DATE(diverifikasi_at), tanggal)) = :bulan
                  AND YEAR(COALESCE(tanggal_lunas_dibayar, DATE(diverifikasi_at), tanggal)) = :tahun
            ");
            $stmtTrx->execute([':bulan' => $bulan, ':tahun' => $tahun]);
            $totalPengeluaranDiverifikasi = (float) $stmtTrx->fetchColumn();

            // 2b. Rincian seluruh belanja UP periode ini untuk rekonsiliasi kas:
            //     total kas keluar = siap GU + sudah di-SPJ GU + belanja dana TU.
            $stmtKeluar = $this->db->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN pengajuan_gu_id IS NOT NULL THEN nilai ELSE 0 END), 0) AS sudah_spj_gu,
                    COALESCE(SUM(CASE WHEN pengajuan_gu_id IS NULL AND pengajuan_tu_id IS NOT NULL THEN nilai ELSE 0 END), 0) AS dana_tu,
                    COALESCE(SUM(nilai), 0) AS total_kas
                FROM transaksi
                WHERE status = 'diverifikasi'
                  AND sumber_dana = 'UP'
                  AND MONTH(COALESCE(tanggal_lunas_dibayar, DATE(diverifikasi_at), tanggal)) = :bulan
                  AND YEAR(COALESCE(tanggal_lunas_dibayar, DATE(diverifikasi_at), tanggal)) = :tahun
            ");
            $stmtKeluar->execute([':bulan' => $bulan, ':tahun' => $tahun]);
            $keluarRows = $stmtKeluar->fetch(PDO::FETCH_ASSOC) ?: [];
            $pengeluaranSudahSpj = (float) ($keluarRows['sudah_spj_gu'] ?? 0);
            $pengeluaranDanaTu   = (float) ($keluarRows['dana_tu'] ?? 0);
            $totalPengeluaranKas = (float) ($keluarRows['total_kas'] ?? 0);

            // 3. Saldo berjalan kumulatif s/d akhir periode diminta.
            //    Satu sumber kebenaran dengan BKU (getSaldoPerTanggal).
            $cutoff = sprintf('%04d-%02d-%02d', $tahun, $bulan, (int) date('t', mktime(0, 0, 0, $bulan, 1, $tahun)));

            $baselineInfo    = $this->getBaselineSaldoAwal($cutoff);
            $baselineTanggal = $baselineInfo['tanggal'] ?? null;
            $baselineNominal = $baselineInfo['nominal'] ?? 0.0;
            $saldoKasSaatIni = $this->getSaldoPerTanggal($cutoff);

            // 4. GU menunggu cair KUMULATIF s/d cutoff (bukan hanya bulan ini),
            //    agar pengajuan SPJ bulan lalu tetap tampil di bulan berjalan.
            //    Hanya dana operasional (UP_GU): GU TU bukan potensi GU.
            $stmtPending = $this->db->prepare("
                SELECT COALESCE(SUM(nominal), 0)
                FROM kas_bank
                WHERE status = 'menunggu_cair'
                  AND arah = 'masuk'
                  AND jenis_dana = 'UP_GU'
                  AND tanggal <= :cutoff
            ");
            $stmtPending->execute([':cutoff' => $cutoff]);
            $guMenungguCair = (float) $stmtPending->fetchColumn();

            // 5. Proyeksi Saldo Kas setelah GU yang menunggu cair masuk
            $proyeksiKasSetelahCair = $saldoKasSaatIni + $guMenungguCair;

            // 6. Belanja bulan ini yang siap di-SPJ-kan untuk pengajuan GU berikutnya
            $belanjaSiapGu = $totalPengeluaranDiverifikasi;

            // 7. Komposisi saldo kas s/d cutoff:
            //    - Uang TU = pencairan TU - belanja yang dibayar dari TU - setoran sisa TU.
            //      Dana ini masih "dipegang" dan wajib di-SPJ-kan/disetor.
            //    - Uang GU = sisa saldo kas di luar dana TU (dana operasional UP/GU).
            $stmtTuMasuk = $this->db->prepare("
                SELECT COALESCE(SUM(nominal), 0)
                FROM kas_bank
                WHERE jenis_dana = 'TU' AND arah = 'masuk' AND status = 'cair'
                  AND COALESCE(tanggal_cair, tanggal) <= :cutoff
            ");
            $stmtTuMasuk->execute([':cutoff' => $cutoff]);
            $tuMasuk = (float) $stmtTuMasuk->fetchColumn();

            $stmtTuKeluar = $this->db->prepare("
                SELECT COALESCE(SUM(t.nilai), 0)
                FROM transaksi t
                WHERE t.status = 'diverifikasi'
                  AND t.pengajuan_tu_id IS NOT NULL
                  AND COALESCE(t.tanggal_lunas_dibayar, DATE(t.diverifikasi_at), t.tanggal) <= :cutoff
            ");
            $stmtTuKeluar->execute([':cutoff' => $cutoff]);
            $tuKeluar = (float) $stmtTuKeluar->fetchColumn();

            $stmtTuSetor = $this->db->prepare("
                SELECT COALESCE(SUM(nominal), 0)
                FROM kas_bank
                WHERE jenis_dana = 'TU' AND arah = 'keluar' AND status = 'cair'
                  AND COALESCE(tanggal_cair, tanggal) <= :cutoff
            ");
            $stmtTuSetor->execute([':cutoff' => $cutoff]);
            $tuSetor = (float) $stmtTuSetor->fetchColumn();

            $danaTuDiKas = max(0.0, $tuMasuk - $tuKeluar - $tuSetor);
            $danaGuDiKas = $saldoKasSaatIni - $danaTuDiKas;

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
                'pengeluaran_sudah_spj_gu'       => $pengeluaranSudahSpj,
                'pengeluaran_dana_tu'            => $pengeluaranDanaTu,
                'total_pengeluaran_kas'          => $totalPengeluaranKas,
                'proyeksi_kas_setelah_cair'      => $proyeksiKasSetelahCair,
                'dana_tu_di_kas'                 => $danaTuDiKas,
                'dana_gu_di_kas'                 => $danaGuDiKas,
                // Plafon UP menampung dana yang sudah di kas DAN GU yang sudah
                // diajukan tapi belum cair — keduanya memakai jatah yang sama.
                'terpakai_plafon_up'             => $danaGuDiKas + $guMenungguCair,
                'sisa_plafon_up'                 => $plafondUp - $danaGuDiKas - $guMenungguCair,
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
                'pengeluaran_sudah_spj_gu'       => 0,
                'pengeluaran_dana_tu'            => 0,
                'total_pengeluaran_kas'          => 0,
                'proyeksi_kas_setelah_cair'      => 0,
                'dana_tu_di_kas'                 => 0,
                'dana_gu_di_kas'                 => 0,
                'terpakai_plafon_up'             => 0,
                'sisa_plafon_up'                 => $plafondUp,
                'cutoff'                         => sprintf('%04d-%02d-01', $tahun, $bulan),
                'baseline_tanggal'               => null,
                'baseline_nominal'               => 0.0,
            ];
        }
    }
}
