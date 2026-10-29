<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Service PDF: RINCIAN BIAYA PERJALANAN DINAS & PERHITUNGAN SPPD RAMPUNG.
 *
 * Layout mengikuti contoh cetak resmi CDK Bojonegoro (A4 portrait, 1 halaman).
 * Output berupa pratinjau PDF (inline) sehingga bisa langsung dicetak dari tab baru.
 */
class RincianBiayaExportService
{
    private const PAGE_W = 210.0;
    private const LEFT = 13.0;
    private const RIGHT = 197.08;
    private const TABLE_TOP = 43.8;
    private const HEADER_BOTTOM = 48.64;
    private const ROW_H = 9.6;

    private const X_NO = 13.0;
    private const X_PERINCIAN = 22.44;
    private const X_HARI = 103.76;
    private const X_JUMLAH = 116.0;
    private const X_KETERANGAN = 147.96;

    private const W_NO = 9.44;
    private const W_PERINCIAN = 81.32;
    private const W_HARI = 12.24;
    private const W_JUMLAH = 31.96;
    private const W_KETERANGAN = 49.12;

    /**
     * Tampilkan PDF rincian biaya langsung di browser (preview/print).
     */
    public function streamPdf(array $header, array $details, array $transaksi = []): void
    {
        $pdf = $this->generatePdf($header, $details, $transaksi);

        $pegawaiNama = $header['pegawai_nama'] ?? ($transaksi['nama_penerima'] ?? 'Pegawai');
        $safeName = preg_replace('/[^A-Za-z0-9]+/', '_', (string) $pegawaiNama);
        $safeName = trim((string) $safeName, '_');
        if ($safeName === '') {
            $safeName = 'Pegawai';
        }
        $filename = 'Rincian_Biaya_' . $safeName . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $pdf->Output('I', $filename);
        exit;
    }

    /**
     * Alias lama agar pemanggil lama tetap aman: sekarang menghasilkan PDF,
     * bukan lagi mengunduh Excel.
     */
    public function download(array $header, array $details, array $transaksi = []): void
    {
        $this->streamPdf($header, $details, $transaksi);
    }

