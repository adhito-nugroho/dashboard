<?php

namespace App\Models;

use PDO;
use PDOException;

class RencanaPenyerapan
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Daftar rencana dengan filter tahun / kegiatan / sub kegiatan / mekanisme / kata kunci.
     */
    public function getAllWithFilters(
        ?int $tahun = null,
        ?int $kegiatanId = null,
        ?int $subKegiatanId = null,
        ?string $mekanisme = null,
        ?string $q = null
    ): array {
        try {
            $conditions = [];
            $params = [];

            if ($tahun !== null) {
                $conditions[] = 'rp.tahun = :tahun';
                $params[':tahun'] = $tahun;
            }
            if ($subKegiatanId !== null) {
                $conditions[] = 'r.sub_kegiatan_id = :sub_kegiatan_id';
                $params[':sub_kegiatan_id'] = $subKegiatanId;
            } elseif ($kegiatanId !== null) {
                $conditions[] = 'k.id = :kegiatan_id';
                $params[':kegiatan_id'] = $kegiatanId;
            }
            if ($mekanisme !== null && in_array($mekanisme, ['GU', 'TU', 'LS'], true)) {
                $conditions[] = 'rp.mekanisme = :mekanisme';
                $params[':mekanisme'] = $mekanisme;
            }
            if ($q !== null && trim($q) !== '') {
                $conditions[] = '(rp.bentuk_kegiatan LIKE :q1 OR rp.uraian LIKE :q2 OR rp.keterangan LIKE :q3 OR r.kode_rekening LIKE :q4 OR r.nama_rekening LIKE :q5)';
                $kw = '%' . trim($q) . '%';
                $params[':q1'] = $kw;
                $params[':q2'] = $kw;
                $params[':q3'] = $kw;
                $params[':q4'] = $kw;
                $params[':q5'] = $kw;
            }

            $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

            $stmt = $this->db->prepare("
                SELECT rp.*,
                       r.kode_rekening, r.nama_rekening, r.sub_kegiatan_id,
                       sk.kode_sub_kegiatan, sk.nama_sub_kegiatan, sk.seksi_id,
                       k.id AS kegiatan_id, k.kode_kegiatan, k.nama_kegiatan,
                       p.id AS program_id, p.kode_program, p.nama_program, p.tahun AS program_tahun,
                       s.kode_seksi, s.nama_seksi
                FROM rencana_penyerapan rp
                INNER JOIN rekening r ON rp.rekening_id = r.id
                INNER JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
                INNER JOIN kegiatan k ON sk.kegiatan_id = k.id
                INNER JOIN program p ON k.program_id = p.id
                LEFT JOIN seksi s ON sk.seksi_id = s.id
                {$where}
                ORDER BY p.kode_program ASC, k.kode_kegiatan ASC, sk.kode_sub_kegiatan ASC,
                         r.kode_rekening ASC, rp.bulan_mulai ASC, rp.id ASC
            ");
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching rencana penyerapan: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch rencana penyerapan');
        }
    }

    public function getById(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT rp.*,
                       r.kode_rekening, r.nama_rekening, r.sub_kegiatan_id,
                       sk.kode_sub_kegiatan, sk.nama_sub_kegiatan, sk.seksi_id,
                       k.id AS kegiatan_id, k.kode_kegiatan, k.nama_kegiatan,
                       p.id AS program_id, p.kode_program, p.nama_program,
                       s.kode_seksi, s.nama_seksi
                FROM rencana_penyerapan rp
                INNER JOIN rekening r ON rp.rekening_id = r.id
                INNER JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
                INNER JOIN kegiatan k ON sk.kegiatan_id = k.id
                INNER JOIN program p ON k.program_id = p.id
                LEFT JOIN seksi s ON sk.seksi_id = s.id
                WHERE rp.id = :id
            ");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('Error fetching rencana penyerapan: ' . $e->getMessage());
            throw new \RuntimeException('Failed to fetch rencana penyerapan');
        }
    }

    public function create(array $data, ?int $createdBy = null): int
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO rencana_penyerapan
                    (rekening_id, tahun, bentuk_kegiatan, uraian, mekanisme,
                     bulan_mulai, bulan_selesai, nilai_rencana, keterangan, created_by)
                VALUES
                    (:rekening_id, :tahun, :bentuk_kegiatan, :uraian, :mekanisme,
                     :bulan_mulai, :bulan_selesai, :nilai_rencana, :keterangan, :created_by)
            ");
            $stmt->bindValue(':rekening_id', (int) $data['rekening_id'], PDO::PARAM_INT);
            $stmt->bindValue(':tahun', (int) $data['tahun'], PDO::PARAM_INT);
            $stmt->bindValue(':bentuk_kegiatan', trim((string) $data['bentuk_kegiatan']), PDO::PARAM_STR);
            $stmt->bindValue(':uraian', $this->nullIfEmpty($data['uraian'] ?? null), $this->nullIfEmpty($data['uraian'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':mekanisme', $data['mekanisme'], PDO::PARAM_STR);
            $stmt->bindValue(':bulan_mulai', (int) $data['bulan_mulai'], PDO::PARAM_INT);
            $stmt->bindValue(':bulan_selesai', (int) $data['bulan_selesai'], PDO::PARAM_INT);
            $stmt->bindValue(':nilai_rencana', (float) $data['nilai_rencana']);
            $stmt->bindValue(':keterangan', $this->nullIfEmpty($data['keterangan'] ?? null), $this->nullIfEmpty($data['keterangan'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':created_by', $createdBy, $createdBy === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->execute();
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Error creating rencana penyerapan: ' . $e->getMessage());
            throw new \RuntimeException('Failed to create rencana penyerapan');
        }
    }

    public function update(int $id, array $data): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE rencana_penyerapan
                SET rekening_id = :rekening_id,
                    tahun = :tahun,
                    bentuk_kegiatan = :bentuk_kegiatan,
                    uraian = :uraian,
                    mekanisme = :mekanisme,
                    bulan_mulai = :bulan_mulai,
                    bulan_selesai = :bulan_selesai,
                    nilai_rencana = :nilai_rencana,
                    keterangan = :keterangan
                WHERE id = :id
            ");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':rekening_id', (int) $data['rekening_id'], PDO::PARAM_INT);
            $stmt->bindValue(':tahun', (int) $data['tahun'], PDO::PARAM_INT);
            $stmt->bindValue(':bentuk_kegiatan', trim((string) $data['bentuk_kegiatan']), PDO::PARAM_STR);
            $stmt->bindValue(':uraian', $this->nullIfEmpty($data['uraian'] ?? null), $this->nullIfEmpty($data['uraian'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':mekanisme', $data['mekanisme'], PDO::PARAM_STR);
            $stmt->bindValue(':bulan_mulai', (int) $data['bulan_mulai'], PDO::PARAM_INT);
            $stmt->bindValue(':bulan_selesai', (int) $data['bulan_selesai'], PDO::PARAM_INT);
            $stmt->bindValue(':nilai_rencana', (float) $data['nilai_rencana']);
            $stmt->bindValue(':keterangan', $this->nullIfEmpty($data['keterangan'] ?? null), $this->nullIfEmpty($data['keterangan'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error updating rencana penyerapan: ' . $e->getMessage());
            throw new \RuntimeException('Failed to update rencana penyerapan');
        }
    }

    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM rencana_penyerapan WHERE id = :id");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error deleting rencana penyerapan: ' . $e->getMessage());
            throw new \RuntimeException('Failed to delete rencana penyerapan');
        }
    }

    /** Total rencana sebuah rekening pada tahun tertentu (opsional kecualikan satu baris saat edit). */
    public function getTotalByRekeningAndYear(int $rekeningId, int $tahun, ?int $excludeId = null): float
    {
        try {
            $sql = "SELECT COALESCE(SUM(nilai_rencana), 0) FROM rencana_penyerapan WHERE rekening_id = :rekening_id AND tahun = :tahun";
            if ($excludeId !== null) {
                $sql .= ' AND id != :exclude_id';
            }
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':rekening_id', $rekeningId, PDO::PARAM_INT);
            $stmt->bindValue(':tahun', $tahun, PDO::PARAM_INT);
            if ($excludeId !== null) {
                $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
            }
            $stmt->execute();
            return (float) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('Error summing rencana penyerapan: ' . $e->getMessage());
            return 0;
        }
    }

    public function getPaguForRekening(int $rekeningId, int $tahun): ?float
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(nilai_pagu), 0)
                FROM pagu
                WHERE rekening_id = :rekening_id AND tahun = :tahun
            ");
            $stmt->bindValue(':rekening_id', $rekeningId, PDO::PARAM_INT);
            $stmt->bindValue(':tahun', $tahun, PDO::PARAM_INT);
            $stmt->execute();
            $sum = (float) $stmt->fetchColumn();

            $check = $this->db->prepare("SELECT COUNT(*) FROM pagu WHERE rekening_id = :rekening_id AND tahun = :tahun");
            $check->bindValue(':rekening_id', $rekeningId, PDO::PARAM_INT);
            $check->bindValue(':tahun', $tahun, PDO::PARAM_INT);
            $check->execute();

            return (int) $check->fetchColumn() > 0 ? $sum : null;
        } catch (PDOException $e) {
            error_log('Error fetching pagu rekening: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Realisasi terverifikasi satu rekening per mekanisme.
     *
     * @return array{total:float,GU:float,TU:float,LS:float,UP:float}
     */
    public function getRealisasiForRekening(int $rekeningId, int $tahun): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(t.nilai), 0) AS total,
                       COALESCE(SUM(CASE WHEN t.pengajuan_tu_id IS NOT NULL THEN t.nilai ELSE 0 END), 0) AS tu,
                       COALESCE(SUM(CASE WHEN t.pengajuan_tu_id IS NULL AND t.pengajuan_gu_id IS NOT NULL THEN t.nilai ELSE 0 END), 0) AS gu,
                       COALESCE(SUM(CASE WHEN t.pengajuan_tu_id IS NULL AND t.pengajuan_gu_id IS NULL AND t.sumber_dana = 'LS' THEN t.nilai ELSE 0 END), 0) AS ls,
                       COALESCE(SUM(CASE WHEN t.pengajuan_tu_id IS NULL AND t.pengajuan_gu_id IS NULL AND t.sumber_dana <> 'LS' THEN t.nilai ELSE 0 END), 0) AS up
                FROM transaksi t
                WHERE t.rekening_id = :rekening_id
                  AND t.status = 'diverifikasi'
                  AND YEAR(COALESCE(t.tanggal_lunas_dibayar, DATE(t.diverifikasi_at), t.tanggal)) = :tahun
            ");
            $stmt->bindValue(':rekening_id', $rekeningId, PDO::PARAM_INT);
            $stmt->bindValue(':tahun', $tahun, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'total' => (float) ($row['total'] ?? 0),
                'GU'    => (float) ($row['gu'] ?? 0),
                'TU'    => (float) ($row['tu'] ?? 0),
                'LS'    => (float) ($row['ls'] ?? 0),
                'UP'    => (float) ($row['up'] ?? 0),
            ];
        } catch (PDOException $e) {
            error_log('Error fetching realisasi rekening: ' . $e->getMessage());
            return ['total' => 0, 'GU' => 0, 'TU' => 0, 'LS' => 0, 'UP' => 0];
        }
    }

    /** @return array<int,float> rekening_id => nilai_pagu */
    public function getPaguMap(int $tahun, ?int $kegiatanId = null, ?int $subKegiatanId = null): array
    {
        try {
            $conditions = ['p.tahun = :tahun'];
            $params = [':tahun' => $tahun];

            if ($subKegiatanId !== null) {
                $conditions[] = 'r.sub_kegiatan_id = :sub_kegiatan_id';
                $params[':sub_kegiatan_id'] = $subKegiatanId;
            } elseif ($kegiatanId !== null) {
                $conditions[] = 'sk.kegiatan_id = :kegiatan_id';
                $params[':kegiatan_id'] = $kegiatanId;
            }

            $stmt = $this->db->prepare("
                SELECT p.rekening_id, SUM(p.nilai_pagu) AS nilai_pagu
                FROM pagu p
                INNER JOIN rekening r ON p.rekening_id = r.id
                INNER JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
                WHERE " . implode(' AND ', $conditions) . "
                GROUP BY p.rekening_id
            ");
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_INT);
            }
            $stmt->execute();

            $map = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $map[(int) $row['rekening_id']] = (float) $row['nilai_pagu'];
            }
            return $map;
        } catch (PDOException $e) {
            error_log('Error fetching pagu map: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Realisasi terverifikasi per rekening dan mekanisme.
     *
     * @return array<int,array{total:float,GU:float,TU:float,LS:float,UP:float}>
     */
    public function getRealisasiMap(int $tahun, ?int $kegiatanId = null, ?int $subKegiatanId = null): array
    {
        try {
            $conditions = [
                "t.status = 'diverifikasi'",
                'YEAR(COALESCE(t.tanggal_lunas_dibayar, DATE(t.diverifikasi_at), t.tanggal)) = :tahun',
            ];
            $params = [':tahun' => $tahun];

            if ($subKegiatanId !== null) {
                $conditions[] = 'r.sub_kegiatan_id = :sub_kegiatan_id';
                $params[':sub_kegiatan_id'] = $subKegiatanId;
            } elseif ($kegiatanId !== null) {
                $conditions[] = 'sk.kegiatan_id = :kegiatan_id';
                $params[':kegiatan_id'] = $kegiatanId;
            }

            $stmt = $this->db->prepare("
                SELECT t.rekening_id,
                       SUM(t.nilai) AS total,
                       SUM(CASE WHEN t.pengajuan_tu_id IS NOT NULL THEN t.nilai ELSE 0 END) AS tu,
                       SUM(CASE WHEN t.pengajuan_tu_id IS NULL AND t.pengajuan_gu_id IS NOT NULL THEN t.nilai ELSE 0 END) AS gu,
                       SUM(CASE WHEN t.pengajuan_tu_id IS NULL AND t.pengajuan_gu_id IS NULL AND t.sumber_dana = 'LS' THEN t.nilai ELSE 0 END) AS ls,
                       SUM(CASE WHEN t.pengajuan_tu_id IS NULL AND t.pengajuan_gu_id IS NULL AND t.sumber_dana <> 'LS' THEN t.nilai ELSE 0 END) AS up
                FROM transaksi t
                INNER JOIN rekening r ON t.rekening_id = r.id
                INNER JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
                WHERE " . implode(' AND ', $conditions) . "
                GROUP BY t.rekening_id
            ");
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_INT);
            }
            $stmt->execute();

            $map = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $map[(int) $row['rekening_id']] = [
                    'total' => (float) $row['total'],
                    'GU'    => (float) $row['gu'],
                    'TU'    => (float) $row['tu'],
                    'LS'    => (float) $row['ls'],
                    'UP'    => (float) $row['up'],
                ];
            }
            return $map;
        } catch (PDOException $e) {
            error_log('Error fetching realisasi map: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Realisasi terverifikasi per rekening dan per bulan.
     *
     * @return array<int,array<int,array{total:float,GU:float,TU:float,LS:float,UP:float}>>
     */
    public function getRealisasiMonthlyMap(int $tahun, ?int $kegiatanId = null, ?int $subKegiatanId = null): array
    {
        try {
            $conditions = [
                "t.status = 'diverifikasi'",
                'YEAR(COALESCE(t.tanggal_lunas_dibayar, DATE(t.diverifikasi_at), t.tanggal)) = :tahun',
            ];
            $params = [':tahun' => $tahun];

            if ($subKegiatanId !== null) {
                $conditions[] = 'r.sub_kegiatan_id = :sub_kegiatan_id';
                $params[':sub_kegiatan_id'] = $subKegiatanId;
            } elseif ($kegiatanId !== null) {
                $conditions[] = 'sk.kegiatan_id = :kegiatan_id';
                $params[':kegiatan_id'] = $kegiatanId;
            }

            $stmt = $this->db->prepare("
                SELECT t.rekening_id,
                       MONTH(COALESCE(t.tanggal_lunas_dibayar, DATE(t.diverifikasi_at), t.tanggal)) AS bulan,
                       SUM(t.nilai) AS total,
                       SUM(CASE WHEN t.pengajuan_tu_id IS NOT NULL THEN t.nilai ELSE 0 END) AS tu,
                       SUM(CASE WHEN t.pengajuan_tu_id IS NULL AND t.pengajuan_gu_id IS NOT NULL THEN t.nilai ELSE 0 END) AS gu,
                       SUM(CASE WHEN t.pengajuan_tu_id IS NULL AND t.pengajuan_gu_id IS NULL AND t.sumber_dana = 'LS' THEN t.nilai ELSE 0 END) AS ls,
                       SUM(CASE WHEN t.pengajuan_tu_id IS NULL AND t.pengajuan_gu_id IS NULL AND t.sumber_dana <> 'LS' THEN t.nilai ELSE 0 END) AS up
                FROM transaksi t
                INNER JOIN rekening r ON t.rekening_id = r.id
                INNER JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
                WHERE " . implode(' AND ', $conditions) . "
                GROUP BY t.rekening_id, bulan
            ");
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_INT);
            }
            $stmt->execute();

            $map = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $map[(int) $row['rekening_id']][(int) $row['bulan']] = [
                    'total' => (float) $row['total'],
                    'GU'    => (float) $row['gu'],
                    'TU'    => (float) $row['tu'],
                    'LS'    => (float) $row['ls'],
                    'UP'    => (float) $row['up'],
                ];
            }
            return $map;
        } catch (PDOException $e) {
            error_log('Error fetching realisasi bulanan map: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Realisasi terverifikasi satu rekening untuk 12 bulan (bulan kosong diisi nol).
     *
     * @return array<int,array{total:float,GU:float,TU:float,LS:float,UP:float}>
     */
    public function getRealisasiMonthlyForRekening(int $rekeningId, int $tahun): array
    {
        $result = [];
        for ($bulan = 1; $bulan <= 12; $bulan++) {
            $result[$bulan] = ['total' => 0.0, 'GU' => 0.0, 'TU' => 0.0, 'LS' => 0.0, 'UP' => 0.0];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT MONTH(COALESCE(t.tanggal_lunas_dibayar, DATE(t.diverifikasi_at), t.tanggal)) AS bulan,
                       SUM(t.nilai) AS total,
                       SUM(CASE WHEN t.pengajuan_tu_id IS NOT NULL THEN t.nilai ELSE 0 END) AS tu,
                       SUM(CASE WHEN t.pengajuan_tu_id IS NULL AND t.pengajuan_gu_id IS NOT NULL THEN t.nilai ELSE 0 END) AS gu,
                       SUM(CASE WHEN t.pengajuan_tu_id IS NULL AND t.pengajuan_gu_id IS NULL AND t.sumber_dana = 'LS' THEN t.nilai ELSE 0 END) AS ls,
                       SUM(CASE WHEN t.pengajuan_tu_id IS NULL AND t.pengajuan_gu_id IS NULL AND t.sumber_dana <> 'LS' THEN t.nilai ELSE 0 END) AS up
                FROM transaksi t
                WHERE t.rekening_id = :rekening_id
                  AND t.status = 'diverifikasi'
                  AND YEAR(COALESCE(t.tanggal_lunas_dibayar, DATE(t.diverifikasi_at), t.tanggal)) = :tahun
                GROUP BY bulan
            ");
            $stmt->bindValue(':rekening_id', $rekeningId, PDO::PARAM_INT);
            $stmt->bindValue(':tahun', $tahun, PDO::PARAM_INT);
            $stmt->execute();

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $result[(int) $row['bulan']] = [
                    'total' => (float) $row['total'],
                    'GU'    => (float) $row['gu'],
                    'TU'    => (float) $row['tu'],
                    'LS'    => (float) $row['ls'],
                    'UP'    => (float) $row['up'],
                ];
            }
        } catch (PDOException $e) {
            error_log('Error fetching realisasi bulanan rekening: ' . $e->getMessage());
            return array_fill(1, 12, ['total' => 0.0, 'GU' => 0.0, 'TU' => 0.0, 'LS' => 0.0, 'UP' => 0.0]);
        }

        return $result;
    }

    /** Rekening ber-pagu yang belum punya rencana pada tahun tsb. */
    public function getRekeningBelumDirencanakan(
        int $tahun,
        ?int $kegiatanId = null,
        ?int $subKegiatanId = null
    ): array {
        try {
            $conditions = [
                'p.tahun = :tahun',
                'NOT EXISTS (SELECT 1 FROM rencana_penyerapan rp WHERE rp.rekening_id = r.id AND rp.tahun = :tahun_rencana)',
            ];
            $params = [':tahun' => $tahun, ':tahun_rencana' => $tahun];

            if ($subKegiatanId !== null) {
                $conditions[] = 'r.sub_kegiatan_id = :sub_kegiatan_id';
                $params[':sub_kegiatan_id'] = $subKegiatanId;
            } elseif ($kegiatanId !== null) {
                $conditions[] = 'sk.kegiatan_id = :kegiatan_id';
                $params[':kegiatan_id'] = $kegiatanId;
            }

            $stmt = $this->db->prepare("
                SELECT r.id, r.kode_rekening, r.nama_rekening,
                       sk.kode_sub_kegiatan, sk.nama_sub_kegiatan,
                       k.kode_kegiatan, k.nama_kegiatan,
                       p2.kode_program, p2.nama_program,
                       SUM(p.nilai_pagu) AS nilai_pagu
                FROM rekening r
                INNER JOIN sub_kegiatan sk ON r.sub_kegiatan_id = sk.id
                INNER JOIN kegiatan k ON sk.kegiatan_id = k.id
                INNER JOIN program p2 ON k.program_id = p2.id
                INNER JOIN pagu p ON p.rekening_id = r.id
                WHERE " . implode(' AND ', $conditions) . "
                GROUP BY r.id, r.kode_rekening, r.nama_rekening,
                         sk.kode_sub_kegiatan, sk.nama_sub_kegiatan,
                         k.kode_kegiatan, k.nama_kegiatan,
                         p2.kode_program, p2.nama_program
                ORDER BY p2.kode_program ASC, k.kode_kegiatan ASC, sk.kode_sub_kegiatan ASC, r.kode_rekening ASC
            ");
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching rekening belum direncanakan: ' . $e->getMessage());
            return [];
        }
    }

    private function nullIfEmpty($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
