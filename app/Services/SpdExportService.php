<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Service untuk dokumen SPD (Surat Perjalanan Dinas, PDF) — kelengkapan SPJ
 * perjalanan dinas, melengkapi Kuitansi & Rincian Biaya.
 *
 * Aturan otomatis (tanpa form input):
 * - Alat angkut : Kepala CDK => "Kendaraan Dinas", selain itu "Kendaraan Pribadi".
 * - Tingkat biaya: Kepala CDK => D, Kepala Seksi => E, selain itu => F.
 * - Pengikut    : dikosongkan.
 * - Akun        : kode rekening transaksi perjalanan dinas terkait
 *                 (Biasa 5.1.02.04.01.0001 / Dalam Kota 5.1.02.04.01.0003);
 *                 fallback ke Biasa bila tidak ada transaksi.
 */
class SpdExportService
{
    public const AKUN_DEFAULT = '5.1.02.04.01.0001';
    public const INSTANSI = 'Cabang Dinas Kehutanan Wilayah Bojonegoro';
    public const TEMPAT_BERANGKAT = 'Bojonegoro';

    /**
     * Logo kop: versi kecil (30KB) agar file unduhan tetap ringan.
     * Fallback ke logo asli bila file kecil belum ada.
     */
    private function logoPath(): string
    {
        $kecil = __DIR__ . '/../../public/images/logo_jatim_kecil.png';
        if (is_file($kecil)) {
            return $kecil;
        }
        return __DIR__ . '/../../public/images/logo_jatim.png';
    }

    // ──────────────────────────────────────────────────────────
    // DATA
    // ──────────────────────────────────────────────────────────