    /**
     * Render dokumen ke objek FPDF (tanpa header HTTP/exit) agar bisa diuji.
     */
    public function generatePdf(array $header, array $details, array $transaksi = []): \FPDF
    {
        require_once __DIR__ . '/../../lib/fpdf.php';
        require_once __DIR__ . '/../../config/terbilang.php';

        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->SetMargins(self::LEFT, 10, self::PAGE_W - self::RIGHT);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();
        $pdf->SetLineWidth(0.3);

        $nomorSurat = $this->resolveNomorSurat($header, $transaksi);
        $tglRaw = $transaksi['tanggal_surat_tugas']
            ?? ($header['tanggal_surat'] ?? ($transaksi['tanggal'] ?? date('Y-m-d')));
        $tglFormatted = $this->formatTanggalIndo((string) $tglRaw);

        // Judul
        $this->put($pdf, 0, 19.8, self::PAGE_W, 6, 'RINCIAN BIAYA PERJALANAN DINAS', 'C', '', 11.5);

        // Metadata SPT
        $this->put($pdf, 13.9, 30.35, 56, 5, 'Lampiran SPT Nomor', 'L', '', 10.5);
        $this->put($pdf, 71.4, 30.35, 4, 5, ':', 'C', '', 10.5);
        $this->put($pdf, 75.9, 30.35, 122, 5, $nomorSurat, 'L', '', 10.5, true);

        $this->put($pdf, 13.9, 35.1, 56, 5, 'Tanggal', 'L', '', 10.5);
        $this->put($pdf, 71.4, 35.1, 4, 5, ':', 'C', '', 10.5);
        $this->put($pdf, 75.9, 35.1, 122, 5, $tglFormatted, 'L', '', 10.5);

        $details = $this->normaliseDetails($details);
        $totalBiaya = 0.0;
        foreach ($details as $d) {
            $totalBiaya += (float) ($d['jumlah'] ?? 0);
        }

        $rowCount = count($details);
        $rowH = self::ROW_H;
        if ($rowCount > 8) {
            // Jaga agar blok tanda tangan tetap muat dalam satu halaman.
            $rowH = max(6.5, (140.0 - self::HEADER_BOTTOM) / max(1, $rowCount));
        }
        $dataBottom = self::HEADER_BOTTOM + ($rowCount * $rowH);
        $totalY = max(96.4, $dataBottom + 4.0);
        $totalRowH = 5.8;
        $terbilangY = $totalY + $totalRowH;
        $tableBottom = $terbilangY + 5.2;

        // Header tabel
        $headerY = 44.5;
        $this->put($pdf, self::X_NO, $headerY, self::W_NO, 4.84, 'No.', 'C', '', 10.5);
        $this->put($pdf, self::X_PERINCIAN, $headerY, self::W_PERINCIAN, 4.84, 'PERINCIAN BIAYA', 'C', '', 10.5);
        $this->put($pdf, self::X_HARI, $headerY, self::W_HARI, 4.84, 'Hari', 'C', '', 10.5);
        $this->put($pdf, self::X_JUMLAH, $headerY, self::W_JUMLAH, 4.84, 'JUMLAH (Rp)', 'C', '', 10.5);
        $this->put($pdf, self::X_KETERANGAN, $headerY, self::W_KETERANGAN, 4.84, 'KETERANGAN', 'C', '', 10.5);

        // Baris rincian
        foreach ($details as $idx => $d) {
            $rowTop = self::HEADER_BOTTOM + ($idx * $rowH);
            $rowTextY = $rowTop + 3.2;
            $namaKomponen = trim((string) ($d['nama_komponen'] ?? ''));
            $hargaSatuan = (float) ($d['harga_satuan'] ?? 0);
            $jumlahHari = $d['jumlah_hari'] !== null && $d['jumlah_hari'] !== ''
                ? (float) $d['jumlah_hari']
                : null;
            if ($jumlahHari !== null && stripos($namaKomponen, 'hotel') !== false) {
                $jumlahHari = null;
            }
            $jumlah = (float) ($d['jumlah'] ?? 0);
            $keterangan = trim((string) ($d['keterangan'] ?? ''));

            $rateText = $this->rateText($hargaSatuan, $jumlahHari);
            $rateWidth = $pdf->GetStringWidth($this->pdfText($rateText));
            $nameWidth = max(20.0, self::W_PERINCIAN - 4.0 - $rateWidth - 3.0);

            $this->put($pdf, self::X_NO, $rowTextY, self::W_NO, $rowH, (string) ($idx + 1), 'C', '', 10.5);
            $this->put($pdf, self::X_PERINCIAN + 2.0, $rowTextY, $nameWidth, $rowH, $namaKomponen, 'L', '', 10.5, true);
            $this->put($pdf, self::X_PERINCIAN + 2.0, $rowTextY, self::W_PERINCIAN - 4.0, $rowH, $rateText, 'R', '', 10.5, true);
            $this->put($pdf, self::X_HARI, $rowTextY, self::W_HARI, $rowH, $this->dayText($jumlahHari), 'C', '', 10.5);
            if ($jumlah > 0) {
                $this->put($pdf, self::X_JUMLAH + 2.0, $rowTextY, 5, $rowH, 'Rp', 'L', '', 10.5);
                $this->put(
                    $pdf,
                    self::X_JUMLAH + 8.0,
                    $rowTextY,
                    self::W_JUMLAH - 10.0,
                    $rowH,
                    $this->rupiah($jumlah),
                    'R',
                    '',
                    10.5,
                    true
                );
            }
            $this->put($pdf, self::X_KETERANGAN + 2.0, $rowTextY, self::W_KETERANGAN - 4.0, $rowH, $keterangan, 'L', '', 10.5, true);
        }

        // Baris total
        $totalTextY = $totalY + 0.6;
        $this->put($pdf, self::X_PERINCIAN, $totalTextY, self::W_PERINCIAN, $totalRowH, 'JUMLAH', 'C', '', 10.5);
        $this->put($pdf, self::X_JUMLAH + 2.0, $totalTextY, 5, $totalRowH, 'Rp', 'L', '', 10.5);
        $this->put($pdf, self::X_JUMLAH + 8.0, $totalTextY, self::W_JUMLAH - 10.0, $totalRowH, $this->rupiah($totalBiaya), 'R', '', 10.5, true);

        // Terbilang
        $terbilangTextY = $terbilangY + 0.6;
        $this->put($pdf, self::X_PERINCIAN + 1.0, $terbilangTextY, 30, 5.2, 'Terbilang', 'L', '', 10.5);
        $terbilangText = ' ' . $this->terbilang($totalBiaya) . ' Rupiah';
        $this->put($pdf, self::X_HARI + 3.0, $terbilangTextY, self::RIGHT - (self::X_HARI + 3.0), 5.2, $terbilangText, 'L', 'I', 9.7, true);

        // Garis tabel
        $this->line($pdf, self::X_NO, self::TABLE_TOP, self::RIGHT, self::TABLE_TOP);
        $this->line($pdf, self::X_NO, self::HEADER_BOTTOM, self::RIGHT, self::HEADER_BOTTOM);
        $this->line($pdf, self::X_NO, $totalY, self::RIGHT, $totalY);
        $this->line($pdf, self::X_NO, $terbilangY, self::RIGHT, $terbilangY);
        $this->line($pdf, self::X_NO, $tableBottom, self::RIGHT, $tableBottom);

        foreach ([self::X_NO, self::X_PERINCIAN, self::X_HARI, self::X_JUMLAH, self::X_KETERANGAN, self::RIGHT] as $x) {
            $bottom = ($x === self::X_NO || $x === self::RIGHT) ? $tableBottom : $terbilangY;
            $this->line($pdf, $x, self::TABLE_TOP, $x, $bottom);
        }

        // Blok tanda tangan
        $bendaharaNama = $this->env('BENDAHARA_NAMA', 'ADHITO NUGROHO, S.Kom.');
        $bendaharaNip = $this->env('BENDAHARA_NIP', '19840214 201001 1 011');
        $kpaNama = $this->env('KPA_NAMA', 'ENDANG HANDAYANI, S.P., M.Si.');
        $kpaNip = $this->env('KPA_NIP', '19760328 200003 2 003');

        $penerimaNama = trim((string) ($header['pegawai_nama'] ?? ($transaksi['nama_penerima'] ?? '-')));
        $penerimaNip = trim((string) ($header['pegawai_nip'] ?? ($transaksi['pegawai_nip'] ?? '-')));
        if ($penerimaNama === '') {
            $penerimaNama = '-';
        }

        $tempatTanggal = trim((string) ($header['tempat_tanggal'] ?? ''));
        if ($tempatTanggal === '') {
            $tempatTanggal = 'Bojonegoro,';
        }

        $sigY = max(111.3, $tableBottom + 3.9);
        $sigTextY = $sigY + 0.4;
        $leftX = 14.7;
        $rightX = 124.8;
        $blockW = 64.0;
        // Blok kanan dilebarkan sampai tepi nominal (195.2) agar nama panjang tidak terpotong.
        $rightBlockW = 195.2 - $rightX;

        $this->put($pdf, 102.3, $sigTextY, 70.0, 5.0, $tempatTanggal, 'C', '', 10.5, true);
        $this->put($pdf, $leftX, $sigTextY + 4.8, $blockW, 5.0, 'Telah dibayar sejumlah', 'C', '', 10.5, true);
        $this->put($pdf, $rightX, $sigTextY + 4.8, $rightBlockW, 5.0, 'Telah menerima uang sejumlah', 'C', '', 10.5, true);

        $this->amountRow($pdf, 24.6, 68.7, $totalBiaya, $sigTextY + 14.5);
        $this->amountRow($pdf, 150.1, 195.2, $totalBiaya, $sigTextY + 14.5);

        $this->put($pdf, $leftX, $sigTextY + 24.1, $blockW, 5.0, 'Bendahara Pengeluaran Pembantu', 'L', '', 10.5, true);
        $this->put($pdf, $rightX, $sigTextY + 24.1, $rightBlockW, 5.0, 'Yang Menerima,', 'L', '', 10.5, true);

        $this->putFit($pdf, $leftX, $sigTextY + 43.2, $blockW, 5.0, $bendaharaNama, 'L', 'U', 10.5);
        $this->putFit($pdf, $rightX, $sigTextY + 43.2, $rightBlockW, 5.0, $penerimaNama, 'L', 'U', 10.5);
        $this->put($pdf, $leftX, $sigTextY + 48.0, $blockW, 5.0, 'NIP. ' . $this->formatNip($bendaharaNip), 'L', '', 10.5, true);
        $this->put($pdf, $rightX, $sigTextY + 48.0, $blockW, 5.0, 'NIP. ' . $this->formatNip($penerimaNip), 'L', '', 10.5, true);

        $this->line($pdf, 13.2, $sigY + 57.1, 197.3, $sigY + 57.1);

        $this->put($pdf, 0, $sigY + 62.7, self::PAGE_W, 5.0, 'PERHITUNGAN SPPD RAMPUNG', 'C', '', 10.5);

        // Perhitungan SPPD rampung
        $ditetapkan = (float) ($header['ditetapkan_sejumlah'] ?? $totalBiaya);
        $dibayar = (float) ($header['dibayar_semula'] ?? $totalBiaya);
        $sisa = $ditetapkan - $dibayar;

        $this->calculationRow($pdf, $sigY + 72.3, 'Ditetapkan sejumlah', $ditetapkan);
        $this->calculationRow($pdf, $sigY + 77.05, 'Yang telah dibayar semula', $dibayar);
        $this->calculationRow($pdf, $sigY + 81.8, 'Sisa kurang/lebih', $sisa, true);

        // Tanda tangan KPA
        $this->put($pdf, $rightX, $sigY + 96.2, $rightBlockW, 5.0, 'Kuasa Pengguna Anggaran', 'L', '', 10.5, true);
        $this->putFit($pdf, $rightX, $sigY + 120.1, $rightBlockW, 5.0, $kpaNama, 'L', 'U', 10.5);
        $this->put($pdf, $rightX, $sigY + 125.0, $blockW, 5.0, 'NIP. ' . $this->formatNip($kpaNip), 'L', '', 10.5, true);

        return $pdf;
    }

