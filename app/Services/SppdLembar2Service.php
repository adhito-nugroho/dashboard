<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Cetak ISI lembar 2 SPPD (overlay di atas blanko lembar 2 yang sudah tercetak).
 *
 * Hanya nilai yang dicetak — garis tabel dan label sudah pre-printed:
 * - I kanan : "Berangkat dari (tempat kedudukan)" (Bojonegoro),
 *             "Ke" (kota tujuan), "Pada tanggal" (tanggal mulai).
 * - II kiri  : "Tiba di" (kota tujuan), "Pada tanggal" (tanggal mulai),
 *              jabatan/nama/NIP pejabat instansi tujuan.
 * - II kanan : "Berangkat dari" (kota tujuan), "ke" (Bojonegoro),
 *              "Pada tanggal" (tanggal selesai), jabatan/nama/NIP pejabat tujuan.
 * - VI kiri  : "Tiba di" (Bojonegoro), "Pada tanggal" (tanggal selesai).
 *
 * Posisi default elemen ada di config/koordinat_sppd_lembar2.php (ruang
 * template + offset kertas). Halaman kalibrasi menyimpan posisi ABSOLUT (mm
 * pada A4) per printer; posisi tersimpan menimpa default.
 */
class SppdLembar2Service
{
    private const CONFIG = __DIR__ . '/../../config/koordinat_sppd_lembar2.php';

    private SpdExportService $spd;

    /** Posisi efektif: key => ['x_mm','y_mm','max_width_mm','max_lines'] (mm A4). */
    private array $pos = [];

    public function __construct(?SpdExportService $spd = null)
    {
        $this->spd = $spd ?? new SpdExportService();
    }

    public function getConfig(): array
    {
        return require self::CONFIG;
    }

    /**
     * Default config dalam mm A4: offset_mm + koordinat template.
     * @return array<string,array{x_mm:float,y_mm:float,w_mm:float,max_lines:int}>
     */
    public function getDefaults(): array
    {
        $cfg = $this->getConfig();
        $offX = (float) ($cfg['offset_mm']['x'] ?? 0);
        $offY = (float) ($cfg['offset_mm']['y'] ?? 0);
        $out = [];
        foreach (($cfg['elements'] ?? []) as $key => $el) {
            $out[$key] = [
                'x_mm' => round($offX + (float) ($el['x_mm'] ?? 0), 2),
                'y_mm' => round($offY + (float) ($el['y_mm'] ?? 0), 2),
                'w_mm' => (float) ($el['w_mm'] ?? 50),
                'max_lines' => max(1, (int) ($el['max_lines'] ?? 1)),
            ];
        }
        return $out;
    }

    /**
     * Terapkan posisi tersimpan (per printer). Key yang tidak ada mengikuti default.
     * $saved: [key => ['x_mm','y_mm','max_width_mm'|null,'max_lines'|null]]
     */
    public function setPositions(array $saved): void
    {
        $pos = [];
        foreach ($this->getDefaults() as $key => $def) {
            $row = is_array($saved[$key] ?? null) ? $saved[$key] : [];
            $w = $row['max_width_mm'] ?? null;
            $lines = $row['max_lines'] ?? null;
            $pos[$key] = [
                'x_mm' => isset($row['x_mm']) ? round((float) $row['x_mm'], 2) : $def['x_mm'],
                'y_mm' => isset($row['y_mm']) ? round((float) $row['y_mm'], 2) : $def['y_mm'],
                'max_width_mm' => ($w === null || $w === '') ? null : (float) $w,
                'max_lines' => ($lines === null || $lines === '') ? null : max(1, (int) $lines),
            ];
        }
        $this->pos = $pos;
    }

    /** @return array<string,array{x_mm:float,y_mm:float,max_width_mm:?float,max_lines:?int}> */
    public function getPositions(): array
    {
        if ($this->pos === []) {
            $this->setPositions([]);
        }
        return $this->pos;
    }

    /** Lebar efektif (mm) satu elemen. */
    public function widthOf(string $key): float
    {
        $pos = $this->getPositions();
        $ov = $pos[$key]['max_width_mm'] ?? null;
        if ($ov !== null && $ov > 0) {
            return $ov;
        }
        return (float) ($this->getDefaults()[$key]['w_mm'] ?? 50);
    }

    /** Jumlah baris efektif satu elemen. */
    public function linesOf(string $key): int
    {
        $pos = $this->getPositions();
        $ov = $pos[$key]['max_lines'] ?? null;
        if ($ov !== null) {
            return max(1, (int) $ov);
        }
        return (int) ($this->getDefaults()[$key]['max_lines'] ?? 1);
    }

