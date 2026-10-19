<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Transaksi;
use PDO;

/**
 * Menyusun Laporan Pertanggungjawaban Limpahan Uang Persediaan (LPJ UP).
 *
 * Sumber data:
 * - Belanja periode ini : transaksi UP terverifikasi yang sudah diajukan
 *   ke SPJ GU (pengajuan_gu_id terisi), dibatasi pengajuan/periode.
 * - Akumulasi belanja   : seluruh transaksi terverifikasi (UP/LS) s.d. cutoff.
 * - Jumlah anggaran     : pagu rekening pada tahun anggaran terkait.
 * - Sisa anggaran       : jumlah anggaran - akumulasi belanja.
 */
class LpjLimpahanUpService
{
    private const TGL_EFEKTIF = 'COALESCE(t.tanggal_lunas_dibayar, DATE(t.diverifikasi_at), t.tanggal)';

    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array<string,mixed>|null Null bila pengajuan_id tidak ditemukan.
     */
    public function build(
        ?int $pengajuanId = null,
        ?string $dari = null,
        ?string $sampai = null,
        ?int $tahun = null
    ): ?array {
        $dari = Transaksi::normalizeTanggalLunas($dari ?? '');
        $sampai = Transaksi::normalizeTanggalLunas($sampai ?? '');
        if ($dari !== null && $sampai !== null && $dari > $sampai) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        $pengajuan = null;
        $conds = [
            "t.sumber_dana = 'UP'",
            "t.status = 'diverifikasi'",
        ];
        $params = [];

        if ($pengajuanId !== null) {
            $stmt = $this->db->prepare('SELECT * FROM pengajuan_gu WHERE id = :id');
            $stmt->execute([':id' => $pengajuanId]);
            $pengajuan = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($pengajuan === null) {
                return null;
            }

            $conds[] = 't.pengajuan_gu_id = :pengajuan_id';
            $params[':pengajuan_id'] = $pengajuanId;

            if ($tahun === null) {
                $tglPengajuan = (string) ($pengajuan['tanggal_pengajuan'] ?? '');
                $tahun = (int) date('Y', strtotime($tglPengajuan !== '' ? $tglPengajuan : 'now'));
            }
        } else {
            $conds[] = 't.pengajuan_gu_id IS NOT NULL';
            if ($dari !== null) {
                $conds[] = self::TGL_EFEKTIF . ' >= :dari';
                $params[':dari'] = $dari;
            }
            if ($sampai !== null) {
                $conds[] = self::TGL_EFEKTIF . ' <= :sampai';
                $params[':sampai'] = $sampai;
            }
            if ($dari === null && $sampai === null) {
                $conds[] = 'YEAR(' . self::TGL_EFEKTIF . ') = :tahun_scope';
            }

            if ($tahun === null) {
                $tahun = (int) date('Y', strtotime($dari ?? $sampai ?? 'now'));
            }
            if ($dari === null && $sampai === null) {
                $params[':tahun_scope'] = $tahun;
            }
        }

        $sql = "
            SELECT t.id, t.rekening_id, t.nilai,
                " . self::TGL_EFEKTIF . " AS tanggal_efektif,
                r.kode_rekening, r.nama_rekening,
                sk.id AS sub_kegiatan_id, sk.kode_sub_kegiatan, sk.nama_sub_kegiatan,
                k.id AS kegiatan_id, k.kode_kegiatan, k.nama_kegiatan,
                p.id AS program_id, p.kode_program, p.nama_program
            FROM transaksi t
            INNER JOIN rekening r ON r.id = t.rekening_id
            INNER JOIN sub_kegiatan sk ON sk.id = r.sub_kegiatan_id
            INNER JOIN kegiatan k ON k.id = sk.kegiatan_id
            INNER JOIN program p ON p.id = k.program_id
            WHERE " . implode(' AND ', $conds) . "
            ORDER BY p.kode_program ASC, k.kode_kegiatan ASC,
                sk.kode_sub_kegiatan ASC, r.kode_rekening ASC,
                tanggal_efektif ASC, t.id ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cutoff = null;
        foreach ($items as $item) {
            $tgl = (string) ($item['tanggal_efektif'] ?? '');
            if ($tgl !== '' && ($cutoff === null || $tgl > $cutoff)) {
                $cutoff = $tgl;
            }
        }

        if ($pengajuan !== null) {
            $tglPengajuan = (string) ($pengajuan['tanggal_pengajuan'] ?? '');
            if ($tglPengajuan !== '' && ($cutoff === null || $tglPengajuan > $cutoff)) {
                $cutoff = $tglPengajuan;
            }
        } elseif ($sampai !== null && ($cutoff === null || $sampai > $cutoff)) {
            $cutoff = $sampai;
        }
        if ($cutoff === null) {
            $cutoff = $sampai ?? date('Y-m-d');
        }

        $paguMap = $this->paguByRekening((int) $tahun);
        $akumulasiMap = $this->akumulasiByRekening((int) $tahun, $cutoff);

        $rekeningMap = [];
        foreach ($items as $item) {
            $rid = (int) $item['rekening_id'];
            if (!isset($rekeningMap[$rid])) {
                $rekeningMap[$rid] = [
                    'type' => 'rekening',
                    'rekening_id' => $rid,
                    'kode' => (string) ($item['kode_rekening'] ?? ''),
                    'nama' => (string) ($item['nama_rekening'] ?? ''),
                    'kode_program' => (string) ($item['kode_program'] ?? ''),
                    'nama_program' => (string) ($item['nama_program'] ?? ''),
                    'kode_kegiatan' => (string) ($item['kode_kegiatan'] ?? ''),
                    'nama_kegiatan' => (string) ($item['nama_kegiatan'] ?? ''),
                    'kode_sub_kegiatan' => (string) ($item['kode_sub_kegiatan'] ?? ''),
                    'nama_sub_kegiatan' => (string) ($item['nama_sub_kegiatan'] ?? ''),
                    'pagu' => (float) ($paguMap[$rid] ?? 0),
                    'periode' => 0.0,
                    'akumulasi' => (float) ($akumulasiMap[$rid] ?? 0),
                    'sisa' => 0.0,
                ];
            }
            $rekeningMap[$rid]['periode'] += (float) ($item['nilai'] ?? 0);
        }

        foreach ($rekeningMap as &$rekening) {
            $rekening['sisa'] = $rekening['pagu'] - $rekening['akumulasi'];
        }
        unset($rekening);

        $totals = ['pagu' => 0.0, 'periode' => 0.0, 'akumulasi' => 0.0, 'sisa' => 0.0];
        foreach ($rekeningMap as $rekening) {
            $totals['pagu'] += $rekening['pagu'];
            $totals['periode'] += $rekening['periode'];
            $totals['akumulasi'] += $rekening['akumulasi'];
            $totals['sisa'] += $rekening['sisa'];
        }

        $rows = $this->flattenTree($this->buildTree($rekeningMap));

        $periodeLabel = 'Seluruh periode';
        if ($pengajuan !== null) {
            $periodeLabel = 'Pengajuan ' . (string) ($pengajuan['nomor_pengajuan'] ?? ('#' . $pengajuanId));
        } elseif ($dari !== null || $sampai !== null) {
            $periodeLabel = ($dari !== null ? $this->formatTanggal($dari) : 'Awal')
                . ' s.d. ' . ($sampai !== null ? $this->formatTanggal($sampai) : 'Akhir');
        }

        return [
            'judul' => 'LAPORAN PERTANGGUNGJAWABAN LIMPAHAN UANG PERSEDIAAN',
            'meta' => [
                'pengajuan' => $pengajuan,
                'nomor_pengajuan' => $pengajuan['nomor_pengajuan'] ?? null,
                'tanggal_pengajuan' => $pengajuan['tanggal_pengajuan'] ?? null,
                'status_pengajuan' => $pengajuan['status'] ?? null,
                'keterangan' => $pengajuan['keterangan'] ?? null,
                'periode_label' => $periodeLabel,
                'tahun' => (int) $tahun,
                'cutoff' => $cutoff,
                'dari' => $dari,
                'sampai' => $sampai,
                'jumlah_transaksi' => count($items),
                'jumlah_rekening' => count($rekeningMap),
            ],
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /**
     * @return array<int,float>
     */
    private function paguByRekening(int $tahun): array
    {
        $stmt = $this->db->prepare("
            SELECT rekening_id, COALESCE(SUM(nilai_pagu), 0) AS total
            FROM pagu
            WHERE tahun = :tahun
            GROUP BY rekening_id
        ");
        $stmt->execute([':tahun' => $tahun]);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['rekening_id']] = (float) $row['total'];
        }

        return $map;
    }

    /**
     * @return array<int,float>
     */
    private function akumulasiByRekening(int $tahun, string $cutoff): array
    {
        $stmt = $this->db->prepare("
            SELECT t.rekening_id, COALESCE(SUM(t.nilai), 0) AS total
            FROM transaksi t
            WHERE t.status = 'diverifikasi'
              AND YEAR(" . self::TGL_EFEKTIF . ") = :tahun
              AND " . self::TGL_EFEKTIF . " <= :cutoff
            GROUP BY t.rekening_id
        ");
        $stmt->execute([':tahun' => $tahun, ':cutoff' => $cutoff]);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['rekening_id']] = (float) $row['total'];
        }

        return $map;
    }