    /**
     * Isi default 4 baris bila detail kosong, sama seperti export lama.
     */
    private function normaliseDetails(array $details): array
    {
        if (!empty($details)) {
            return array_values($details);
        }

        return [
            ['nama_komponen' => 'Uang Harian', 'harga_satuan' => 0, 'jumlah_hari' => 1, 'jumlah' => 0, 'keterangan' => ''],
            ['nama_komponen' => 'BBM', 'harga_satuan' => 0, 'jumlah_hari' => null, 'jumlah' => 0, 'keterangan' => ''],
            ['nama_komponen' => 'Tol', 'harga_satuan' => 0, 'jumlah_hari' => null, 'jumlah' => 0, 'keterangan' => ''],
            ['nama_komponen' => 'Hotel', 'harga_satuan' => 0, 'jumlah_hari' => null, 'jumlah' => 0, 'keterangan' => ''],
        ];
    }

    private function rateText(float $hargaSatuan, ?float $jumlahHari): string
    {
        if ($hargaSatuan <= 0) {
            return 'Rp -';
        }

        $prefix = ($jumlahHari !== null && $jumlahHari > 0) ? '@ Rp ' : 'Rp ';
        return $prefix . $this->rupiah($hargaSatuan);
    }

    private function dayText(?float $jumlahHari): string
    {
        if ($jumlahHari === null || $jumlahHari <= 0) {
            return '';
        }
        if (floor($jumlahHari) == $jumlahHari) {
            return (string) (int) $jumlahHari;
        }
        return number_format($jumlahHari, 1, ',', '.');
    }

