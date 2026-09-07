<?php

namespace App\Services;

require_once __DIR__ . '/../../lib/fpdf.php';

class KuitansiPdfService
{
    private array $koordinat;

    public function __construct()
    {
        $this->koordinat = require __DIR__ . '/../../config/koordinat_kuitansi.php';
    }

    public function getKoordinat(): array
    {
        return $this->koordinat;
    }

    private function newPdf(): \FPDF
    {
        $w = (float) ($this->koordinat['page']['width_mm'] ?? 215);
        $h = (float) ($this->koordinat['page']['height_mm'] ?? 165);
        $pdf = new \FPDF('L', 'mm', [$w, $h]);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();
        return $pdf;
    }

    private function field(\FPDF $pdf, string $key, string $text, float $ox, float $oy, bool $multi = false): void
    {
        $c = $this->koordinat[$key];
        $pdf->SetFont($c['font'] ?? 'Times', $c['style'] ?? '', (int) ($c['size'] ?? 11));
        $pdf->SetXY((float) $c['x_mm'] + $ox, (float) $c['y_mm'] + $oy);
        $text = kuitansi_pdf_text($text);
        if ($multi) {
            $pdf->MultiCell((float) $c['w_mm'], (float) ($c['line_h_mm'] ?? 6), $text, 0, $c['align'] ?? 'L');
        } else {
            $pdf->Cell((float) $c['w_mm'], (float) $c['h_mm'], $text, 0, 0, $c['align'] ?? 'L');
        }
    }

    private function ttdBlock(\FPDF $pdf, string $key, string $nama, string $nip, float $ox, float $oy): void
    {
        $c = $this->koordinat[$key];
        $x = (float) $c['x_mm'] + $ox;
        $y = (float) $c['y_mm'] + $oy;
        $w = (float) $c['w_mm'];
        $sigSpace = (float) ($this->koordinat['ttd_signature_space_mm'] ?? 18);
        $nipGap = (float) ($this->koordinat['ttd_nip_gap_mm'] ?? 1);

        $pdf->SetFont($c['font'] ?? 'Times', '', (int) ($c['size'] ?? 10));
        $pdf->SetXY($x, $y);
        $pdf->MultiCell($w, 5, kuitansi_pdf_text($c['jabatan'] ?? ''), 0, 'C');

        $namaY = $pdf->GetY() + $sigSpace;
        $pdf->SetFont($c['font'] ?? 'Times', 'U', (int) ($c['size'] ?? 10));
        $pdf->SetXY($x, $namaY);
        $pdf->Cell($w, 5, kuitansi_pdf_text($nama !== '' ? $nama : '........................'), 0, 0, 'C');

        $pdf->SetFont($c['font'] ?? 'Times', '', (int) ($c['size'] ?? 10));
        $pdf->SetXY($x, $namaY + 5 + $nipGap);
        $pdf->Cell($w, 5, kuitansi_pdf_text($nip !== '' ? ('NIP. ' . $nip) : ''), 0, 0, 'C');
    }

    /**
     * Bangun data cetak dari satu baris transaksi (hasil Transaksi::getById, sudah JOIN).
     */
    public function buildData(array $trx): array
    {
        $nilai = (float) ($trx['nilai'] ?? 0);
        $tanggal = (string) ($trx['tanggal'] ?? date('Y-m-d'));

        return [
            'no_bku' => (string) ($trx['nomor_bukti'] ?? ''),
            'no_program' => trim((string) ($trx['kode_program'] ?? '')),
            'no_kegiatan' => trim((string) ($trx['kode_kegiatan'] ?? '')),
            // "Terima dari" boleh dikosongkan — default kosong sesuai spec.
            'terima_dari' => '',
            'jumlah_terbilang' => kuitansi_terbilang($nilai) . ' Rupiah',
            'uraian' => (string) ($trx['uraian'] ?? ''),
            'terbilang_rp' => number_format($nilai, 0, ',', '.'),
            'tempat_tanggal' => 'Bojonegoro, ' . kuitansi_tanggal_id($tanggal),
            'kpa_nama' => (string) ($_ENV['KPA_NAMA'] ?? getenv('KPA_NAMA') ?: 'ENDANG HANDAYANI, S.P., M.Si.'),
            'kpa_nip' => (string) ($_ENV['KPA_NIP'] ?? getenv('KPA_NIP') ?: '19760328 200003 2 003'),
            'bendahara_nama' => (string) ($_ENV['BENDAHARA_NAMA'] ?? getenv('BENDAHARA_NAMA') ?: 'ADHITO NUGROHO, S.Kom.'),
            'bendahara_nip' => (string) ($_ENV['BENDAHARA_NIP'] ?? getenv('BENDAHARA_NIP') ?: '19840214 201001 1 011'),
            'penerima_nama' => (string) ($trx['nama_penerima'] ?? ''),
            'nilai' => $nilai,
        ];
    }