    /** Contoh teks untuk kanvas kalibrasi & cetak uji. */
    public function sampleTexts(): array
    {
        return [
            'i_berangkat_dari' => 'Bojonegoro',
            'i_ke' => 'Sidoarjo',
            'i_tanggal' => '22 Oktober 2026',
            'tiba_di' => 'Sidoarjo',
            'tiba_tanggal' => '22 Oktober 2026',
            'pejabat_tiba_jabatan' => 'Auditor Manajemen ASN Ahli Pertama',
            'pejabat_tiba_nama' => 'Lina Maria Ulfa, S.IAN',
            'pejabat_tiba_nip' => 'NIP. 1997030920250062002',
            'berangkat_dari' => 'Sidoarjo',
            'berangkat_ke' => 'Bojonegoro',
            'berangkat_tanggal' => '22 Oktober 2026',
            'vi_tiba_di' => 'Bojonegoro',
            'vi_tiba_tanggal' => '22 Oktober 2026',
            'pejabat_berangkat_jabatan' => 'Auditor Manajemen ASN Ahli Pertama',
            'pejabat_berangkat_nama' => 'Lina Maria Ulfa, S.IAN',
            'pejabat_berangkat_nip' => 'NIP. 1997030920250062002',
        ];
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
            'i_berangkat_dari' => (string) $d['tempat_berangkat'],
            'i_ke' => $tujuan,
            'i_tanggal' => (string) $d['tgl_berangkat'],
            'tiba_di' => $tujuan,
            'tiba_tanggal' => (string) $d['tgl_berangkat'],
            'berangkat_dari' => $tujuan,
            'berangkat_ke' => (string) $d['tempat_berangkat'],
            'berangkat_tanggal' => (string) $d['tgl_kembali'],
            'vi_tiba_di' => (string) $d['tempat_berangkat'],
            'vi_tiba_tanggal' => (string) $d['tgl_kembali'],
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
        $fontFamily = (string) ($cfg['font']['family'] ?? 'Helvetica');
        $fontSize = (float) ($cfg['font']['size'] ?? 10.5);
        $elements = is_array($cfg['elements'] ?? null) ? $cfg['elements'] : [];

        $pdf = new \FPDF('P', 'mm', [$pageW, $pageH]);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        if ($test) {
            return $this->renderTestPage($pdf, $cfg, $pageW, $pageH);
        }

        $texts = $this->resolveTexts($values, $pejabat);
        $positions = $this->getPositions();

        foreach ($elements as $key => $el) {
            $text = (string) ($texts[$key] ?? '');
            if ($text === '' || !isset($positions[$key])) {
                continue;
            }
            $x = (float) $positions[$key]['x_mm'];
            $y = (float) $positions[$key]['y_mm'];
            $w = $this->widthOf($key);
            $h = (float) ($el['h_mm'] ?? 5);
            $this->cell($pdf, $x, $y, $w, $h, $text, (string) ($el['align'] ?? 'L'), (string) ($el['style'] ?? ''), $fontFamily, $fontSize, $this->linesOf($key));
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
            'i_berangkat_dari' => (string) ($values['i_berangkat_dari'] ?? ''),
            'i_ke' => (string) ($values['i_ke'] ?? ''),
            'i_tanggal' => (string) ($values['i_tanggal'] ?? ''),
            'tiba_di' => (string) ($values['tiba_di'] ?? ''),
            'tiba_tanggal' => (string) ($values['tiba_tanggal'] ?? ''),
            'berangkat_dari' => (string) ($values['berangkat_dari'] ?? ''),
            'berangkat_ke' => (string) ($values['berangkat_ke'] ?? ''),
            'berangkat_tanggal' => (string) ($values['berangkat_tanggal'] ?? ''),
            'vi_tiba_di' => (string) ($values['vi_tiba_di'] ?? ''),
            'vi_tiba_tanggal' => (string) ($values['vi_tiba_tanggal'] ?? ''),
            'pejabat_tiba_jabatan' => $jabatan,
            'pejabat_tiba_nama' => $nama,
            'pejabat_tiba_nip' => $nipText,
            'pejabat_berangkat_jabatan' => $jabatan,
            'pejabat_berangkat_nama' => $nama,
            'pejabat_berangkat_nip' => $nipText,
        ];
    }

