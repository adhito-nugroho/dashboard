<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Cetak ISI lembar 2 SPPD (overlay di atas blanko lembar 2 yang sudah tercetak).
 *
 * Hanya nilai yang dicetak — garis tabel dan label sudah pre-printed:
 * - II kiri  : "Tiba di" (kota tujuan), "Pada tanggal" (tanggal mulai),
 *              jabatan/nama/NIP pejabat instansi tujuan.
 * - II kanan : "Berangkat dari" (kota tujuan), "ke" (Bojonegoro),
 *              "Pada tanggal" (tanggal selesai), jabatan/nama/NIP pejabat tujuan.
 *
 * Posisi elemen ada di config/koordinat_sppd_lembar2.php (lihat komentar di sana).
 */
class SppdLembar2Service
{
    private const CONFIG = __DIR__ . '/../../config/koordinat_sppd_lembar2.php';

    private SpdExportService $spd;

    public function __construct(?SpdExportService $spd = null)
    {
        $this->spd = $spd ?? new SpdExportService();
    }

    public function getConfig(): array
    {
        return require self::CONFIG;
    }

    /**
     * Isian otomatis dari Surat Tugas.
     * Return null bila ST/pegawai tidak ditemukan di database Surat Tugas.
     */
    public function buildValues(int $suratTugasId, string $nip): ?array
    {
        $d = $this->spd->buildData($suratTugasId, $nip);
        if ($d === null) {
            return null;
        }

        $kotaTujuan = trim((string) ($d['kota_tujuan'] ?? ''));
        $tujuan = trim((string) ($d['tempat_tujuan'] ?? $kotaTujuan));

        return [
            'tiba_di' => $tujuan,
            'tiba_tanggal' => (string) $d['tgl_berangkat'],
            'berangkat_dari' => $tujuan,
            'berangkat_ke' => (string) $d['tempat_berangkat'],
            'berangkat_tanggal' => (string) $d['tgl_kembali'],
            'kota_tujuan_kosong' => $kotaTujuan === '',
            'nomor_surat' => (string) ($d['nomor_surat'] ?? ''),
            'pegawai_nama' => (string) ($d['pegawai_nama'] ?? ''),
            'pegawai_nip' => (string) ($d['pegawai_nip'] ?? ''),
        ];
    }

    /**
     * Render PDF overlay A4. $pejabat = ['jabatan'=>..,'nama'=>..,'nip'=>..].
     * $test = true menghasilkan halaman uji posisi (kotak + crosshair), bukan isian.
     */
    public function generatePdf(array $values, array $pejabat = [], bool $test = false): \FPDF
    {
        require_once __DIR__ . '/../../lib/fpdf.php';
        require_once __DIR__ . '/../../config/terbilang.php';

        $cfg = $this->getConfig();
        $pageW = (float) ($cfg['page']['width_mm'] ?? 210);
        $pageH = (float) ($cfg['page']['height_mm'] ?? 297);
        $offX = (float) ($cfg['offset_mm']['x'] ?? 0);
        $offY = (float) ($cfg['offset_mm']['y'] ?? 0);
        $fontFamily = (string) ($cfg['font']['family'] ?? 'Helvetica');
        $fontSize = (float) ($cfg['font']['size'] ?? 10.5);
        $elements = is_array($cfg['elements'] ?? null) ? $cfg['elements'] : [];

        $pdf = new \FPDF('P', 'mm', [$pageW, $pageH]);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        if ($test) {
            return $this->renderTestPage($pdf, $cfg, $elements, $pageW, $pageH, $offX, $offY);
        }

        $texts = $this->resolveTexts($values, $pejabat);

        foreach ($elements as $key => $el) {
            $text = (string) ($texts[$key] ?? '');
            if ($text === '') {
                continue;
            }
            $x = $offX + (float) ($el['x_mm'] ?? 0);
            $y = $offY + (float) ($el['y_mm'] ?? 0);
            $w = (float) ($el['w_mm'] ?? 50);
            $h = (float) ($el['h_mm'] ?? 5);
            $this->cell($pdf, $x, $y, $w, $h, $text, (string) ($el['align'] ?? 'L'), (string) ($el['style'] ?? ''), $fontFamily, $fontSize);
        }

        return $pdf;
    }

    private function resolveTexts(array $values, array $pejabat): array
    {
        $jabatan = trim((string) ($pejabat['jabatan'] ?? ''));
        $nama = trim((string) ($pejabat['nama'] ?? ''));
        $nip = trim((string) ($pejabat['nip'] ?? ''));
        $nipText = $nip !== '' ? 'NIP. ' . $nip : '';

        return [
            'tiba_di' => (string) ($values['tiba_di'] ?? ''),
            'tiba_tanggal' => (string) ($values['tiba_tanggal'] ?? ''),
            'berangkat_dari' => (string) ($values['berangkat_dari'] ?? ''),
            'berangkat_ke' => (string) ($values['berangkat_ke'] ?? ''),
            'berangkat_tanggal' => (string) ($values['berangkat_tanggal'] ?? ''),
            'pejabat_tiba_jabatan' => $jabatan,
            'pejabat_tiba_nama' => $nama,
            'pejabat_tiba_nip' => $nipText,
            'pejabat_berangkat_jabatan' => $jabatan,
            'pejabat_berangkat_nama' => $nama,
            'pejabat_berangkat_nip' => $nipText,
        ];
    }