    /**
     * Susun data SPD untuk satu pegawai dalam satu Surat Tugas.
     * Return null bila ST/pegawai tidak ditemukan.
     */
    public function buildData(int $suratTugasId, string $nip): ?array
    {
        $cfg = __DIR__ . '/../../config/database_surat_tugas.php';
        if (!class_exists('DatabaseSuratTugas') && is_file($cfg)) {
            require_once $cfg;
        }
        if (!class_exists('DatabaseSuratTugas')) {
            return null;
        }
        $pdo = \DatabaseSuratTugas::getConnection();
        if ($pdo === null) {
            return null;
        }

        try {
            $stStmt = $pdo->prepare('
                SELECT id, nomor_surat, tanggal_surat, untuk,
                       tanggal_mulai, tanggal_selesai
                FROM surat_tugas WHERE id = ? LIMIT 1
            ');
            $stStmt->execute([$suratTugasId]);
            $st = $stStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$st) {
                return null;
            }

            $pegStmt = $pdo->prepare('
                SELECT COALESCE(p.nip, pt.nip) AS nip,
                       COALESCE(p.nama, pt.nip) AS nama,
                       p.pangkat, p.jabatan
                FROM pegawai_tugas pt
                LEFT JOIN pegawai p ON (
                    p.nip = pt.nip
                    OR REPLACE(p.nip, \' \', \'\') = REPLACE(pt.nip, \' \', \'\')
                )
                WHERE pt.id_surat_tugas = ?
                  AND (pt.nip = ? OR REPLACE(pt.nip, \' \', \'\') = REPLACE(?, \' \', \'\'))
                LIMIT 1
            ');
            $pegStmt->execute([$suratTugasId, $nip, $nip]);
            $peg = $pegStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$peg) {
                return null;
            }
        } catch (\Throwable $e) {
            error_log('SpdExportService::buildData ST error: ' . $e->getMessage());
            return null;
        }

        $jabatanFull = trim((string) ($peg['jabatan'] ?? ''));
        $isKepalaCdk = stripos($jabatanFull, 'Kepala Cabang') !== false
            || stripos($jabatanFull, 'Kepala CDK') !== false;
        $isKasie = stripos($jabatanFull, 'Kepala Seksi') !== false;

        // Cari transaksi perjalanan dinas terkait (untuk akun + lokasi tujuan).
        $akun = self::AKUN_DEFAULT;
        $lokasi = '';
        $trx = $this->findTransaksi($suratTugasId, (string) $st['nomor_surat'], (string) $peg['nip'], (string) $peg['nama']);
        if ($trx !== null) {
            if (!empty($trx['kode_rekening'])) {
                $akun = trim((string) $trx['kode_rekening']);
            }
            $lokasi = trim((string) ($trx['lokasi_kegiatan'] ?? ''));
        }

        $tglMulai = (string) $st['tanggal_mulai'];
        $tglSelesai = (string) $st['tanggal_selesai'] !== '' ? (string) $st['tanggal_selesai'] : $tglMulai;
        $lama = max(1, (int) round((strtotime($tglSelesai) - strtotime($tglMulai)) / 86400) + 1);

        $kpaNama = trim((string) ($_ENV['KPA_NAMA'] ?? getenv('KPA_NAMA') ?: 'Endang Handayani, S.P., M.Si'));
        $kpaNip = preg_replace('/[^0-9]/', '', (string) ($_ENV['KPA_NIP'] ?? getenv('KPA_NIP') ?: '197603282000032003'));

        return [
            'kpa_nama_full' => $kpaNama,
            'kpa_nip' => $kpaNip,
            'pegawai_nama' => trim((string) $peg['nama']),
            'pegawai_nip' => preg_replace('/[^0-9]/', '', (string) $peg['nip']),
            'pangkat' => $this->formatPangkat((string) ($peg['pangkat'] ?? '')),
            'jabatan' => $this->shortJabatan($jabatanFull),
            'tingkat' => $isKepalaCdk ? 'D' : ($isKasie ? 'E' : 'F'),
            'alat_angkut' => $isKepalaCdk ? 'Kendaraan Dinas' : 'Kendaraan Pribadi',
            'maksud' => trim((string) $st['untuk']),
            'tempat_berangkat' => self::TEMPAT_BERANGKAT,
            'tempat_tujuan' => $lokasi !== '' ? $lokasi : self::TEMPAT_BERANGKAT,
            'lama_hari' => $lama,
            'lama_text' => $lama . ' (' . strtolower($this->terbilangId($lama)) . ') hari',
            'tgl_berangkat' => $this->formatTanggalIndo($tglMulai),
            'tgl_kembali' => $this->formatTanggalIndo($tglSelesai),
            'tgl_dikeluarkan' => $this->formatTanggalIndo((string) ($st['tanggal_surat'] ?? $tglMulai)),
            'instansi' => self::INSTANSI,
            'akun' => $akun,
            'nomor_surat' => (string) $st['nomor_surat'],
        ];
    }

    /**
     * Susun data SPD langsung dari satu baris transaksi db_anggaran.
     * ST di-resolve via surat_tugas_ref_id, fallback cocokkan nomor_surat_tugas.
     * Return null bila ST/NIP tidak bisa di-resolve.
     */
    public function buildDataFromTransaksi(array $trx): ?array
    {
        $stId = (int) ($trx['surat_tugas_ref_id'] ?? 0);
        if ($stId <= 0) {
            $nomor = trim((string) ($trx['nomor_surat_tugas'] ?? ''));
            if ($nomor === '') {
                return null;
            }
            $found = $this->resolveStIdByNomor($nomor);
            if ($found === null) {
                return null;
            }
            $stId = $found;
        }
        $nip = trim((string) ($trx['pegawai_nip'] ?? ''));
        if ($nip === '' || $nip === '-') {
            return null;
        }
        return $this->buildData($stId, $nip);
    }

    /**
     * Cari ID Surat Tugas dari nomornya (cocok persis dulu, lalu LIKE).
     */
    public function resolveStIdByNomor(string $nomor): ?int
    {
        $cfg = __DIR__ . '/../../config/database_surat_tugas.php';
        if (!class_exists('DatabaseSuratTugas') && is_file($cfg)) {
            require_once $cfg;
        }
        if (!class_exists('DatabaseSuratTugas')) {
            return null;
        }
        $pdo = \DatabaseSuratTugas::getConnection();
        if ($pdo === null) {
            return null;
        }
        try {
            $stmt = $pdo->prepare('SELECT id FROM surat_tugas WHERE nomor_surat = ? LIMIT 1');
            $stmt->execute([$nomor]);
            $id = $stmt->fetchColumn();
            if ($id !== false) {
                return (int) $id;
            }
            $stmt = $pdo->prepare('SELECT id FROM surat_tugas WHERE nomor_surat LIKE ? ORDER BY id DESC LIMIT 1');
            $stmt->execute(['%' . $nomor . '%']);
            $id = $stmt->fetchColumn();
            return $id !== false ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Cari transaksi perjalanan dinas untuk (ST + pegawai) guna mengambil
     * kode rekening (akun) dan lokasi kegiatan (tempat tujuan).
     */
    private function findTransaksi(int $stId, string $nomorSurat, string $nip, string $nama): ?array
    {
        try {
            $cfg = __DIR__ . '/../../config/database.php';
            if (!class_exists('Database') && is_file($cfg)) {
                require_once $cfg;
            }
            if (!class_exists('Database')) {
                return null;
            }
            $db = \Database::getConnection();

            $sql = '
                SELECT t.lokasi_kegiatan, r.kode_rekening
                FROM transaksi t
                LEFT JOIN rekening r ON r.id = t.rekening_id
                WHERE t.jenis_transaksi = \'perjalanan_dinas\'
                  AND (t.surat_tugas_ref_id = :st
                       OR t.nomor_surat_tugas = :no)
                  AND (REPLACE(t.pegawai_nip, \' \', \'\') = REPLACE(:nip, \' \', \'\')
                       OR t.nama_penerima = :nama
                       OR t.nama_penerima LIKE :nama_like)
                ORDER BY t.id DESC LIMIT 1
            ';
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':st' => $stId,
                ':no' => $nomorSurat,
                ':nip' => $nip,
                ':nama' => $nama,
                ':nama_like' => '%' . $nama . '%',
            ]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * "Kepala Seksi Tata Kelola dan Usaha Kehutanan di Cabang Dinas ..."
     * => "Kepala Seksi TKUK". Jabatan tanpa embel "pada/di ..." dibiarkan utuh.
     */
    public function shortJabatan(string $jabatan): string
    {
        $jabatan = trim(preg_replace('/\s+/', ' ', $jabatan));
        if ($jabatan === '') {
            return '-';
        }
        $parts = preg_split('/\s+(?:pada|di)\s+/i', $jabatan, 2);
        $short = trim((string) ($parts[0] ?? $jabatan));
        $short = str_ireplace('Tata Kelola dan Usaha Kehutanan', 'TKUK', $short);
        return $short === '' ? $jabatan : $short;
    }

    /**
     * "Penata Tingkat I /III-d" => "Penata Tingkat I, III/d".
     */
    public function formatPangkat(string $pangkat): string
    {
        $pangkat = trim(preg_replace('/\s+/', ' ', $pangkat));
        if ($pangkat === '') {
            return '-';
        }
        $out = preg_replace('#\s*/\s*([IVX]+)\s*-\s*([a-z])#i', ', $1/$2', $pangkat);
        return $out ?? $pangkat;
    }

    public function formatTanggalIndo(?string $dateStr): string
    {
        if (!$dateStr) {
            return date('j F Y');
        }
        $t = strtotime($dateStr);
        if (!$t) {
            return $dateStr;
        }
        $bulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        return ((int) date('j', $t)) . ' ' . ($bulan[(int) date('n', $t)] ?? date('F', $t)) . ' ' . date('Y', $t);
    }

    private function terbilangId(int $n): string
    {
        if (function_exists('kuitansi_terbilang')) {
            return kuitansi_terbilang($n);
        }
        $satuan = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];
        if ($n < 12) {
            return $satuan[$n];
        }
        if ($n < 20) {
            return $this->terbilangId($n - 10) . ' Belas';
        }
        return $this->terbilangId((int) ($n / 10)) . ' Puluh' . ($n % 10 ? ' ' . $this->terbilangId($n % 10) : '');
    }

    // ──────────────────────────────────────────────────────────
    // PDF (FPDF)
    // ──────────────────────────────────────────────────────────

    public function downloadPdf(array $d): void
    {
        $pdf = $this->generatePdf($d);
        $filename = 'SPD_' . $this->safeName($d['pegawai_nama']) . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $pdf->Output('D', $filename);
        exit;
    }

    /**
     * Render dokumen SPD ke objek FPDF (A4 portrait). Dipisah dari
     * downloadPdf() agar bisa diuji tanpa header HTTP / exit.
     */
    public function generatePdf(array $d): \FPDF
    {
        require_once __DIR__ . '/../../lib/fpdf.php';
        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();
        $W = 180; // lebar area cetak (210 - 2x15)
        $txt = fn(string $s): string => $this->pdfText($s);

        // ── Kop ──
        $logo = $this->logoPath();
        if (is_file($logo)) {
            // Lebar 15mm (tinggi ±21mm) agar pas di dalam blok kop,
            // tidak melewati garis ganda di bawahnya.
            $pdf->Image($logo, 15, 10, 15);
        }
        $pdf->SetFont('Times', 'B', 11);
        $pdf->SetY(10);
        $pdf->Cell($W, 5, $txt('PEMERINTAH PROVINSI JAWA TIMUR'), 0, 1, 'C');
        $pdf->Cell($W, 5, $txt('DINAS KEHUTANAN'), 0, 1, 'C');
        $pdf->SetFont('Times', 'B', 14);
        $pdf->Cell($W, 6, $txt('CABANG DINAS WILAYAH BOJONEGORO'), 0, 1, 'C');
        $pdf->SetFont('Times', '', 8);
        $pdf->Cell($W, 4, $txt('Jalan Hayamwuruk Nomor 9, Karangpacar, Bojonegoro, Jawa Timur 62119'), 0, 1, 'C');
        $pdf->Cell($W, 4, $txt('Tlp./Fak. (0353) 2898161, Pos-el cdkwilayahbojonegoro@jatimprov.go.id'), 0, 1, 'C');
        $y = $pdf->GetY() + 1;
        $pdf->SetLineWidth(0.6);
        $pdf->Line(15, $y, 195, $y);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(15, $y + 1, 195, $y + 1);
        $pdf->SetY($y + 5);

        // ── Lembar Ke / Kode Nomor ──
        $pdf->SetFont('Times', '', 11);
        $pdf->Cell(90, 6, '', 0, 0);
        $pdf->Cell(30, 6, $txt('Lembar Ke'), 0, 0);
        $pdf->Cell(60, 6, $txt(':  Satu'), 0, 1);
        $pdf->Cell(90, 6, '', 0, 0);
        $pdf->Cell(30, 6, $txt('Kode Nomor'), 0, 0);
        $pdf->Cell(60, 6, $txt(':  1'), 0, 1);
        $pdf->Ln(2);

        // ── Judul ──
        $pdf->SetFont('Times', 'BU', 12);
        $pdf->Cell($W, 7, $txt('SURAT PERJALANAN DINAS (SPD)'), 0, 1, 'C');
        $pdf->Ln(2);

        // ── Tabel ──
        $wNo = 10;
        $wUraian = 68;
        $wIsi = $W - $wNo - $wUraian; // 102
        $lh = 5.5;
        $pdf->SetFont('Times', '', 11);

        $row = function (string $no, string $uraian, string $isi) use ($pdf, $txt, $wNo, $wUraian, $wIsi, $lh): void {
            $h = $this->pdfRowHeight($pdf, [[$wNo, $no], [$wUraian, $uraian], [$wIsi, $isi]], $lh);
            $this->pdfCheckBreak($pdf, $h);
            $x0 = $pdf->GetX();
            $y0 = $pdf->GetY();
            foreach ([[$wNo, $no, 'C'], [$wUraian, $uraian, 'L'], [$wIsi, $isi, 'L']] as [$w, $t, $a]) {
                $x = $pdf->GetX();
                $pdf->Rect($x, $y0, $w, $h);
                $pdf->SetXY($x + 1, $y0 + 1);
                $pdf->MultiCell($w - 2, $lh, $txt($t), 0, $a);
                $pdf->SetXY($x + $w, $y0);
            }
            $pdf->SetXY($x0, $y0 + $h);
        };

        $row('1', 'Kuasa Pengguna Anggaran', $d['kpa_nama_full']);
        $row('2', "Nama/NIP Pegawai Yang Melaksanakan\nPerjalanan Dinas", $d['pegawai_nama'] . "\nNIP " . $d['pegawai_nip']);
        $row(
            '3',
            "a. Pangkat dan Golongan\nb. Jabatan/Instansi\nc. Tingkat Biaya Perjalanan Dinas",
            "a. " . $d['pangkat'] . "\nb. " . $d['jabatan'] . "\nc. " . $d['tingkat']
        );
        $row('4', 'Maksud Perjalanan Dinas', $d['maksud']);
        $row('5', 'Alat Angkut Yang Dipergunakan', $d['alat_angkut']);
        $row(
            '6',
            "a. Tempat Berangkat\nb. Tempat Tujuan",
            "a. " . $d['tempat_berangkat'] . "\nb. " . $d['tempat_tujuan']
        );
        $row(
            '7',
            "a. Lama Perjalanan Dinas\nb. Tanggal Berangkat\nc. Tanggal Harus Kembali/Tiba Ditempat Baru",
            "a. " . $d['lama_text'] . "\nb. " . $d['tgl_berangkat'] . "\nc. " . $d['tgl_kembali']
        );

        // Baris 8 — Pengikut (isi dipecah dua kolom)
        $wTgl = $wIsi / 2;
        $wKet = $wIsi - $wTgl;
        $h8 = $this->pdfRowHeight($pdf, [[$wNo, '8'], [$wUraian, 'Pengikut :          Nama'], [$wTgl, 'Tanggal Lahir'], [$wKet, 'Keterangan']], $lh);
        $this->pdfCheckBreak($pdf, $h8);
        $x0 = $pdf->GetX();
        $y0 = $pdf->GetY();
        foreach ([[$wNo, '8', 'C'], [$wUraian, 'Pengikut :          Nama', 'L'], [$wTgl, 'Tanggal Lahir', 'C'], [$wKet, 'Keterangan', 'C']] as [$w, $t, $a]) {
            $x = $pdf->GetX();
            $pdf->Rect($x, $y0, $w, $h8);
            $pdf->SetXY($x + 1, $y0 + 1);
            $pdf->MultiCell($w - 2, $lh, $txt($t), 0, $a);
            $pdf->SetXY($x + $w, $y0);
        }
        $pdf->SetXY($x0, $y0 + $h8);
        $h8b = $lh * 3 + 2;
        $this->pdfCheckBreak($pdf, $h8b);
        $x0 = $pdf->GetX();
        $y0 = $pdf->GetY();
        foreach ([[$wNo, '', 'C'], [$wUraian, "1.\n\n2.", 'L'], [$wTgl, '', 'C'], [$wKet, '', 'C']] as [$w, $t, $a]) {
            $x = $pdf->GetX();
            $pdf->Rect($x, $y0, $w, $h8b);
            $pdf->SetXY($x + 1, $y0 + 1);
            $pdf->MultiCell($w - 2, $lh, $txt($t), 0, $a);
            $pdf->SetXY($x + $w, $y0);
        }
        $pdf->SetXY($x0, $y0 + $h8b);

        $row('9', "Pembebanan Anggaran\n\na. Instansi\nb. Akun", "\n\na. " . $d['instansi'] . "\nb. " . $d['akun']);
        $row('10', 'Keterangan Lain-lain', ' ');
        $pdf->Ln(3);

        // ── Footer (blok kanan) ──
        $pdf->SetX(15 + $wNo + $wUraian);
        $pdf->Cell($wIsi, 6, $txt('Dikeluarkan di         : Bojonegoro'), 0, 1);
        $pdf->SetX(15 + $wNo + $wUraian);
        $pdf->Cell($wIsi, 6, $txt('Tanggal                  : ' . $d['tgl_dikeluarkan']), 0, 1);
        $pdf->Ln(2);
        $pdf->SetX(15 + $wNo + $wUraian);
        $pdf->Cell($wIsi, 6, $txt('Kuasa Pengguna Anggaran'), 0, 1);
        $pdf->Ln(14);
        $pdf->SetX(15 + $wNo + $wUraian);
        $pdf->SetFont('Times', 'U', 11);
        $pdf->Cell($wIsi, 6, $txt($d['kpa_nama_full']), 0, 1);
        $pdf->SetX(15 + $wNo + $wUraian);
        $pdf->SetFont('Times', '', 11);
        $pdf->Cell($wIsi, 6, $txt('NIP ' . $d['kpa_nip']), 0, 1);

        return $pdf;
    }

    // ── Helper FPDF ──

    private function pdfText(string $s): string
    {
        if (function_exists('kuitansi_pdf_text')) {
            return kuitansi_pdf_text($s);
        }
        $out = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
        return $out === false ? $s : $out;
    }

    /** Hitung jumlah baris wrap untuk lebar kolom tertentu (font aktif). */
    private function pdfNbLines(\FPDF $pdf, float $w, string $text): int
    {
        $maxW = $w - 2;
        if ($maxW <= 0) {
            return 1;
        }
        $spaceW = $pdf->GetStringWidth(' ');
        $lines = 0;
        foreach (explode("\n", $text) as $para) {
            if (trim($para) === '') {
                $lines++;
                continue;
            }
            $cur = 0.0;
            $extra = 0;
            foreach (preg_split('/\s+/', trim($para)) as $word) {
                $ww = $pdf->GetStringWidth($word);
                if ($cur > 0 && $cur + $spaceW + $ww > $maxW) {
                    $extra++;
                    $cur = $ww;
                } else {
                    $cur += ($cur > 0 ? $spaceW : 0) + $ww;
                }
            }
            $lines += $extra + 1;
        }
        return max(1, $lines);
    }

    /** @param array<int, array{0:float,1:string}> $cells */
    private function pdfRowHeight(\FPDF $pdf, array $cells, float $lh): float
    {
        $max = 1;
        foreach ($cells as [$w, $t]) {
            $max = max($max, $this->pdfNbLines($pdf, $w, $this->pdfText($t)));
        }
        return $max * $lh + 2;
    }

    private function pdfCheckBreak(\FPDF $pdf, float $h): void
    {
        // Margin bawah 15mm (lihat SetAutoPageBreak(true, 15)).
        if ($pdf->GetY() + $h > $pdf->GetPageHeight() - 15) {
            $pdf->AddPage();
        }
    }

    private function safeName(string $nama): string
    {
        $safe = preg_replace('/[^A-Za-z0-9]+/', '_', trim($nama));
        $safe = trim((string) $safe, '_');
        return $safe !== '' ? $safe : 'Pegawai';
    }
}
