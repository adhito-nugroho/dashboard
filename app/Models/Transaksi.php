<?php

namespace App\Models;

use PDO;
use PDOException;

class Transaksi
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get transactions with flexible filters (bulan, tahun, kegiatan_id, sub_kegiatan_id)
     *
     * @param int|null $bulan
     * @param int|null $tahun
     * @param int|null $kegiatanId
     * @param int|null $subKegiatanId
     * @return array
     */
    public function getWithFilters(?int $bulan = null, ?int $tahun = null, ?int $kegiatanId = null, ?int $subKegiatanId = null, ?string $status = null): array
    {
        try {
            $conditions = [];
            $params = [];

            if ($bulan !== null) {
                $conditions[] = 'MONTH(t.tanggal) = :bulan';
                $params[':bulan'] = $bulan;
            }
            if ($tahun !== null) {
                $conditions[] = 'YEAR(t.tanggal) = :tahun';
                $params[':tahun'] = $tahun;
            }
            if ($status !== null && $status !== '') {
                $conditions[] = 't.status = :status';
                $params[':status'] = $status;
            }
            if ($subKegiatanId !== null) {
                $conditions[] = 'r.sub_kegiatan_id = :sub_kegiatan_id';
                $params[':sub_kegiatan_id'] = $subKegiatanId;
            } elseif ($kegiatanId !== null) {
                $conditions[] = 'sk.kegiatan_id = :kegiatan_id';
                $params[':kegiatan_id'] = $kegiatanId;
            }

            $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

            $stmt = $this->db->prepare("
                SELECT t.*, 
                       s.kode_seksi, s.nama_seksi,
                       r.kode_rekening, r.nama_rekening,
                       sk.kode_sub_kegiatan, sk.nama_sub_kegiatan,
                       k.kode_kegiatan, k.nama_kegiatan,
                       p.kode_program, p.nama_program
                FROM transaksi t
                INNER JOIN seksi s ON t.seksi_id = s.id
                INNER JOIN rekening r ON t.rekening_id = r.id
                INNER JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
                INNER JOIN kegiatan k ON sk.kegiatan_id = k.id
                INNER JOIN program p ON k.program_id = p.id
                {$where}
                ORDER BY t.tanggal DESC, t.id DESC
            ");
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('Error fetching transactions with filters: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch transactions');
        }
    }

    /**
     * Get all transactions with related information
     * 
     * @return array
     */
    public function getAll(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, 
                       s.kode_seksi, s.nama_seksi,
                       r.kode_rekening, r.nama_rekening,
                       sk.kode_sub_kegiatan, sk.nama_sub_kegiatan,
                       k.kode_kegiatan, k.nama_kegiatan,
                       p.kode_program, p.nama_program
                FROM transaksi t
                INNER JOIN seksi s ON t.seksi_id = s.id
                INNER JOIN rekening r ON t.rekening_id = r.id
                INNER JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
                INNER JOIN kegiatan k ON sk.kegiatan_id = k.id
                INNER JOIN program p ON k.program_id = p.id
                ORDER BY t.tanggal DESC, t.id DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching transactions: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch transactions');
        }
    }

    /**
     * Get transaction by ID
     * 
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, 
                       s.kode_seksi, s.nama_seksi,
                       r.kode_rekening, r.nama_rekening,
                       sk.kode_sub_kegiatan, sk.nama_sub_kegiatan,
                       k.kode_kegiatan, k.nama_kegiatan,
                       p.kode_program, p.nama_program
                FROM transaksi t
                INNER JOIN seksi s ON t.seksi_id = s.id
                INNER JOIN rekening r ON t.rekening_id = r.id
                INNER JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
                INNER JOIN kegiatan k ON sk.kegiatan_id = k.id
                INNER JOIN program p ON k.program_id = p.id
                WHERE t.id = :id
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log('Error fetching transaction: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch transaction');
        }
    }

    /**
     * Get transactions by rekening and year
     * 
     * @param int $rekeningId
     * @param int $tahun
     * @return array
     */
    public function getByRekeningAndYear(int $rekeningId, int $tahun): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM transaksi 
                WHERE rekening_id = :rekening_id 
                AND YEAR(tanggal) = :tahun
                AND status = 'diverifikasi'
                ORDER BY tanggal DESC
            ");
            $stmt->bindParam(':rekening_id', $rekeningId, PDO::PARAM_INT);
            $stmt->bindParam(':tahun', $tahun, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching transactions: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch transactions');
        }
    }

    /**
     * Create new transaction
     * 
     * @param string $tanggal
     * @param int $seksiId
     * @param int $rekeningId
     * @param string $uraian
     * @param float $nilai
     * @param string $nomorBukti
     * @return int Inserted ID
     */
    public function create(string $tanggal, int $seksiId, int $rekeningId, string $uraian, float $nilai, string $nomorBukti): int
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO transaksi (tanggal, seksi_id, rekening_id, uraian, nilai, nomor_bukti) 
                VALUES (:tanggal, :seksi_id, :rekening_id, :uraian, :nilai, :nomor_bukti)
            ");
            $stmt->bindParam(':tanggal', $tanggal, PDO::PARAM_STR);
            $stmt->bindParam(':seksi_id', $seksiId, PDO::PARAM_INT);
            $stmt->bindParam(':rekening_id', $rekeningId, PDO::PARAM_INT);
            $stmt->bindParam(':uraian', $uraian, PDO::PARAM_STR);
            $stmt->bindParam(':nilai', $nilai, PDO::PARAM_STR);
            $stmt->bindParam(':nomor_bukti', $nomorBukti, PDO::PARAM_STR);
            $stmt->execute();
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Error creating transaction: ' . $e->getMessage());
            throw new \RuntimeException('Failed to create transaction');
        }
    }

    /**
     * Create new transaction (input oleh seksi, status 'diajukan') dengan dukungan field BKU & Surat Tugas
     *
     * @return int Inserted ID
     */
    public function createSeksi(
        string $tanggal,
        int $seksiId,
        int $rekeningId,
        string $uraian,
        float $nilai,
        string $nomorBukti,
        int $inputBy,
        ?string $namaPenerima = null,
        string $jenisTransaksi = 'lainnya',
        ?string $nomorSuratTugas = null,
        ?string $tanggalSuratTugas = null,
        ?string $tanggalPelaksanaan = null,
        ?string $lokasiKegiatan = null,
        ?int $suratTugasRefId = null,
        ?string $pegawaiNip = null
    ): int {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO transaksi (
                    tanggal, seksi_id, rekening_id, uraian, nilai, nomor_bukti, status, input_by,
                    nama_penerima, jenis_transaksi, nomor_surat_tugas, tanggal_surat_tugas,
                    tanggal_pelaksanaan, lokasi_kegiatan, surat_tugas_ref_id, pegawai_nip
                ) VALUES (
                    :tanggal, :seksi_id, :rekening_id, :uraian, :nilai, :nomor_bukti, 'diajukan', :input_by,
                    :nama_penerima, :jenis_transaksi, :nomor_surat_tugas, :tanggal_surat_tugas,
                    :tanggal_pelaksanaan, :lokasi_kegiatan, :surat_tugas_ref_id, :pegawai_nip
                )
            ");
            $stmt->bindParam(':tanggal', $tanggal, PDO::PARAM_STR);
            $stmt->bindParam(':seksi_id', $seksiId, PDO::PARAM_INT);
            $stmt->bindParam(':rekening_id', $rekeningId, PDO::PARAM_INT);
            $stmt->bindParam(':uraian', $uraian, PDO::PARAM_STR);
            $stmt->bindParam(':nilai', $nilai, PDO::PARAM_STR);
            $stmt->bindParam(':nomor_bukti', $nomorBukti, PDO::PARAM_STR);
            $stmt->bindParam(':input_by', $inputBy, PDO::PARAM_INT);
            $stmt->bindParam(':nama_penerima', $namaPenerima, PDO::PARAM_STR);
            $stmt->bindParam(':jenis_transaksi', $jenisTransaksi, PDO::PARAM_STR);
            $stmt->bindParam(':nomor_surat_tugas', $nomorSuratTugas, PDO::PARAM_STR);
            $stmt->bindParam(':tanggal_surat_tugas', $tanggalSuratTugas, PDO::PARAM_STR);
            $stmt->bindParam(':tanggal_pelaksanaan', $tanggalPelaksanaan, PDO::PARAM_STR);
            $stmt->bindParam(':lokasi_kegiatan', $lokasiKegiatan, PDO::PARAM_STR);
            $stmt->bindParam(':surat_tugas_ref_id', $suratTugasRefId, PDO::PARAM_INT);
            $stmt->bindParam(':pegawai_nip', $pegawaiNip, PDO::PARAM_STR);
            $stmt->execute();
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Error creating seksi transaction: ' . $e->getMessage());
            throw new \RuntimeException('Failed to create transaction');
        }
    }

    /**
     * Verifikasi atau tolak transaksi oleh admin/bendahara
     */
    public function verifikasi(int $id, string $status, int $verifBy, string $catatan): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE transaksi
                SET status = :status,
                    diverifikasi_by = :verif_by,
                    diverifikasi_at = NOW(),
                    catatan_verifikasi = :catatan
                WHERE id = :id
            ");
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->bindParam(':verif_by', $verifBy, PDO::PARAM_INT);
            $stmt->bindParam(':catatan', $catatan, PDO::PARAM_STR);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            error_log('Error verifying transaction: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get transaksi milik seksi dengan filter & search & pagination (untuk halaman 'Transaksi Saya')
     * Params null = tidak difilter. $q mencari uraian/nomor_bukti (LIKE).
     */
    public function getBySeksiFiltered(?int $inputBy, ?string $status = null, ?int $bulan = null, ?int $tahun = null, ?string $q = null, ?int $limit = null, ?int $offset = null): array
    {
        try {
            $conditions = [];
            $params = [];
            if ($inputBy !== null) {
                $conditions[] = 't.input_by = :input_by';
                $params[':input_by'] = $inputBy;
            }
            if ($status !== null && $status !== '' && $status !== 'semua') {
                $conditions[] = 't.status = :status';
                $params[':status'] = $status;
            }
            if ($bulan !== null && $bulan >= 1 && $bulan <= 12) {
                $conditions[] = 'MONTH(t.tanggal) = :bulan';
                $params[':bulan'] = $bulan;
            }
            if ($tahun !== null && $tahun > 0) {
                $conditions[] = 'YEAR(t.tanggal) = :tahun';
                $params[':tahun'] = $tahun;
            }
            if ($q !== null && trim($q) !== '') {
                $conditions[] = '(t.uraian LIKE :q_uraian OR t.nomor_bukti LIKE :q_bukti OR t.nama_penerima LIKE :q_penerima)';
                $kw = '%' . trim($q) . '%';
                $params[':q_uraian'] = $kw;
                $params[':q_bukti'] = $kw;
                $params[':q_penerima'] = $kw;
            }
            $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
            $limitSql = '';
            if ($limit !== null && $limit > 0) {
                $limitSql = ' LIMIT ' . (int)$limit;
                if ($offset !== null && $offset >= 0) $limitSql .= ' OFFSET ' . (int)$offset;
            }
            $sql = "
                SELECT t.*,
                       s.kode_seksi, s.nama_seksi,
                       r.kode_rekening, r.nama_rekening,
                       sk.kode_sub_kegiatan, sk.nama_sub_kegiatan,
                       k.kode_kegiatan, k.nama_kegiatan,
                       p.kode_program, p.nama_program,
                       u.username AS input_oleh
                FROM transaksi t
                INNER JOIN seksi s ON t.seksi_id = s.id
                INNER JOIN rekening r ON t.rekening_id = r.id
                INNER JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
                INNER JOIN kegiatan k ON sk.kegiatan_id = k.id
                INNER JOIN program p ON k.program_id = p.id
                LEFT JOIN users u ON u.id = t.input_by
                {$where}
                ORDER BY t.tanggal DESC, t.id DESC{$limitSql}";
            $stmt = $this->db->prepare($sql);
            foreach ($params as $k => $v) {
                $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($k, $v, $type);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching seksi filtered: ' . $e->getMessage());
            return [];
        }
    }

    public function countBySeksiFiltered(?int $inputBy, ?string $status = null, ?int $bulan = null, ?int $tahun = null, ?string $q = null): int
    {
        try {
            $conditions = [];
            $params = [];
            if ($inputBy !== null) {
                $conditions[] = 't.input_by = :input_by';
                $params[':input_by'] = $inputBy;
            }
            if ($status !== null && $status !== '' && $status !== 'semua') {
                $conditions[] = 't.status = :status';
                $params[':status'] = $status;
            }
            if ($bulan !== null && $bulan >= 1 && $bulan <= 12) {
                $conditions[] = 'MONTH(t.tanggal) = :bulan';
                $params[':bulan'] = $bulan;
            }
            if ($tahun !== null && $tahun > 0) {
                $conditions[] = 'YEAR(t.tanggal) = :tahun';
                $params[':tahun'] = $tahun;
            }
            if ($q !== null && trim($q) !== '') {
                $conditions[] = '(t.uraian LIKE :q_uraian OR t.nomor_bukti LIKE :q_bukti OR t.nama_penerima LIKE :q_penerima)';
                $kw = '%' . trim($q) . '%';
                $params[':q_uraian'] = $kw;
                $params[':q_bukti'] = $kw;
                $params[':q_penerima'] = $kw;
            }
            $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM transaksi t {$where}");
            foreach ($params as $k => $v) {
                $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($k, $v, $type);
            }
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('Error count seksi filtered: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Hitung jumlah per status untuk pill (Opsi A: filter bulan/tahun/q aktif, ignore status).
     * 1 query GROUP BY, tidak N+1. Menghormati workflow.
     */
    public function getStatusCountsBySeksi(?int $inputBy, ?int $bulan = null, ?int $tahun = null, ?string $q = null): array
    {
        try {
            $conditions = [];
            $params = [];
            if ($inputBy !== null) {
                $conditions[] = 't.input_by = :input_by';
                $params[':input_by'] = $inputBy;
            }
            if ($bulan !== null && $bulan >= 1 && $bulan <= 12) {
                $conditions[] = 'MONTH(t.tanggal) = :bulan';
                $params[':bulan'] = $bulan;
            }
            if ($tahun !== null && $tahun > 0) {
                $conditions[] = 'YEAR(t.tanggal) = :tahun';
                $params[':tahun'] = $tahun;
            }
            if ($q !== null && trim($q) !== '') {
                $conditions[] = '(t.uraian LIKE :q_uraian OR t.nomor_bukti LIKE :q_bukti OR t.nama_penerima LIKE :q_penerima)';
                $kw = '%' . trim($q) . '%';
                $params[':q_uraian'] = $kw;
                $params[':q_bukti'] = $kw;
                $params[':q_penerima'] = $kw;
            }
            $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
            $stmt = $this->db->prepare("SELECT t.status, COUNT(*) as cnt FROM transaksi t {$where} GROUP BY t.status");
            foreach ($params as $k => $v) {
                $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($k, $v, $type);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $all = 0; foreach ($rows as $c) $all += (int)$c;
            return [
                '' => $all,
                'diajukan' => (int)($rows['diajukan'] ?? 0),
                'diverifikasi' => (int)($rows['diverifikasi'] ?? 0),
                'ditolak' => (int)($rows['ditolak'] ?? 0),
            ];
        } catch (PDOException $e) {
            error_log('Error status counts: ' . $e->getMessage());
            return [''=>0,'diajukan'=>0,'diverifikasi'=>0,'ditolak'=>0];
        }
    }

    /**
     * Get transaksi milik seksi (untuk halaman 'Transaksi Saya') — legacy wrapper, tetap kompatibel
     */
    public function getBySeksi(int $seksiId, ?int $inputBy = null): array
    {
        if ($inputBy !== null) return $this->getBySeksiFiltered($inputBy);
        try {
            $stmt = $this->db->prepare("
                    SELECT t.*,
                           s.kode_seksi, s.nama_seksi,
                           r.kode_rekening, r.nama_rekening,
                           sk.kode_sub_kegiatan, sk.nama_sub_kegiatan,
                           k.kode_kegiatan, k.nama_kegiatan,
                           p.kode_program, p.nama_program,
                           u.username AS input_oleh
                    FROM transaksi t
                    INNER JOIN seksi s ON t.seksi_id = s.id
                    INNER JOIN rekening r ON t.rekening_id = r.id
                    INNER JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
                    INNER JOIN kegiatan k ON sk.kegiatan_id = k.id
                    INNER JOIN program p ON k.program_id = p.id
                    LEFT JOIN users u ON u.id = t.input_by
                    WHERE t.seksi_id = :seksi_id
                    ORDER BY t.tanggal DESC, t.id DESC
                ");
            $stmt->bindParam(':seksi_id', $seksiId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching seksi transactions: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Update transaction oleh seksi (hanya jika status masih 'diajukan' atau 'ditolak') dengan field BKU
     */
    public function updateSeksi(
        int $id,
        int $inputBy,
        string $tanggal,
        int $seksiId,
        int $rekeningId,
        string $uraian,
        float $nilai,
        string $nomorBukti,
        ?string $namaPenerima = null,
        string $jenisTransaksi = 'lainnya',
        ?string $nomorSuratTugas = null,
        ?string $tanggalSuratTugas = null,
        ?string $tanggalPelaksanaan = null,
        ?string $lokasiKegiatan = null,
        ?int $suratTugasRefId = null
    ): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE transaksi
                SET tanggal = :tanggal,
                    seksi_id = :seksi_id,
                    rekening_id = :rekening_id,
                    uraian = :uraian,
                    nilai = :nilai,
                    nomor_bukti = :nomor_bukti,
                    nama_penerima = :nama_penerima,
                    jenis_transaksi = :jenis_transaksi,
                    nomor_surat_tugas = :nomor_surat_tugas,
                    tanggal_surat_tugas = :tanggal_surat_tugas,
                    tanggal_pelaksanaan = :tanggal_pelaksanaan,
                    lokasi_kegiatan = :lokasi_kegiatan,
                    surat_tugas_ref_id = :surat_tugas_ref_id
                WHERE id = :id AND input_by = :input_by AND status IN ('diajukan','ditolak')
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':input_by', $inputBy, PDO::PARAM_INT);
            $stmt->bindParam(':tanggal', $tanggal, PDO::PARAM_STR);
            $stmt->bindParam(':seksi_id', $seksiId, PDO::PARAM_INT);
            $stmt->bindParam(':rekening_id', $rekeningId, PDO::PARAM_INT);
            $stmt->bindParam(':uraian', $uraian, PDO::PARAM_STR);
            $stmt->bindParam(':nilai', $nilai, PDO::PARAM_STR);
            $stmt->bindParam(':nomor_bukti', $nomorBukti, PDO::PARAM_STR);
            $stmt->bindParam(':nama_penerima', $namaPenerima, PDO::PARAM_STR);
            $stmt->bindParam(':jenis_transaksi', $jenisTransaksi, PDO::PARAM_STR);
            $stmt->bindParam(':nomor_surat_tugas', $nomorSuratTugas, PDO::PARAM_STR);
            $stmt->bindParam(':tanggal_surat_tugas', $tanggalSuratTugas, PDO::PARAM_STR);
            $stmt->bindParam(':tanggal_pelaksanaan', $tanggalPelaksanaan, PDO::PARAM_STR);
            $stmt->bindParam(':lokasi_kegiatan', $lokasiKegiatan, PDO::PARAM_STR);
            $stmt->bindParam(':surat_tugas_ref_id', $suratTugasRefId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('Error updating seksi transaction: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete transaction oleh seksi (hanya jika status masih 'diajukan' atau 'ditolak')
     */
    public function deleteSeksi(int $id, int $inputBy): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM transaksi WHERE id = :id AND input_by = :input_by AND status IN ('diajukan','ditolak')");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':input_by', $inputBy, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('Error deleting seksi transaction: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update transaction
     * 
     * @param int $id
     * @param string $tanggal
     * @param int $seksiId
     * @param int $rekeningId
     * @param string $uraian
     * @param float $nilai
     * @param string $nomorBukti
     * @return bool
     */
    public function update(int $id, string $tanggal, int $seksiId, int $rekeningId, string $uraian, float $nilai, string $nomorBukti): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE transaksi 
                SET tanggal = :tanggal,
                    seksi_id = :seksi_id,
                    rekening_id = :rekening_id,
                    uraian = :uraian,
                    nilai = :nilai,
                    nomor_bukti = :nomor_bukti
                WHERE id = :id
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':tanggal', $tanggal, PDO::PARAM_STR);
            $stmt->bindParam(':seksi_id', $seksiId, PDO::PARAM_INT);
            $stmt->bindParam(':rekening_id', $rekeningId, PDO::PARAM_INT);
            $stmt->bindParam(':uraian', $uraian, PDO::PARAM_STR);
            $stmt->bindParam(':nilai', $nilai, PDO::PARAM_STR);
            $stmt->bindParam(':nomor_bukti', $nomorBukti, PDO::PARAM_STR);

            $result = $stmt->execute();
            $rowCount = $stmt->rowCount();

            // Log for debugging
            error_log("Update transaksi ID {$id}: execute={$result}, rowCount={$rowCount}");

            // Return true if execute succeeded and at least one row was updated
            return $result && $rowCount > 0;
        } catch (PDOException $e) {
            error_log('Error updating transaction: ' . $e->getMessage());
            throw new \RuntimeException('Failed to update transaction');
        }
    }

    /**
     * Delete transaction
     * 
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM transaksi WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error deleting transaction: ' . $e->getMessage());
            throw new \RuntimeException('Failed to delete transaction');
        }
    }

    /**
     * Delete multiple transactions by IDs in a single database transaction.
     * Also unlinks any associated rincian_biaya_perjalanan_dinas.
     * 
     * @param int[] $ids
     * @return int Number of deleted transactions
     */
    public function deleteBatch(array $ids): int
    {
        $validIds = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
        if (empty($validIds)) {
            return 0;
        }

        $this->db->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($validIds), '?'));

            // Unlink rincian_biaya_perjalanan_dinas jika ada
            try {
                $unlinkStmt = $this->db->prepare("UPDATE rincian_biaya_perjalanan_dinas SET transaksi_id = NULL WHERE transaksi_id IN ($placeholders)");
                $unlinkStmt->execute($validIds);
            } catch (\Exception $e) {
                // Kolom/tabel mungkin opsional jika belum dimigrasi
                error_log("Notice unlinking rincian_biaya_perjalanan_dinas on deleteBatch: " . $e->getMessage());
            }

            // Hapus data transaksi
            $stmt = $this->db->prepare("DELETE FROM transaksi WHERE id IN ($placeholders)");
            $stmt->execute($validIds);
            $deletedCount = $stmt->rowCount();

            $this->db->commit();
            return $deletedCount;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('Error in Transaksi::deleteBatch: ' . $e->getMessage());
            throw new \RuntimeException('Gagal menghapus batch transaksi: ' . $e->getMessage());
        }
    }

    /**
     * Get all transactions filtered by month and year
     * 
     * @param int $bulan Month (1-12)
     * @param int $tahun Year
     * @return array
     */
    public function getByMonthYear(int $bulan, int $tahun): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, 
                       s.kode_seksi, s.nama_seksi,
                       r.kode_rekening, r.nama_rekening,
                       sk.kode_sub_kegiatan, sk.nama_sub_kegiatan,
                       k.kode_kegiatan, k.nama_kegiatan,
                       p.kode_program, p.nama_program
                FROM transaksi t
                INNER JOIN seksi s ON t.seksi_id = s.id
                INNER JOIN rekening r ON t.rekening_id = r.id
                INNER JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
                INNER JOIN kegiatan k ON sk.kegiatan_id = k.id
                INNER JOIN program p ON k.program_id = p.id
                WHERE MONTH(t.tanggal) = :bulan AND YEAR(t.tanggal) = :tahun
                ORDER BY t.tanggal DESC, t.id DESC
            ");
            $stmt->bindParam(':bulan', $bulan, PDO::PARAM_INT);
            $stmt->bindParam(':tahun', $tahun, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching transactions by month: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch transactions');
        }
    }

    /**
     * Get total transactions for rekening and year
     * 
     * @param int $rekeningId
     * @param int $tahun
     * @return float
     */
    public function getTotalByRekeningAndYear(int $rekeningId, int $tahun): float
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(nilai), 0) as total 
                FROM transaksi 
                WHERE rekening_id = :rekening_id 
                AND YEAR(tanggal) = :tahun
                AND status = 'diverifikasi'
            ");
            $stmt->bindParam(':rekening_id', $rekeningId, PDO::PARAM_INT);
            $stmt->bindParam(':tahun', $tahun, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (float) ($result['total'] ?? 0);
        } catch (PDOException $e) {
            error_log('Error calculating total transactions: ' . $e->getMessage());
            return 0;
        }
    }
}

