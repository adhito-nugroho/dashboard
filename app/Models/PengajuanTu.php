<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;

/**
 * Model untuk modul Tambah Uang (TU).
 *
 * Alur siklus:
 *   1. Seksi ajukan TU → row berstatus 'diajukan'
 *   2. Admin setujui  → 'disetujui'
 *   3. Admin cairkan (SP2D-TU masuk kas) → 'cair' + baris kas_bank jenis='tu'
 *   4. Seksi pakai untuk belanja → transaksi.pengajuan_tu_id diisi
 *   5. Batas SPJ → admin selesaikan: hitung sisa, catat setoran → 'selesai'
 *   5b. Atau admin tolak saat masih 'diajukan' → 'ditolak'
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
     * Pengajuan TU milik seksi tertentu (dipakai view seksi).
     */
    public function getBySeksi(int $seksiId, ?string $status = null): array
    {
        $conds  = ['pt.seksi_id = :seksi_id'];
        $params = [':seksi_id' => $seksiId];
        if ($status) { $conds[] = 'pt.status = :status'; $params[':status'] = $status; }

        $stmt = $this->db->prepare("
            SELECT pt.*,
                   (SELECT COALESCE(SUM(t.nilai), 0)
                    FROM transaksi t
                    WHERE t.pengajuan_tu_id = pt.id
                      AND t.status = 'diverifikasi') AS nominal_terpakai
            FROM pengajuan_tu pt
            WHERE " . implode(' AND ', $conds) . "
            ORDER BY pt.tanggal_pengajuan DESC, pt.id DESC
        ");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Detail satu pengajuan TU + daftar transaksi yang terkait.
     */
    public function getByIdWithTransaksi(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT pt.*,
                   s.nama_seksi, s.kode_seksi,
                   u.username AS created_by_name,
                   ua.username AS disetujui_by_name
            FROM pengajuan_tu pt
            LEFT JOIN seksi s  ON pt.seksi_id   = s.id
            LEFT JOIN users u  ON pt.created_by = u.id
            LEFT JOIN users ua ON pt.disetujui_by = ua.id
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

    /**
     * Pengajuan TU yang sedang cair (status='cair') untuk seksi tertentu —
     * dipakai saat input transaksi agar seksi bisa memilih TU yang aktif.
     */
    public function getAktifBySeksi(int $seksiId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, nomor_pengajuan, tanggal_cair, nominal_cair, batas_spj, keperluan,
                   (SELECT COALESCE(SUM(nilai), 0) FROM transaksi
                    WHERE pengajuan_tu_id = pt.id AND status = 'diverifikasi') AS nominal_terpakai
            FROM pengajuan_tu pt
            WHERE seksi_id = :sid AND status = 'cair'
            ORDER BY tanggal_cair DESC
        ");
        $stmt->execute([':sid' => $seksiId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Hitung jumlah TU aktif (cair) yang batas SPJ-nya sudah lewat.
     */
    public function countMendekatiJatuhTempo(string $today): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM pengajuan_tu
            WHERE status = 'cair' AND batas_spj IS NOT NULL AND batas_spj <= :today
        ");
        $stmt->execute([':today' => $today]);
        return (int) $stmt->fetchColumn();
    }

    // ──────────────────────────────────────────────────────────────────────
    // CREATE
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Seksi mengajukan TU baru.
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
     * Admin menyetujui pengajuan TU (diajukan → disetujui).
     */
    public function setujui(int $id, int $adminId, ?string $catatan = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE pengajuan_tu
            SET status = 'disetujui',
                disetujui_by = :admin_id,
                disetujui_at = NOW(),
                catatan_admin = :catatan
            WHERE id = :id AND status = 'diajukan'
        ");
        $stmt->execute([':admin_id' => $adminId, ':catatan' => $catatan, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Admin menolak pengajuan TU (diajukan → ditolak).
     */
    public function tolak(int $id, int $adminId, string $catatan): bool
    {
        $stmt = $this->db->prepare("
            UPDATE pengajuan_tu
            SET status = 'ditolak',
                disetujui_by = :admin_id,
                disetujui_at = NOW(),
                catatan_admin = :catatan
            WHERE id = :id AND status IN ('diajukan')
        ");
        $stmt->execute([':admin_id' => $adminId, ':catatan' => $catatan, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Admin mencairkan TU (disetujui → cair).
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
            if (!$tu || $tu['status'] !== 'disetujui') {
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
                WHERE id = :id AND status = 'disetujui'
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
     * Menghitung sisa, jika ada sisa > 0 mencatat kas_bank jenis='setoran'.
     *
     * @return array ['sisa' => float, 'kas_bank_setoran_id' => int|null]
     */
    public function selesaikan(int $id, int $adminId, ?string $catatanAdmin = null): array
    {
        $this->db->beginTransaction();
        try {
            $data = $this->getByIdWithTransaksi($id);
            if (!$data || $data['status'] !== 'cair') {
                throw new \RuntimeException('Pengajuan TU tidak dalam status cair.');
            }

            $nominalCair   = (float) ($data['nominal_cair'] ?? 0);
            $nominalTerpakai = (float) $data['nominal_terpakai'];
            $sisa = $nominalCair - $nominalTerpakai;
            if ($sisa < 0) $sisa = 0.0;

            $kasBankSetoranId = null;

            // Jika ada sisa, catat setoran ke kas
            if ($sisa > 0.005) {
                $tanggalSetoran = date('Y-m-d');
                $stmtSetoran = $this->db->prepare("
                    INSERT INTO kas_bank
                        (tanggal, tahun, bulan, jenis, nomor_bukti, keterangan, nominal,
                         status, tanggal_cair, created_by)
                    VALUES
                        (:tgl, :tahun, :bulan, 'setoran',
                         :nomor_bukti, :ket, :nominal, 'cair', :tgl, :by)
                ");
                $bulan = (int) date('n');
                $tahun = (int) date('Y');
                $noBukti = 'SETORAN-TU-' . $id . '-' . date('Ymd');
                $ket = 'Setoran sisa TU: ' . ($data['nomor_pengajuan'] ?? 'ID-' . $id);
                $stmtSetoran->execute([
                    ':tgl'         => $tanggalSetoran,
                    ':tahun'       => $tahun,
                    ':bulan'       => $bulan,
                    ':nomor_bukti' => $noBukti,
                    ':ket'         => $ket,
                    ':nominal'     => $sisa,
                    ':by'          => $adminId,
                ]);
                $kasBankSetoranId = (int) $this->db->lastInsertId();
            }

            // Update pengajuan_tu ke selesai
            $stmtU = $this->db->prepare("
                UPDATE pengajuan_tu
                SET status                = 'selesai',
                    nominal_digunakan     = :terpakai,
                    nominal_disetor       = :sisa,
                    kas_bank_setoran_id   = :kb_setoran_id,
                    catatan_admin         = :catatan
                WHERE id = :id AND status = 'cair'
            ");
            $stmtU->execute([
                ':terpakai'       => $nominalTerpakai,
                ':sisa'           => $sisa,
                ':kb_setoran_id'  => $kasBankSetoranId,
                ':catatan'        => $catatanAdmin,
                ':id'             => $id,
            ]);

            $this->db->commit();
            return ['sisa' => $sisa, 'kas_bank_setoran_id' => $kasBankSetoranId];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
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
     * Kaitkan transaksi ke pengajuan TU tertentu.
     * Hanya bisa jika TU berstatus 'cair' dan transaksi milik seksi yang sama.
     */
    public function kaitkanTransaksi(int $tuId, int $transaksiId, \PDO $db): bool
    {
        // Validasi: TU harus cair dan milik seksi yang sama dengan transaksi
        $stmtCek = $db->prepare("
            SELECT pt.seksi_id FROM pengajuan_tu pt WHERE pt.id = :tu_id AND pt.status = 'cair' LIMIT 1
        ");
        $stmtCek->execute([':tu_id' => $tuId]);
        $tu = $stmtCek->fetch(PDO::FETCH_ASSOC);
        if (!$tu) return false;

        $stmtT = $db->prepare("
            UPDATE transaksi SET pengajuan_tu_id = :tu_id
            WHERE id = :t_id AND seksi_id = :seksi_id AND pengajuan_tu_id IS NULL
        ");
        $stmtT->execute([':tu_id' => $tuId, ':t_id' => $transaksiId, ':seksi_id' => $tu['seksi_id']]);
        return $stmtT->rowCount() > 0;
    }

    /**
     * Lepas kaitan transaksi dari TU (set pengajuan_tu_id = NULL).
     */
    public function lepasTransaksi(int $tuId, int $transaksiId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE transaksi SET pengajuan_tu_id = NULL
            WHERE id = :t_id AND pengajuan_tu_id = :tu_id
        ");
        $stmt->execute([':t_id' => $transaksiId, ':tu_id' => $tuId]);
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