    /**
     * @param array<int,array<string,mixed>> $rekeningMap
     * @return array<string,mixed>
     */
    private function buildTree(array $rekeningMap): array
    {
        $tree = [];

        foreach ($rekeningMap as $rekening) {
            $programParts = explode('.', $rekening['kode_program']);
            $kodeUrusan = $programParts[0] ?? '';
            $kodeBidang = isset($programParts[1])
                ? $programParts[0] . '.' . $programParts[1]
                : $kodeUrusan;

            if (!isset($tree[$kodeUrusan])) {
                $tree[$kodeUrusan] = [
                    'type' => 'urusan',
                    'kode' => $kodeUrusan,
                    'nama' => $this->namaUrusan($kodeUrusan),
                    'children' => [],
                ];
            }
            if (!isset($tree[$kodeUrusan]['children'][$kodeBidang])) {
                $tree[$kodeUrusan]['children'][$kodeBidang] = [
                    'type' => 'bidang',
                    'kode' => $kodeBidang,
                    'nama' => $this->namaBidang($kodeBidang),
                    'children' => [],
                ];
            }

            $programKey = $rekening['kode_program'];
            if (!isset($tree[$kodeUrusan]['children'][$kodeBidang]['children'][$programKey])) {
                $tree[$kodeUrusan]['children'][$kodeBidang]['children'][$programKey] = [
                    'type' => 'program',
                    'kode' => $programKey,
                    'nama' => $rekening['nama_program'],
                    'children' => [],
                ];
            }

            $kegiatanKey = $rekening['kode_kegiatan'];
            if (!isset($tree[$kodeUrusan]['children'][$kodeBidang]['children'][$programKey]['children'][$kegiatanKey])) {
                $tree[$kodeUrusan]['children'][$kodeBidang]['children'][$programKey]['children'][$kegiatanKey] = [
                    'type' => 'kegiatan',
                    'kode' => $kegiatanKey,
                    'nama' => $rekening['nama_kegiatan'],
                    'children' => [],
                ];
            }

            $subKey = $rekening['kode_sub_kegiatan'];
            if (!isset($tree[$kodeUrusan]['children'][$kodeBidang]['children'][$programKey]['children'][$kegiatanKey]['children'][$subKey])) {
                $tree[$kodeUrusan]['children'][$kodeBidang]['children'][$programKey]['children'][$kegiatanKey]['children'][$subKey] = [
                    'type' => 'sub_kegiatan',
                    'kode' => $subKey,
                    'nama' => $rekening['nama_sub_kegiatan'],
                    'children' => [],
                ];
            }

            $tree[$kodeUrusan]['children'][$kodeBidang]['children'][$programKey]['children'][$kegiatanKey]['children'][$subKey]['children'][$rekening['rekening_id']] = $rekening;
        }

        $this->sortTree($tree);

        return $tree;
    }

