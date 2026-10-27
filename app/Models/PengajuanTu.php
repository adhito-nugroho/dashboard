<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;

/**
 * Model untuk modul Tambah Uang (TU).
 *
 * Alur siklus (seluruhnya dikelola admin/bendahara):
 *   1. Admin input pengajuan TU → 'diajukan' (menunggu SP2D)
 *   2. Admin cairkan (SP2D-TU masuk kas) → 'cair' + baris kas_bank jenis='tu'
 *   3. Admin catat pembayaran → transaksi.pengajuan_tu_id diisi (diverifikasi)
 *   4. Admin selesaikan: input setoran sisa → 'selesai' + baris kas_bank jenis='setoran'
 */
class PengajuanTu
{
    /** Tanggal mulai fitur TU aktif di sistem. Pengajuan sebelum tanggal ini tidak ditampilkan. */
    public const TU_MULAI = '2026-10-01';

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ──────────────────────────────────────────────────────────────────────
    // LIST & GET
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Daftar semua pengajuan TU dengan filter opsional (admin view).
     */
    public function getAll(
        ?string $status  = null,
        ?int    $seksiId = null,
        ?int    $tahun   = null,
        ?int    $bulan   = null,
        int     $limit   = 50,
        int     $offset  = 0
    ): array {
        $conds  = [];
        $params = [];

        if ($status)  { $conds[] = 'pt.status = :status';    $params[':status']  = $status;  }
        if ($seksiId) { $conds[] = 'pt.seksi_id = :seksi_id'; $params[':seksi_id'] = $seksiId; }
        if ($tahun)   { $conds[] = 'pt.tahun = :tahun';       $params[':tahun']   = $tahun;   }
        if ($bulan)   { $conds[] = 'pt.bulan = :bulan';       $params[':bulan']   = $bulan;   }

        $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

        $stmt = $this->db->prepare("
            SELECT pt.*,
                   s.nama_seksi, s.kode_seksi,
                   u.username AS created_by_name,
                   ua.username AS disetujui_by_name,
                   (SELECT COALESCE(SUM(t.nilai), 0)
                    FROM transaksi t
                    WHERE t.pengajuan_tu_id = pt.id
                      AND t.status = 'diverifikasi') AS nominal_terpakai
            FROM pengajuan_tu pt
            LEFT JOIN seksi s  ON pt.seksi_id   = s.id
            LEFT JOIN users u  ON pt.created_by = u.id
            LEFT JOIN users ua ON pt.disetujui_by = ua.id
            {$where}
            ORDER BY FIELD(pt.status,'diajukan','disetujui','cair','selesai','ditolak'),
                     pt.tanggal_pengajuan DESC, pt.id DESC
            LIMIT :lim OFFSET :off
        ");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':lim', $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Hitung total pengajuan TU (untuk pagination).
     */
    public function countAll(
        ?string $status  = null,
        ?int    $seksiId = null,
        ?int    $tahun   = null,
        ?int    $bulan   = null
    ): int {
        $conds  = [];
        $params = [];

        if ($status)  { $conds[] = 'status = :status';    $params[':status']  = $status;  }
        if ($seksiId) { $conds[] = 'seksi_id = :seksi_id'; $params[':seksi_id'] = $seksiId; }
        if ($tahun)   { $conds[] = 'tahun = :tahun';       $params[':tahun']   = $tahun;   }
        if ($bulan)   { $conds[] = 'bulan = :bulan';       $params[':bulan']   = $bulan;   }

        $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';
        $stmt  = $this->db->prepare("SELECT COUNT(*) FROM pengajuan_tu {$where}");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Detail satu pengajuan TU + daftar transaksi yang terkait.
     */
    public function getByIdWithTransaksi(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT pt.*,
                   s.nama_seksi, s.kode_seksi,
                   r.kode_rekening, r.nama_rekening,
                   sk.id AS sub_kegiatan_id, sk.kode_sub_kegiatan, sk.nama_sub_kegiatan,
                   k.id AS kegiatan_id, k.kode_kegiatan, k.nama_kegiatan,
                   p.id AS program_id, p.kode_program, p.nama_program,
                   u.username AS created_by_name,
                   ua.username AS disetujui_by_name
            FROM pengajuan_tu pt
            LEFT JOIN seksi s          ON pt.seksi_id    = s.id
            LEFT JOIN rekening r       ON pt.rekening_id = r.id
            LEFT JOIN sub_kegiatan sk  ON r.sub_kegiatan_id = sk.id
            LEFT JOIN kegiatan k       ON sk.kegiatan_id = k.id
            LEFT JOIN program p        ON k.program_id   = p.id
            LEFT JOIN users u          ON pt.created_by  = u.id
            LEFT JOIN users ua         ON pt.disetujui_by = ua.id
            WHERE pt.id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        // Transaksi yang dikaitkan ke TU ini
        $stmtT = $this->db->prepare("
            SELECT t.id, t.tanggal, t.tanggal_lunas_dibayar,
                   t.uraian, t.nilai, t.nomor_bukti, t.status,
                   t.nama_penerima, t.jenis_transaksi,
                   r.kode_rekening, r.nama_rekening,
                   sk.nama_sub_kegiatan
            FROM transaksi t
            INNER JOIN rekening r ON t.rekening_id = r.id
            INNER JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
            WHERE t.pengajuan_tu_id = :id
            ORDER BY COALESCE(t.tanggal_lunas_dibayar, t.tanggal) ASC, t.id ASC
        ");
        $stmtT->execute([':id' => $id]);
        $transaksis = $stmtT->fetchAll(PDO::FETCH_ASSOC);

        $row['transaksis']       = $transaksis;
        $row['nominal_terpakai'] = array_sum(array_column(
            array_filter($transaksis, fn($t) => $t['status'] === 'diverifikasi'),
            'nilai'
        ));

        return $row;
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT pt.*, s.nama_seksi, s.kode_seksi,
                   r.kode_rekening, r.nama_rekening,
                   sk.id AS sub_kegiatan_id, sk.kode_sub_kegiatan, sk.nama_sub_kegiatan,
                   k.id AS kegiatan_id, k.kode_kegiatan, k.nama_kegiatan,
                   p.id AS program_id, p.kode_program, p.nama_program
            FROM pengajuan_tu pt
            LEFT JOIN seksi s        ON pt.seksi_id   = s.id
            LEFT JOIN rekening r     ON pt.rekening_id = r.id
            LEFT JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
            LEFT JOIN kegiatan k     ON sk.kegiatan_id = k.id
            LEFT JOIN program p      ON k.program_id  = p.id
            WHERE pt.id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ──────────────────────────────────────────────────────────────────────
    // CREATE
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Admin membuat pengajuan TU baru.
     */
    public function create(
        string $nomorPengajuan,
        string $tanggalPengajuan,
        int    $seksiId,
        int    $tahun,
        int    $bulan,
        float  $kebutuhan,
        string $keperluan,
        int    $createdBy,
        ?int   $rekeningId = null
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO pengajuan_tu
                (nomor_pengajuan, tanggal_pengajuan, seksi_id, rekening_id, tahun, bulan,
                 kebutuhan, keperluan, status, created_by)
            VALUES
                (:nomor, :tgl, :seksi_id, :rekening_id, :tahun, :bulan,
                 :kebutuhan, :keperluan, 'diajukan', :created_by)
        ");
        $stmt->execute([
            ':nomor'       => $nomorPengajuan,
            ':tgl'         => $tanggalPengajuan,
            ':seksi_id'    => $seksiId,
            ':rekening_id' => $rekeningId,
            ':tahun'       => $tahun,
            ':bulan'       => $bulan,
            ':kebutuhan'   => $kebutuhan,
            ':keperluan'   => $keperluan,
            ':created_by'  => $createdBy,
        ]);
        return (int) $this->db->lastInsertId();
    }

    // ──────────────────────────────────────────────────────────────────────
    // STATUS TRANSITIONS
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Admin mencairkan TU (diajukan → cair).
     * Mencatat baris kas_bank jenis='tu' dan mengisi kas_bank_id + nomor SP2D.
     * $batasSpj = null → default +30 hari dari tanggal_cair.
     *
     * @return int ID kas_bank yang baru dibuat
     */
    public function cairkan(
        int     $id,
        string  $nomorSp2d,
        string  $tanggalCair,
        float   $nominalCair,
        ?string $batasSpj,
        int     $adminId
    ): int {
        $this->db->beginTransaction();
        try {
            $tu = $this->getById($id);
            if (!$tu || !in_array($tu['status'], ['diajukan', 'disetujui'], true)) {
                throw new \RuntimeException('Pengajuan TU tidak ditemukan atau status tidak valid.');
            }

            // Batas SPJ default = +30 hari dari tanggal cair
            if (!$batasSpj) {
                $batasSpj = date('Y-m-d', strtotime($tanggalCair . ' +30 days'));
            }

            // Catat pencairan ke kas_bank
            $stmtKas = $this->db->prepare("
                INSERT INTO kas_bank
                    (tanggal, tahun, bulan, jenis, nomor_bukti, keterangan, nominal,
                     status, tanggal_cair, created_by)
                VALUES
                    (:tgl, :tahun, :bulan, 'tu', :nomor_sp2d,
                     :ket, :nominal, 'cair', :tgl_cair, :by)
            ");
            $bulan = (int) date('n', strtotime($tanggalCair));
            $tahun = (int) date('Y', strtotime($tanggalCair));
            $ket   = 'Pencairan SP2D-TU: ' . ($tu['nomor_pengajuan'] ?? '') . ' - ' . ($tu['keperluan'] ?? '');
            $stmtKas->execute([
                ':tgl'       => $tanggalCair,
                ':tahun'     => $tahun,
                ':bulan'     => $bulan,
                ':nomor_sp2d' => $nomorSp2d,
                ':ket'       => substr($ket, 0, 255),
                ':nominal'   => $nominalCair,
                ':tgl_cair'  => $tanggalCair,
                ':by'        => $adminId,
            ]);
            $kasBankId = (int) $this->db->lastInsertId();

            // Update pengajuan_tu
            $stmtU = $this->db->prepare("
                UPDATE pengajuan_tu
                SET status         = 'cair',
                    nomor_sp2d     = :sp2d,
                    tanggal_cair   = :tgl_cair,
                    nominal_cair   = :nominal,
                    batas_spj      = :batas,
                    kas_bank_id    = :kb_id
                WHERE id = :id AND status IN ('diajukan','disetujui')
            ");
            $stmtU->execute([
                ':sp2d'    => $nomorSp2d,
                ':tgl_cair' => $tanggalCair,
                ':nominal' => $nominalCair,
                ':batas'   => $batasSpj,
                ':kb_id'   => $kasBankId,
                ':id'      => $id,
            ]);

            $this->db->commit();
            return $kasBankId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Admin menyelesaikan siklus TU (cair → selesai).
     * Sisa dihitung dari dana cair dikurangi pembayaran terverifikasi; admin
     * menginput sendiri nominal dan tanggal setoran (boleh lebih kecil dari
     * sisa bila ada koreksi), lalu dicatat sebagai kas_bank jenis='setoran'.
     *
     * @return array ['sisa' => float, 'disetor' => float, 'kas_bank_setoran_id' => int|null]
     */
    public function selesaikan(
        int     $id,
        int     $adminId,
        float   $nominalSetor,
        string  $tanggalSetoran,
        ?string $nomorBuktiSetoran = null,
        ?string $catatanAdmin = null
    ): array {
        $this->db->beginTransaction();
        try {
            $data = $this->getByIdWithTransaksi($id);
            if (!$data || $data['status'] !== 'cair') {
                throw new \RuntimeException('Pengajuan TU tidak dalam status cair.');
            }

            $nominalCair     = (float) ($data['nominal_cair'] ?? 0);
            $nominalTerpakai = (float) $data['nominal_terpakai'];
            $sisa = $nominalCair - $nominalTerpakai;
            if ($sisa < 0) $sisa = 0.0;

            if ($nominalSetor < 0) $nominalSetor = 0.0;
            if ($nominalSetor > $sisa + 0.005) {
                throw new \RuntimeException(sprintf(
                    'Nominal setoran (Rp %s) melebihi sisa dana TU (Rp %s).',
                    number_format($nominalSetor, 0, ',', '.'),
                    number_format($sisa, 0, ',', '.')
                ));
            }
            if (!strtotime($tanggalSetoran)) {
                $tanggalSetoran = date('Y-m-d');
            }

            $kasBankSetoranId = null;

            // Catat setoran ke kas hanya bila nominalnya > 0
            if ($nominalSetor > 0.005) {
                $stmtSetoran = $this->db->prepare("
                    INSERT INTO kas_bank
                        (tanggal, tahun, bulan, jenis, nomor_bukti, keterangan, nominal,
                         status, tanggal_cair, created_by)
                    VALUES
                        (:tgl, :tahun, :bulan, 'setoran',
                         :nomor_bukti, :ket, :nominal, 'cair', :tgl_cair, :by)
                ");
                $bulan = (int) date('n', strtotime($tanggalSetoran));
                $tahun = (int) date('Y', strtotime($tanggalSetoran));
                $noBukti = $nomorBuktiSetoran ?: 'SETORAN-TU-' . $id . '-' . date('Ymd', strtotime($tanggalSetoran));
                $ket = 'Setoran sisa TU: ' . ($data['nomor_pengajuan'] ?? 'ID-' . $id);
                $stmtSetoran->execute([
                    ':tgl'         => $tanggalSetoran,
                    ':tgl_cair'    => $tanggalSetoran,
                    ':tahun'       => $tahun,
                    ':bulan'       => $bulan,
                    ':nomor_bukti' => $noBukti,
                    ':ket'         => $ket,
                    ':nominal'     => $nominalSetor,
                    ':by'          => $adminId,
                ]);
                $kasBankSetoranId = (int) $this->db->lastInsertId();
            }

            // Update pengajuan_tu ke selesai
            $stmtU = $this->db->prepare("
                UPDATE pengajuan_tu
                SET status                = 'selesai',
                    nominal_digunakan     = :terpakai,
                    nominal_disetor       = :disetor,
                    kas_bank_setoran_id   = :kb_setoran_id,
                    catatan_admin         = :catatan
                WHERE id = :id AND status = 'cair'
            ");
            $stmtU->execute([
                ':terpakai'       => $nominalTerpakai,
                ':disetor'        => $nominalSetor,
                ':kb_setoran_id'  => $kasBankSetoranId,
                ':catatan'        => $catatanAdmin,
                ':id'             => $id,
            ]);

            $this->db->commit();
            return [
                'sisa'                => $sisa,
                'disetor'             => $nominalSetor,
                'kas_bank_setoran_id' => $kasBankSetoranId,
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Catat pembayaran dari dana TU (admin).
     *
     * Membuat baris transaksi terverifikasi yang langsung terkait ke TU ini.
     * Nilai pembayaran tidak boleh melebihi sisa dana TU yang belum terpakai.
     *
     * @param array<string,mixed> $data tanggal, rekening_id, uraian, nilai,
     *                                 nomor_bukti, nama_penerima, jenis_transaksi
     * @return int ID transaksi baru
     */
    public function catatPembayaran(int $tuId, array $data, int $adminId): int
    {
        $tu = $this->getById($tuId);
        if (!$tu || $tu['status'] !== 'cair') {
            throw new \RuntimeException('Pembayaran hanya dapat dicatat pada TU berstatus cair.');
        }

        $rekeningId = (int) ($data['rekening_id'] ?? 0);
        $stmtRek = $this->db->prepare("
            SELECT sk.seksi_id
            FROM rekening r
            INNER JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
            WHERE r.id = :rid
            LIMIT 1
        ");
        $stmtRek->execute([':rid' => $rekeningId]);
        $rekeningSeksiId = $stmtRek->fetchColumn();
        if ($rekeningSeksiId === false || $rekeningSeksiId === null || (int) $rekeningSeksiId === 0) {
            throw new \RuntimeException('Rekening pembayaran tidak valid atau belum memiliki seksi.');
        }

        $uraian = trim((string) ($data['uraian'] ?? ''));
        if ($uraian === '') {
            throw new \RuntimeException('Uraian pembayaran wajib diisi.');
        }

        $nilai = (float) ($data['nilai'] ?? 0);
        if ($nilai <= 0) {
            throw new \RuntimeException('Nilai pembayaran harus lebih dari 0.');
        }

        $tanggal = (string) ($data['tanggal'] ?? '');
        if (!strtotime($tanggal)) {
            throw new \RuntimeException('Tanggal pembayaran tidak valid.');
        }

        $stmtPakai = $this->db->prepare("
            SELECT COALESCE(SUM(nilai), 0)
            FROM transaksi
            WHERE pengajuan_tu_id = :tu AND status = 'diverifikasi'
        ");
        $stmtPakai->execute([':tu' => $tuId]);
        $terpakai = (float) $stmtPakai->fetchColumn();
        $sisa = (float) $tu['nominal_cair'] - $terpakai;
        if ($nilai > $sisa + 0.005) {
            throw new \RuntimeException(sprintf(
                'Nilai pembayaran (Rp %s) melebihi sisa dana TU (Rp %s).',
                number_format($nilai, 0, ',', '.'),
                number_format(max(0, $sisa), 0, ',', '.')
            ));
        }

        $jenisTransaksi = (string) ($data['jenis_transaksi'] ?? 'belanja');
        if (!in_array($jenisTransaksi, ['perjalanan_dinas', 'belanja', 'honorarium', 'lainnya'], true)) {
            $jenisTransaksi = 'belanja';
        }

        $stmt = $this->db->prepare("
            INSERT INTO transaksi
                (tanggal, tanggal_lunas_dibayar, seksi_id, rekening_id, uraian,
                 nama_penerima, jenis_transaksi, nilai, nomor_bukti, status,
                 sumber_dana, input_by, diverifikasi_by, diverifikasi_at, pengajuan_tu_id)
            VALUES
                (:tgl, :tgl_lunas, :seksi_id, :rekening_id, :uraian,
                 :penerima, :jenis, :nilai, :nomor_bukti, 'diverifikasi',
                 'UP', :input_by, :verif_by, NOW(), :tu_id)
        ");
        $stmt->execute([
            ':tgl'         => $tanggal,
            ':tgl_lunas'   => $tanggal,
            ':seksi_id'    => (int) $rekeningSeksiId,
            ':rekening_id' => $rekeningId,
            ':uraian'      => $uraian,
            ':penerima'    => trim((string) ($data['nama_penerima'] ?? '')) ?: null,
            ':jenis'       => $jenisTransaksi,
            ':nilai'       => $nilai,
            ':nomor_bukti' => trim((string) ($data['nomor_bukti'] ?? '')) ?: null,
            ':input_by'    => $adminId,
            ':verif_by'    => $adminId,
            ':tu_id'       => $tuId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Hapus pembayaran yang terkait ke TU ini (hanya saat TU masih cair).
     */
    public function hapusPembayaran(int $tuId, int $transaksiId): bool
    {
        $tu = $this->getById($tuId);
        if (!$tu || $tu['status'] !== 'cair') {
            return false;
        }
        $stmt = $this->db->prepare("
            DELETE FROM transaksi
            WHERE id = :tid AND pengajuan_tu_id = :tu_id AND pengajuan_gu_id IS NULL
        ");
        $stmt->execute([':tid' => $transaksiId, ':tu_id' => $tuId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Update keperluan/kebutuhan (hanya saat status diajukan).
     */
    public function update(int $id, float $kebutuhan, string $keperluan, string $tanggalPengajuan, ?int $rekeningId = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE pengajuan_tu
            SET kebutuhan          = :kebutuhan,
                keperluan          = :keperluan,
                tanggal_pengajuan  = :tgl,
                rekening_id        = :rekening_id
            WHERE id = :id AND status = 'diajukan'
        ");
        $stmt->execute([':kebutuhan' => $kebutuhan, ':keperluan' => $keperluan, ':tgl' => $tanggalPengajuan, ':rekening_id' => $rekeningId, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Hapus pengajuan TU (hanya saat status diajukan).
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM pengajuan_tu WHERE id = :id AND status = 'diajukan'");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Generate nomor pengajuan TU otomatis.
     * Format: TU-{SEKSI_KODE}/{urut}/{BULAN_ROMAWI}/{TAHUN}
     */
    public function generateNomor(int $seksiId, int $bulan, int $tahun): string
    {
        $romawiMap = [1=>'I',2=>'II',3=>'III',4=>'IV',5=>'V',6=>'VI',
                      7=>'VII',8=>'VIII',9=>'IX',10=>'X',11=>'XI',12=>'XII'];
        $romawi = $romawiMap[$bulan] ?? 'I';

        // Hitung berapa TU sudah diajukan oleh seksi ini di tahun ini
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM pengajuan_tu WHERE seksi_id = :sid AND tahun = :thn
        ");
        $stmt->execute([':sid' => $seksiId, ':thn' => $tahun]);
        $urut = (int) $stmt->fetchColumn() + 1;

        // Ambil kode seksi
        $stmtS = $this->db->prepare("SELECT kode_seksi FROM seksi WHERE id = :id LIMIT 1");
        $stmtS->execute([':id' => $seksiId]);
        $kode = $stmtS->fetchColumn() ?: 'SKS';

        return sprintf('TU-%s/%03d/%s/%d', strtoupper($kode), $urut, $romawi, $tahun);
    }

    /**
     * Statistik ringkas untuk dashboard (badge sidebar dan kartu KPI).
     */
    public function getStats(?int $tahun = null): array
    {
        $tahun = $tahun ?? (int) date('Y');
        // MySQL tidak support FILTER() — pakai SUM+CASE
        $stmt = $this->db->prepare("
            SELECT
                SUM(CASE WHEN status = 'diajukan'  THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'disetujui' THEN 1 ELSE 0 END) AS disetujui,
                SUM(CASE WHEN status = 'cair'      THEN 1 ELSE 0 END) AS cair,
                SUM(CASE WHEN status = 'selesai'   THEN 1 ELSE 0 END) AS selesai,
                SUM(CASE WHEN status = 'cair' AND batas_spj IS NOT NULL AND batas_spj < :today THEN 1 ELSE 0 END) AS jatuh_tempo,
                SUM(CASE WHEN status IN ('cair','selesai') THEN COALESCE(nominal_cair,0) ELSE 0 END) AS total_cair,
                SUM(CASE WHEN status = 'selesai' THEN COALESCE(nominal_disetor,0) ELSE 0 END) AS total_disetor
            FROM pengajuan_tu WHERE tahun = :tahun
        ");
        $stmt->execute([':today' => date('Y-m-d'), ':tahun' => $tahun]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'pending'      => (int)   ($row['pending']      ?? 0),
            'disetujui'    => (int)   ($row['disetujui']    ?? 0),
            'cair'         => (int)   ($row['cair']         ?? 0),
            'selesai'      => (int)   ($row['selesai']      ?? 0),
            'jatuh_tempo'  => (int)   ($row['jatuh_tempo']  ?? 0),
            'total_cair'   => (float) ($row['total_cair']   ?? 0),
            'total_disetor'=> (float) ($row['total_disetor']?? 0),
        ];
    }
}
