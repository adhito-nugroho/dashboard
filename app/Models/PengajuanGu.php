<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;

/**
 * Pengajuan SPJ menjadi GU (Ganti Uang).
 *
 * Alur fisik: GU cair -> belanja -> kumpulkan nota terverifikasi -> ajukan SPJ
 * (batch, dipilih manual, bisa 2x sebulan) -> Kasda cair (SP2D) -> kas utuh lagi.
 *
 * Satu pengajuan = satu baris kas_bank jenis 'gu' status 'menunggu_cair'
 * (nominal otomatis = jumlah belanja tercakup, anti salah ketik & anti
 * klaim ganda via transaksi.pengajuan_gu_id).
 */
class PengajuanGu
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Transaksi yang siap di-SPJ-kan: diverifikasi + UP + belum tercakup
     * pengajuan mana pun. Basis tanggal = tanggal bayar (konsisten kas/BKU).
     * Filter rentang opsional untuk siklus 2x sebulan (mis. 1–15 / 16–akhir).
     *
     * @return array<int, array<string,mixed>>
     */
    public function getSiapSpj(?string $dari = null, ?string $sampai = null): array
    {
        try {
            $conds = [
                "t.status = 'diverifikasi'",
                "t.sumber_dana = 'UP'",
                't.pengajuan_gu_id IS NULL',
            ];
            $params = [];
            if (Transaksi::normalizeTanggalLunas($dari ?? '') !== null) {
                $conds[] = 'COALESCE(t.tanggal_lunas_dibayar, DATE(t.diverifikasi_at), t.tanggal) >= :dari';
                $params[':dari'] = $dari;
            }
            if (Transaksi::normalizeTanggalLunas($sampai ?? '') !== null) {
                $conds[] = 'COALESCE(t.tanggal_lunas_dibayar, DATE(t.diverifikasi_at), t.tanggal) <= :sampai';
                $params[':sampai'] = $sampai;
            }
            $where = 'WHERE ' . implode(' AND ', $conds);
            $stmt = $this->db->prepare("
                SELECT t.id, t.tanggal, t.tanggal_lunas_dibayar, t.diverifikasi_at,
                    COALESCE(t.tanggal_lunas_dibayar, DATE(t.diverifikasi_at), t.tanggal) AS tanggal_efektif,
                    t.nomor_bukti, t.uraian, t.nama_penerima, t.nilai, s.nama_seksi
                FROM transaksi t
                INNER JOIN seksi s ON t.seksi_id = s.id
                {$where}
                ORDER BY tanggal_efektif ASC, t.id ASC
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching siap SPJ: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Total nominal belanja yang belum di-SPJ-kan (seluruh waktu).
     */
    public function getTotalBelumSpj(): float
    {
        try {
            return (float) $this->db->query("
                SELECT COALESCE(SUM(nilai), 0) FROM transaksi
                WHERE status = 'diverifikasi' AND sumber_dana = 'UP' AND pengajuan_gu_id IS NULL
            ")->fetchColumn();
        } catch (PDOException $e) {
            error_log('Error calculating belum SPJ: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function getAll(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT p.*, u.username AS created_by_name,
                    (SELECT COUNT(*) FROM transaksi t WHERE t.pengajuan_gu_id = p.id) AS jumlah_transaksi
                FROM pengajuan_gu p
                LEFT JOIN users u ON p.created_by = u.id
                ORDER BY p.tanggal_pengajuan DESC, p.id DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching pengajuan GU: ' . $e->getMessage());
            return [];
        }
    }

    public function getById(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM pengajuan_gu WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('Error fetching pengajuan GU by id: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function getItems(int $pengajuanId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT t.id, t.tanggal, t.tanggal_lunas_dibayar,
                    COALESCE(t.tanggal_lunas_dibayar, DATE(t.diverifikasi_at), t.tanggal) AS tanggal_efektif,
                    t.nomor_bukti, t.uraian, t.nilai, s.nama_seksi
                FROM transaksi t
                INNER JOIN seksi s ON t.seksi_id = s.id
                WHERE t.pengajuan_gu_id = :id
                ORDER BY tanggal_efektif ASC, t.id ASC
            ");
            $stmt->execute([':id' => $pengajuanId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching pengajuan items: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Buat pengajuan SPJ dari transaksi pilihan + otomatis catat GU
     * menunggu_cair di kas_bank. Return ID pengajuan baru.
     *
     * @param int[] $ids
     * @throws \RuntimeException bila tidak ada transaksi valid
     */
    public function create(array $ids, string $tanggalPengajuan, string $keterangan, int $createdBy, KasBank $kasBank): int
    {
        $validIds = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
        if (empty($validIds)) {
            throw new \RuntimeException('Tidak ada transaksi yang dipilih.');
        }
        $tgl = Transaksi::normalizeTanggalLunas($tanggalPengajuan) ?? date('Y-m-d');

        $ownTransaction = !$this->db->inTransaction();
        if ($ownTransaction) {
            $this->db->beginTransaction();
        }
        try {
            // Kunci hanya yang memenuhi syarat (anti klaim ganda / salah status)
            $placeholders = implode(',', array_fill(0, count($validIds), '?'));
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(nilai), 0) AS total, COUNT(*) AS c
                FROM transaksi
                WHERE id IN ($placeholders)
                  AND status = 'diverifikasi'
                  AND sumber_dana = 'UP'
                  AND pengajuan_gu_id IS NULL
            ");
            $stmt->execute($validIds);
            $agg = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$agg || (int) $agg['c'] === 0) {
                throw new \RuntimeException('Tidak ada transaksi valid (harus terverifikasi, dana UP, dan belum di-SPJ-kan).');
            }
            $total = (float) $agg['total'];

            $stmtIds = $this->db->prepare("
                SELECT id FROM transaksi
                WHERE id IN ($placeholders)
                  AND status = 'diverifikasi'
                  AND sumber_dana = 'UP'
                  AND pengajuan_gu_id IS NULL
                ORDER BY COALESCE(tanggal_lunas_dibayar, DATE(diverifikasi_at), tanggal) ASC, id ASC
            ");
            $stmtIds->execute($validIds);
            $finalIds = $stmtIds->fetchAll(PDO::FETCH_COLUMN);

            // Nomor pengajuan otomatis per bulan: SPJ-GU/MM/YYYY/NNN
            $time = strtotime($tgl) ?: time();
            $bln = (int) date('m', $time);
            $thn = (int) date('Y', $time);
            $stmtSeq = $this->db->prepare("
                SELECT COUNT(*) FROM pengajuan_gu
                WHERE MONTH(tanggal_pengajuan) = :b AND YEAR(tanggal_pengajuan) = :t
            ");
            $stmtSeq->execute([':b' => $bln, ':t' => $thn]);
            $seq = (int) $stmtSeq->fetchColumn() + 1;
            $nomor = sprintf('SPJ-GU/%02d/%d/%03d', $bln, $thn, $seq);

            $stmtIns = $this->db->prepare("
                INSERT INTO pengajuan_gu (nomor_pengajuan, tanggal_pengajuan, keterangan, total_nominal, status, created_by)
                VALUES (:nomor, :tanggal, :keterangan, :total, 'diajukan', :created_by)
            ");
            $stmtIns->execute([
                ':nomor'       => $nomor,
                ':tanggal'     => $tgl,
                ':keterangan'  => trim($keterangan) !== '' ? trim($keterangan) : 'Pengajuan GU atas ' . count($finalIds) . ' transaksi',
                ':total'       => $total,
                ':created_by'  => $createdBy,
            ]);
            $pengajuanId = (int) $this->db->lastInsertId();

            // Otomatis catat GU menunggu cair (nominal = jumlah SPJ, bukan ketikan)
            $kasBankId = $kasBank->create($tgl, 'gu', $nomor, 'Pengajuan ' . $nomor, $total, $createdBy, 'menunggu_cair', null);
            $stmtLink = $this->db->prepare('UPDATE pengajuan_gu SET kas_bank_id = :kas WHERE id = :id');
            $stmtLink->execute([':kas' => $kasBankId, ':id' => $pengajuanId]);

            // Tandai transaksi tercakup
            $stmtTag = $this->db->prepare("UPDATE transaksi SET pengajuan_gu_id = :pid WHERE id = :id");
            foreach ($finalIds as $trxId) {
                $stmtTag->execute([':pid' => $pengajuanId, ':id' => (int) $trxId]);
            }

            if ($ownTransaction) {
                $this->db->commit();
            }
            return $pengajuanId;
        } catch (\Throwable $e) {
            if ($ownTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Error creating pengajuan GU: ' . $e->getMessage());
            throw new \RuntimeException($e->getMessage());
        }
    }

    /**
     * Tandai pengajuan sudah cair (SP2D masuk): kas_bank -> cair.
     */
    public function tandaiCair(int $id, ?string $tanggalCair, KasBank $kasBank): bool
    {
        try {
            $pengajuan = $this->getById($id);
            if (!$pengajuan || ($pengajuan['status'] ?? '') !== 'diajukan' || empty($pengajuan['kas_bank_id'])) {
                return false;
            }
            $ok = $kasBank->tandaiCair((int) $pengajuan['kas_bank_id'], $tanggalCair ?: date('Y-m-d'));
            if (!$ok) {
                return false;
            }
            $stmt = $this->db->prepare("UPDATE pengajuan_gu SET status = 'cair' WHERE id = :id AND status = 'diajukan'");
            return $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            error_log('Error mencairkan pengajuan GU: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Batalkan pengajuan yang masih diajukan: lepas semua transaksi + hapus
     * baris GU menunggu_cair + hapus pengajuan.
     */
    public function batalkan(int $id, KasBank $kasBank): bool
    {
        try {
            $pengajuan = $this->getById($id);
            if (!$pengajuan || ($pengajuan['status'] ?? '') !== 'diajukan') {
                return false;
            }
            $ownTransaction = !$this->db->inTransaction();
            if ($ownTransaction) {
                $this->db->beginTransaction();
            }
            try {
                $this->db->prepare('UPDATE transaksi SET pengajuan_gu_id = NULL WHERE pengajuan_gu_id = :id')
                    ->execute([':id' => $id]);
                if (!empty($pengajuan['kas_bank_id'])) {
                    $kasBank->delete((int) $pengajuan['kas_bank_id']);
                }
                $this->db->prepare('DELETE FROM pengajuan_gu WHERE id = :id')->execute([':id' => $id]);
                if ($ownTransaction) {
                    $this->db->commit();
                }
                return true;
            } catch (\Throwable $e) {
                if ($ownTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $e;
            }
        } catch (\Throwable $e) {
            error_log('Error membatalkan pengajuan GU: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Lepas satu transaksi dari pengajuan yang masih diajukan (mis. salah
     * pilih): nominal pengajuan + baris kas disesuaikan ulang.
     */
    public function lepasTransaksi(int $pengajuanId, int $trxId, KasBank $kasBank): bool
    {
        try {
            $pengajuan = $this->getById($pengajuanId);
            if (!$pengajuan || ($pengajuan['status'] ?? '') !== 'diajukan') {
                return false;
            }
            $stmt = $this->db->prepare('UPDATE transaksi SET pengajuan_gu_id = NULL WHERE id = :trx AND pengajuan_gu_id = :pid');
            $stmt->execute([':trx' => $trxId, ':pid' => $pengajuanId]);
            if ($stmt->rowCount() === 0) {
                return false;
            }
            $sisa = (float) $this->db->query(
                'SELECT COALESCE(SUM(nilai), 0) FROM transaksi WHERE pengajuan_gu_id = ' . (int) $pengajuanId
            )->fetchColumn();
            $this->db->prepare('UPDATE pengajuan_gu SET total_nominal = :total WHERE id = :id')
                ->execute([':total' => $sisa, ':id' => $pengajuanId]);
            if (!empty($pengajuan['kas_bank_id'])) {
                $kas = $kasBank->getById((int) $pengajuan['kas_bank_id']);
                if ($kas) {
                    $kasBank->update(
                        (int) $kas['id'],
                        (string) $kas['tanggal'],
                        (string) $kas['jenis'],
                        $kas['nomor_bukti'] ?? null,
                        (string) $kas['keterangan'],
                        $sisa,
                        (string) $kas['status'],
                        $kas['tanggal_cair'] ?? null
                    );
                }
            }
            return true;
        } catch (\Throwable $e) {
            error_log('Error melepas transaksi dari pengajuan: ' . $e->getMessage());
            return false;
        }
    }
}