    /**
     * @param array<string|int,mixed> $tree
     */
    private function sortTree(array &$tree): void
    {
        uksort($tree, static fn($a, $b): int => strnatcasecmp((string) $a, (string) $b));
        foreach ($tree as &$node) {
            if (isset($node['children']) && is_array($node['children'])) {
                $this->sortTree($node['children']);
            }
        }
        unset($node);
    }

    /**
     * @param array<string,mixed> $tree
     * @return array<int,array<string,mixed>>
     */
    private function flattenTree(array $tree): array
    {
        $rows = [];
        foreach ($tree as $node) {
            $children = $node['children'] ?? [];
            unset($node['children']);
            $rows[] = $node;
            if (!empty($children)) {
                array_push($rows, ...$this->flattenTree($children));
            }
        }

        return $rows;
    }

    private function namaUrusan(string $kode): string
    {
        $map = [
            '1' => 'URUSAN PEMERINTAHAN WAJIB',
            '2' => 'URUSAN PEMERINTAHAN WAJIB',
            '3' => 'URUSAN PEMERINTAHAN PILIHAN',
            '4' => 'URUSAN PEMERINTAHAN PENDUKUNG',
        ];

        return $map[$kode] ?? ('URUSAN PEMERINTAHAN ' . $kode);
    }

    private function namaBidang(string $kode): string
    {
        $map = [
            '3.28' => 'URUSAN PEMERINTAHAN BIDANG KEHUTANAN',
        ];

        if (isset($map[$kode])) {
            return $map[$kode];
        }

        return 'URUSAN PEMERINTAHAN BIDANG ' . $kode;
    }

    private function formatTanggal(string $tanggal): string
    {
        $time = strtotime($tanggal);

        return $time !== false ? date('d/m/Y', $time) : $tanggal;
    }
}