    /** Tulis satu baris; teks yang melebihi kotak dipotong dengan elipsis. */
    private function cell(
        \FPDF $pdf,
        float $x,
        float $y,
        float $w,
        float $h,
        string $text,
        string $align,
        string $style,
        string $family,
        float $size
    ): void {
        $pdf->SetFont($family, $style, $size);
        $encoded = $this->pdfText($text);
        $maxWidth = max(1.0, $w - 1.0);
        if ($pdf->GetStringWidth($encoded) > $maxWidth) {
            $encoded = $this->clip($pdf, $encoded, $maxWidth);
        }
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, $h, $encoded, 0, 0, $align);
    }

    private function clip(\FPDF $pdf, string $text, float $maxWidth): string
    {
        $ellipsis = '...';
        while ($text !== '' && $pdf->GetStringWidth($text . $ellipsis) > $maxWidth) {
            $text = substr($text, 0, -1);
        }
        return rtrim($text) . $ellipsis;
    }

    /**
     * Halaman uji: kotak + crosshair tiap elemen agar bisa ditumpuk ke blanko
     * untuk mengecek/menggeser koordinat.
     */
    private function renderTestPage(
        \FPDF $pdf,
        array $cfg,
        array $elements,
        float $pageW,
        float $pageH,
        float $offX,
        float $offY
    ): \FPDF {
        $content = $cfg['content'] ?? [];
        $cw = (float) ($content['width_mm'] ?? 0);
        $ch = (float) ($content['height_mm'] ?? 0);

        $pdf->SetDrawColor(180, 180, 180);
        $pdf->SetLineWidth(0.2);
        if ($cw > 0 && $ch > 0) {
            $pdf->Rect($offX, $offY, $cw, $ch);
        }

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetXY(6, 4);
        $pdf->Cell(180, 5, $this->pdfText(
            sprintf(
                'UJI POSISI LEMBAR 2 SPPD — A4 %.0fx%.0fmm, offset (%.2f, %.2f)',
                $pageW,
                $pageH,
                $offX,
                $offY
            )
        ));

        foreach ($elements as $key => $el) {
            $x = $offX + (float) ($el['x_mm'] ?? 0);
            $y = $offY + (float) ($el['y_mm'] ?? 0);
            $w = (float) ($el['w_mm'] ?? 50);
            $h = (float) ($el['h_mm'] ?? 5);

            $pdf->SetDrawColor(200, 30, 30);
            $pdf->Rect($x, $y, $w, $h);
            $r = 2.5;
            $pdf->Line($x - $r, $y + $h / 2, $x + $r, $y + $h / 2);
            $pdf->Line($x, $y - $r, $x, $y + $h + $r);

            $pdf->SetFont('Arial', '', 6);
            $pdf->SetTextColor(200, 30, 30);
            $pdf->SetXY($x, max(0.5, $y - 3.4));
            $pdf->Cell($w, 3, $this->pdfText(sprintf('%s (%.1f,%.1f)', $key, $el['x_mm'] ?? 0, $el['y_mm'] ?? 0)));
            $pdf->SetTextColor(0, 0, 0);
        }

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetXY(6, $pageH - 12);
        $pdf->MultiCell(
            198,
            4,
            $this->pdfText('Cetak di kertas yang sama dengan blanko, lalu tumpuk ke blanko untuk mengecek posisi. Sesuaikan offset_mm / x_mm / y_mm di config/koordinat_sppd_lembar2.php.'),
            0,
            'L'
        );
        $pdf->SetTextColor(0, 0, 0);

        return $pdf;
    }

    public function streamPdf(array $values, array $pejabat = [], bool $test = false): void
    {
        $pdf = $this->generatePdf($values, $pejabat, $test);

        if ($test) {
            $filename = 'Uji_Posisi_SPPD_Lembar2.pdf';
        } else {
            $safe = preg_replace('/[^A-Za-z0-9]+/', '_', (string) ($values['pegawai_nama'] ?? ''));
            $safe = trim((string) $safe, '_');
            $filename = 'SPPD_Lembar2_' . ($safe !== '' ? $safe : 'Pegawai') . '.pdf';
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $pdf->Output('I', $filename);
    }

    private function pdfText(string $s): string
    {
        if (function_exists('kuitansi_pdf_text')) {
            return kuitansi_pdf_text($s);
        }
        $out = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
        return $out === false ? $s : $out;
    }
}