    private function amountRow(\FPDF $pdf, float $xRp, float $xRight, float $amount, float $y): void
    {
        $this->put($pdf, $xRp, $y, 8, 5.0, 'Rp', 'L', '', 10.5);
        $this->put($pdf, $xRight - 22.0, $y, 22.0, 5.0, $this->rupiah($amount), 'R', '', 10.5, true);
    }

    private function calculationRow(\FPDF $pdf, float $y, string $label, float $amount, bool $showDashForZero = false): void
    {
        $this->put($pdf, 13.9, $y, 56, 5.0, $label, 'L', '', 10.5, true);
        $this->put($pdf, 71.4, $y, 4, 5.0, ':', 'C', '', 10.5);
        $this->put($pdf, 77.2, $y, 5, 5.0, 'Rp', 'L', '', 10.5);

        if ($showDashForZero && $amount == 0.0) {
            $this->put($pdf, 83.2, $y, 18.8, 5.0, '-', 'C', '', 10.5);
            return;
        }

        $this->put($pdf, 83.2, $y, 18.8, 5.0, $this->rupiah($amount), 'R', '', 10.5, true);
    }

    private function line(\FPDF $pdf, float $x1, float $y1, float $x2, float $y2): void
    {
        $pdf->Line($x1, $y1, $x2, $y2);
    }