    /**
     * Generate PDF kuitansi untuk satu transaksi. Stream langsung ke browser (print).
     */
    public function streamKuitansi(array $trx, float $offsetX, float $offsetY): void
    {
        $d = $this->buildData($trx);
        $pdf = $this->newPdf();

        $this->field($pdf, 'no_bku', $d['no_bku'], $offsetX, $offsetY);
        $this->field($pdf, 'no_program', $d['no_program'], $offsetX, $offsetY);
        $this->field($pdf, 'no_kegiatan', $d['no_kegiatan'], $offsetX, $offsetY);
        $this->field($pdf, 'terima_dari', $d['terima_dari'], $offsetX, $offsetY);
        $this->field($pdf, 'jumlah_terbilang', $d['jumlah_terbilang'], $offsetX, $offsetY);
        $this->field($pdf, 'uraian', $d['uraian'], $offsetX, $offsetY, true);
        $this->field($pdf, 'terbilang_rp', $d['terbilang_rp'], $offsetX, $offsetY);
        $this->field($pdf, 'tempat_tanggal', $d['tempat_tanggal'], $offsetX, $offsetY);

        $this->ttdBlock($pdf, 'ttd1', $d['kpa_nama'], $d['kpa_nip'], $offsetX, $offsetY);
        $this->ttdBlock($pdf, 'ttd2', $d['bendahara_nama'], $d['bendahara_nip'], $offsetX, $offsetY);
        $this->ttdBlock($pdf, 'ttd3', $d['penerima_nama'], '', $offsetX, $offsetY);

        $fname = 'kuitansi_' . preg_replace('/[^A-Za-z0-9-_]+/', '_', (string) ($trx['nomor_bukti'] ?? $trx['id'] ?? 'transaksi')) . '.pdf';
        $pdf->Output('I', $fname);
    }

    /**
     * "Cetak Uji": gambar crosshair + label di tiap posisi elemen memakai offset
     * yang sedang diketik di form (belum tentu tersimpan). Dicetak ke kertas NCR
     * contoh untuk cek kesesuaian sebelum klik Simpan.
     */
    public function streamTestPage(float $offsetX, float $offsetY): void
    {
        $pdf = $this->newPdf();
        $pageW = (float) ($this->koordinat['page']['width_mm'] ?? 215);
        $pageH = (float) ($this->koordinat['page']['height_mm'] ?? 165);

        // Bingkai halaman tipis agar terlihat batas kertas 215x165.
        $pdf->SetDrawColor(150, 150, 150);
        $pdf->Rect(2, 2, $pageW - 4, $pageH - 4);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetXY(5, 4);
        $pdf->Cell(120, 5, kuitansi_pdf_text(sprintf('UJI KALIBRASI KUITANSI 215x165mm  (ox=%+.1fmm, oy=%+.1fmm)', $offsetX, $offsetY)));

        $keys = ['no_bku', 'no_program', 'no_kegiatan', 'terima_dari', 'jumlah_terbilang', 'uraian', 'terbilang_rp', 'tempat_tanggal', 'ttd1', 'ttd2', 'ttd3'];
        foreach ($keys as $key) {
            $c = $this->koordinat[$key];
            $x = (float) $c['x_mm'] + $offsetX;
            $y = (float) $c['y_mm'] + $offsetY;
            $w = (float) ($c['w_mm'] ?? 40);
            $h = (float) ($c['h_mm'] ?? ($key === 'uraian' ? 24 : 6));

            // Kotak area elemen.
            $pdf->SetDrawColor(200, 30, 30);
            $pdf->Rect($x, $y, $w, $h);

            // Crosshair di titik awal (x,y).
            $r = 3;
            $pdf->Line($x - $r, $y, $x + $r, $y);
            $pdf->Line($x, $y - $r, $x, $y + $r);

            // Label nama elemen.
            $pdf->SetFont('Arial', '', 7);
            $pdf->SetTextColor(200, 30, 30);
            $pdf->SetXY($x + 1, $y + 1);
            $pdf->Cell($w - 2, 4, kuitansi_pdf_text(($c['label'] ?? $key) . sprintf(' (%g,%g)', $x, $y)));
            $pdf->SetTextColor(0, 0, 0);
        }

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetXY(5, $pageH - 10);
        $pdf->Cell(200, 5, kuitansi_pdf_text('Tempelkan hasil cetak ini di atas kertas NCR fisik. Jika teks uji meleset, ukur selisihnya (mm) lalu isi sebagai offset.'));

        $pdf->Output('I', 'uji-kalibrasi-kuitansi.pdf');
    }
}