    /**
     * Tulis teks dalam kotak; bila maxLines > 1 teks dipecah per kata ke
     * beberapa baris (jarak antar baris = h_mm). Baris terakhir dipotong
     * dengan elipsis bila masih melebihi lebar kotak.
     */
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
        float $size,
        int $maxLines = 1
    ): void {
        $pdf->SetFont($family, $style, $size);
        $encoded = $this->pdfText($text);
        $maxWidth = max(1.0, $w - 1.0);
        $lines = $this->wrapLines($pdf, $encoded, $maxWidth, $maxLines);
        foreach ($lines as $i => $line) {
            $pdf->SetXY($x, $y + ($i * $h));
            $pdf->Cell($w, $h, $line, 0, 0, $align);
        }
    }

    /** @return string[] */
    private function wrapLines(\FPDF $pdf, string $text, float $maxWidth, int $maxLines): array
    {
        $text = trim($text);
        if ($maxLines <= 1 || $pdf->GetStringWidth($text) <= $maxWidth) {
            return [$this->fitLine($pdf, $text, $maxWidth)];
        }

        $words = preg_split('/\s+/', $text) ?: [];
        $lines = [];
        $cur = '';
        $i = 0;
        $n = count($words);

        // Isi baris 1..(maxLines-1) secara greedy; sisanya masuk baris terakhir.
        while ($i < $n && count($lines) < $maxLines - 1) {
            $word = (string) $words[$i];
            $try = $cur === '' ? $word : $cur . ' ' . $word;
            if ($cur !== '' && $pdf->GetStringWidth($try) > $maxWidth) {
                $lines[] = $cur;
                $cur = '';
                continue;
            }
            $cur = $try;
            $i++;
        }

        $rest = trim($cur . ' ' . implode(' ', array_slice($words, $i)));
        $lines[] = $this->fitLine($pdf, $rest, $maxWidth);

        return $lines;
    }

    private function fitLine(\FPDF $pdf, string $text, float $maxWidth): string
    {
        if ($pdf->GetStringWidth($text) <= $maxWidth) {
            return $text;
        }
        return $this->clip($pdf, $text, $maxWidth);
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
     * Halaman uji: kotak + crosshair tiap elemen (posisi efektif / hasil
     * geser di kanvas kalibrasi) agar bisa ditumpuk ke blanko fisik.
     */
    private function renderTestPage(
        \FPDF $pdf,
        array $cfg,
        float $pageW,
        float $pageH
    ): \FPDF {
        $content = $cfg['content'] ?? [];
        $cw = (float) ($content['width_mm'] ?? 0);
        $ch = (float) ($content['height_mm'] ?? 0);
        $offX = (float) ($cfg['offset_mm']['x'] ?? 0);
        $offY = (float) ($cfg['offset_mm']['y'] ?? 0);

        $pdf->SetDrawColor(180, 180, 180);
        $pdf->SetLineWidth(0.2);
        $pdf->Rect(2, 2, $pageW - 4, $pageH - 4);
        if ($cw > 0 && $ch > 0) {
            $pdf->Rect($offX, $offY, $cw, $ch);
        }

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetXY(6, 4);
        $pdf->Cell(180, 5, $this->pdfText(
            sprintf('UJI POSISI LEMBAR 2 SPPD — A4 %.0fx%.0fmm (mm pada A4)', $pageW, $pageH)
        ));

        $positions = $this->getPositions();
        foreach ($positions as $key => $p) {
            $el = $cfg['elements'][$key] ?? [];
            $x = (float) $p['x_mm'];
            $y = (float) $p['y_mm'];
            $w = $this->widthOf($key);
            $h = (float) ($el['h_mm'] ?? 5);
            $lines = $this->linesOf($key);
            $boxH = $h * $lines;

            $pdf->SetDrawColor(200, 30, 30);
            $pdf->Rect($x, $y, $w, $boxH);
            $r = 2.5;
            $pdf->Line($x - $r, $y + $boxH / 2, $x + $r, $y + $boxH / 2);
            $pdf->Line($x, $y - $r, $x, $y + $boxH + $r);

            $pdf->SetFont('Arial', '', 6);
            $pdf->SetTextColor(200, 30, 30);
            $pdf->SetXY($x + 0.6, $y + 0.6);
            $pdf->Cell($w - 1.2, 3, $this->pdfText(sprintf('%s (%.1f,%.1f)', $key, $x, $y)));
            $pdf->SetTextColor(0, 0, 0);
        }

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetXY(6, $pageH - 12);
        $pdf->MultiCell(
            198,
            4,
            $this->pdfText('Cetak di kertas yang sama dengan blanko, lalu tumpuk ke blanko untuk mengecek posisi. Posisi bisa disetel per printer di halaman Kalibrasi Lembar 2 SPPD.'),
            0,
            'L'
        );
        $pdf->SetTextColor(0, 0, 0);

        return $pdf;
    }

    /**
     * Cetak uji dari posisi kanvas (belum tentu tersimpan).
     * $positions: [key => ['x_mm','y_mm','max_width_mm'|null,'max_lines'|null]]
     */
    public function streamTestPage(?array $positions = null): void
    {
        if (is_array($positions)) {
            $this->setPositions($positions);
        }
        $pdf = $this->generatePdf([], [], true);

        $filename = 'Uji_Kalibrasi_SPPD_Lembar2.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $pdf->Output('I', $filename);
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