    /**
     * Tulis teks satu baris. $clip = potong dengan elipsis agar tidak menabrak kolom.
     */
    private function put(
        \FPDF $pdf,
        float $x,
        float $y,
        float $w,
        float $h,
        string $text,
        string $align = 'L',
        string $style = '',
        float $size = 10.5,
        bool $clip = false
    ): void {
        $pdf->SetFont('Arial', $style, $size);
        $encoded = $this->pdfText($text);
        if ($clip) {
            $encoded = $this->clip($pdf, $encoded, max(1.0, $w - 2.0));
        }
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, $h, $encoded, 0, 0, $align);
    }

    /**
     * Seperti put(), tetapi font dikecilkan bertahap agar teks muat utuh.
     * Hanya bila masih terlalu panjang pada ukuran minimum, teks dipotong elipsis.
     */
    private function putFit(
        \FPDF $pdf,
        float $x,
        float $y,
        float $w,
        float $h,
        string $text,
        string $align = 'L',
        string $style = '',
        float $size = 10.5,
        float $minSize = 9.0
    ): void {
        $encoded = $this->pdfText($text);
        $maxWidth = max(1.0, $w - 2.0);

        $pdf->SetFont('Arial', $style, $size);
        while ($size > $minSize && $pdf->GetStringWidth($encoded) > $maxWidth) {
            $size = max($minSize, $size - 0.25);
            $pdf->SetFont('Arial', $style, $size);
        }
        if ($pdf->GetStringWidth($encoded) > $maxWidth) {
            $encoded = $this->clip($pdf, $encoded, $maxWidth);
        }

        $pdf->SetXY($x, $y);
        $pdf->Cell($w, $h, $encoded, 0, 0, $align);
    }

    private function clip(\FPDF $pdf, string $text, float $maxWidth): string
    {
        if ($text === '' || $pdf->GetStringWidth($text) <= $maxWidth) {
            return $text;
        }

        $ellipsis = '...';
        while ($text !== '' && $pdf->GetStringWidth($text . $ellipsis) > $maxWidth) {
            $text = substr($text, 0, -1);
        }
        return rtrim($text) . $ellipsis;
    }

    private function pdfText(string $s): string
    {
        if (function_exists('kuitansi_pdf_text')) {
            return kuitansi_pdf_text($s);
        }

        $out = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
        return $out === false ? $s : $out;
    }

    private function rupiah(float $n): string
    {
        return number_format($n, 0, ',', '.');
    }

    private function env(string $key, string $default): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || trim((string) $value) === '') {
            return $default;
        }
        return trim((string) $value);
    }

    /**
     * Format NIP 18 digit standar PNS: 19840214 201001 1 011
     */
    public function formatNip(?string $nip): string
    {
        if (!$nip) {
            return '-';
        }
        $clean = preg_replace('/[^0-9]/', '', $nip);
        if (strlen($clean) === 18) {
            return substr($clean, 0, 8) . ' ' . substr($clean, 8, 6) . ' ' . substr($clean, 14, 1) . ' ' . substr($clean, 15, 3);
        }
        return $nip;
    }

    /**
     * Format Tanggal Bahasa Indonesia: 21 April 2026
     */
    public function formatTanggalIndo(?string $dateStr): string
    {
        if (!$dateStr) {
            return date('d F Y');
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

        $d = date('j', $t);
        $m = (int) date('n', $t);
        $y = date('Y', $t);

        return $d . ' ' . ($bulan[$m] ?? date('F', $t)) . ' ' . $y;
    }

    /**
     * Nomor SPT lengkap untuk "Lampiran SPT Nomor".
     */
    private function resolveNomorSurat(array $header, array $transaksi): string
    {
        $candidates = [];

        $stId = (int) ($header['surat_tugas_id'] ?? 0);
        if ($stId <= 0) {
            $stId = (int) ($transaksi['surat_tugas_ref_id'] ?? 0);
        }
        if ($stId > 0) {
            try {
                $cfg = __DIR__ . '/../../config/database_surat_tugas.php';
                if (!class_exists('DatabaseSuratTugas') && is_file($cfg)) {
                    require_once $cfg;
                }
                if (class_exists('DatabaseSuratTugas')) {
                    $stDb = \DatabaseSuratTugas::getConnection();
                    if ($stDb !== null) {
                        $stmt = $stDb->prepare('SELECT nomor_surat FROM surat_tugas WHERE id = ? LIMIT 1');
                        $stmt->execute([$stId]);
                        $candidates[] = (string) $stmt->fetchColumn();
                    }
                }
            } catch (\Throwable $e) {
                // abaikan - pakai cache lokal
            }
        }

        $candidates[] = (string) ($header['nomor_surat'] ?? '');
        $candidates[] = (string) ($transaksi['nomor_surat_tugas'] ?? '');

        $best = '';
        foreach ($candidates as $c) {
            $c = trim($c);
            if ($c !== '' && strlen($c) > strlen($best)) {
                $best = $c;
            }
        }
        if ($best === '') {
            return '-';
        }
        if (strpos($best, '/') !== false) {
            return $best;
        }

        $prefix = trim((string) ($_ENV['SPT_NOMOR_PREFIX'] ?? getenv('SPT_NOMOR_PREFIX') ?: '800.1.11.1'));
        $suffix = trim((string) ($_ENV['SPT_NOMOR_SUFFIX'] ?? getenv('SPT_NOMOR_SUFFIX') ?: '123.6.6'));
        $tglRaw = $transaksi['tanggal_surat_tugas'] ?? ($header['tanggal_surat'] ?? ($transaksi['tanggal'] ?? ''));
        $tahun = ($t = strtotime((string) $tglRaw)) !== false ? date('Y', $t) : date('Y');
        return $prefix . '/ ' . $best . ' /' . $suffix . '/' . $tahun;
    }

    /**
     * Konversi angka ke kalimat terbilang bahasa Indonesia.
     */
    public function terbilang(float $n): string
    {
        $n = abs((int) round($n));
        if ($n === 0) {
            return 'Nol';
        }

        $satuan = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];

        if ($n < 12) {
            return $satuan[$n];
        }
        if ($n < 20) {
            return $this->terbilang($n - 10) . ' Belas';
        }
        if ($n < 100) {
            $sisa = $n % 10;
            return $this->terbilang((int) ($n / 10)) . ' Puluh' . ($sisa ? ' ' . $this->terbilang($sisa) : '');
        }
        if ($n < 200) {
            $sisa = $n - 100;
            return 'Seratus' . ($sisa ? ' ' . $this->terbilang($sisa) : '');
        }
        if ($n < 1000) {
            $sisa = $n % 100;
            return $this->terbilang((int) ($n / 100)) . ' Ratus' . ($sisa ? ' ' . $this->terbilang($sisa) : '');
        }
        if ($n < 2000) {
            $sisa = $n - 1000;
            return 'Seribu' . ($sisa ? ' ' . $this->terbilang($sisa) : '');
        }
        if ($n < 1000000) {
            $sisa = $n % 1000;
            return $this->terbilang((int) ($n / 1000)) . ' Ribu' . ($sisa ? ' ' . $this->terbilang($sisa) : '');
        }
        if ($n < 1000000000) {
            $sisa = $n % 1000000;
            return $this->terbilang((int) ($n / 1000000)) . ' Juta' . ($sisa ? ' ' . $this->terbilang($sisa) : '');
        }
        if ($n < 1000000000000) {
            $sisa = $n % 1000000000;
            return $this->terbilang((int) ($n / 1000000000)) . ' Miliar' . ($sisa ? ' ' . $this->terbilang($sisa) : '');
        }
        return (string) $n;
    }
}
