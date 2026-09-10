<?php

namespace App\Services;

require_once __DIR__ . '/../../lib/fpdf.php';
require_once __DIR__ . '/../../config/terbilang.php';

class KuitansiPdfService
{
    /**
     * 13 elemen_key — HARUS match persis dengan seed tabel kalibrasi_kuitansi_elemen
     * dan dengan daftar kanvas di views/kuitansi/kalibrasi.php.
     * Teks jabatan TTD ("Setuju dibayar" dll) SENGAJA tidak ada: sudah pre-printed
     * di kertas NCR, jadi tidak digambar ulang. Yang dicetak hanya nama + NIP.
     */
    public const ELEMEN_KEYS = [
        'no_bku', 'no_program', 'no_kegiatan',
        'terima_dari', 'jumlah_terbilang', 'uraian',
        'terbilang_rp', 'tempat_tanggal',
        'ttd_kpa_nama', 'ttd_kpa_nip',
        'ttd_bendahara_nama', 'ttd_bendahara_nip',
        'ttd_penerima_nama',
    ];

    private array $koordinat;

    /** Posisi aktif per elemen_key: ['key' => ['x_mm'=>f,'y_mm'=>f,'max_width_mm'=>f|null]] */
    private array $pos = [];

    public function __construct()
    {
        $this->koordinat = require __DIR__ . '/../../config/koordinat_kuitansi.php';
        $this->pos = self::defaultPositions($this->koordinat);
    }

    public function getKoordinat(): array
    {
        return $this->koordinat;
    }

    /**
     * Posisi default dari config (dipakai jika DB belum ada / key belum tersimpan).
     * Diukur dari scan NCR asli. Baris nama tepat di garis titik-titik (y=150),
     * NIP di bawahnya (y=156), jabatan di y=122.
     */
    public static function defaultPositions(array $koordinat): array
    {
        $t1 = $koordinat['ttd1'] ?? ['x_mm' => 47, 'y_mm' => 122];
        $t2 = $koordinat['ttd2'] ?? ['x_mm' => 107, 'y_mm' => 122];
        $t3 = $koordinat['ttd3'] ?? ['x_mm' => 168, 'y_mm' => 122];
        $g = fn($c) => ['x_mm' => (float) ($c['x_mm'] ?? 0), 'y_mm' => (float) ($c['y_mm'] ?? 0)];

        $p = [];
        foreach (['no_bku', 'no_program', 'no_kegiatan', 'terima_dari', 'jumlah_terbilang', 'uraian', 'terbilang_rp', 'tempat_tanggal'] as $k) {
            $p[$k] = $g($koordinat[$k] ?? []);
        }
        $p['uraian']['max_width_mm'] = (float) ($koordinat['uraian']['w_mm'] ?? 170);

        // Nama/NIP di garis titik-titik. ttd1/2/3 hanya jangkar x (teks jabatan
        // tidak digambar — sudah pre-printed di NCR).
        $p['ttd_kpa_nama'] = ['x_mm' => (float) $t1['x_mm'] + 19, 'y_mm' => 150];
        $p['ttd_kpa_nip'] = ['x_mm' => (float) $t1['x_mm'] + 19, 'y_mm' => 156];
        $p['ttd_bendahara_nama'] = ['x_mm' => (float) $t2['x_mm'] + 19, 'y_mm' => 150];
        $p['ttd_bendahara_nip'] = ['x_mm' => (float) $t2['x_mm'] + 19, 'y_mm' => 156];
        $p['ttd_penerima_nama'] = ['x_mm' => (float) $t3['x_mm'] + 16, 'y_mm' => 150];
        return $p;
    }

    /** Timpa posisi aktif dari DB (hasil KalibrasiKuitansiElemen::getAll()). */
    public function setPositions(array $pos): void
    {
        foreach ($pos as $key => $v) {
            if (!in_array($key, self::ELEMEN_KEYS, true) || !is_array($v)) {
                continue;
            }
            $this->pos[$key] = [
                'x_mm' => (float) ($v['x_mm'] ?? $this->pos[$key]['x_mm'] ?? 0),
                'y_mm' => (float) ($v['y_mm'] ?? $this->pos[$key]['y_mm'] ?? 0),
            ];
            if (array_key_exists('max_width_mm', $v)) {
                $this->pos[$key]['max_width_mm'] = $v['max_width_mm'] === null ? null : (float) $v['max_width_mm'];
            }
        }
    }

    public function getPositions(): array
    {
        return $this->pos;
    }

    private function newPdf(): \FPDF
    {
        $w = (float) ($this->koordinat['page']['width_mm'] ?? 215);
        $h = (float) ($this->koordinat['page']['height_mm'] ?? 165);
        // PERHATIAN: FPDF menukar size[0]/size[1] untuk orientasi 'L'
        // (w = size[1], h = size[0]), jadi oper [h, w] agar MediaBox = 215x165.
        // Pernah terbalik ([w, h] -> box 165x215 + isi meluber) sehingga
        // printer me-scale/rotate sendiri: huruf jadi raksasa & posisi kacau.
        $pdf = new \FPDF('L', 'mm', [$h, $w]);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();
        return $pdf;
    }

    /** Lebar gambar: max_width_mm (uraian), w_mm config, atau peta ttd_widths. */
    private function widthOf(string $key): float
    {
        if ($key === 'uraian') {
            return (float) ($this->pos['uraian']['max_width_mm'] ?? $this->koordinat['uraian']['w_mm'] ?? 170);
        }
        $tw = $this->koordinat['ttd_widths'] ?? [];
        if (isset($tw[$key])) {
            return (float) $tw[$key];
        }
        return (float) ($this->koordinat[$key]['w_mm'] ?? 60);
    }

    private function field(\FPDF $pdf, string $key, string $text, bool $multi = false): void
    {
        $c = $this->koordinat[$key] ?? ['font' => 'Helvetica', 'style' => '', 'size' => 10, 'align' => 'L', 'h_mm' => 6];
        $p = $this->pos[$key] ?? ['x_mm' => 0, 'y_mm' => 0];
        $pdf->SetFont($c['font'] ?? 'Helvetica', $c['style'] ?? '', (int) ($c['size'] ?? 10));
        $pdf->SetXY((float) $p['x_mm'], (float) $p['y_mm']);
        $text = kuitansi_pdf_text($text);
        if ($multi) {
            $pdf->MultiCell($this->widthOf($key), (float) ($c['line_h_mm'] ?? 6), $text, 0, $c['align'] ?? 'L');
        } else {
            $pdf->Cell($this->widthOf($key), (float) ($c['h_mm'] ?? 6), $text, 0, 0, $c['align'] ?? 'L');
        }
    }

    /** Satu baris TTD (jabatan pakai MultiCell, nama underline, NIP biasa). */
    private function ttdLine(\FPDF $pdf, string $key, string $text, string $style = ''): void
    {
        $p = $this->pos[$key] ?? ['x_mm' => 0, 'y_mm' => 0];
        $w = $this->widthOf($key);
        $pdf->SetFont('Helvetica', $style, 9);
        $pdf->SetXY((float) $p['x_mm'], (float) $p['y_mm']);
        if ($style === '' && str_contains($text, "\n")) {
            $pdf->MultiCell($w, 5, kuitansi_pdf_text($text), 0, 'C');
        } else {
            $pdf->Cell($w, 5, kuitansi_pdf_text($text), 0, 0, 'C');
        }
    }

    /**
     * Bangun data cetak dari satu baris transaksi (hasil Transaksi::getById, sudah JOIN).
     * Murni ISI teks — penempatan murni dari posisi per-elemen.
     */
    public function buildData(array $trx): array
    {
        $nilai = (float) ($trx['nilai'] ?? 0);
        $tanggal = (string) ($trx['tanggal'] ?? date('Y-m-d'));

        return [
            // Nomor kuitansi / no_bku dikosongkan pada cetakan kuitansi
            'no_bku' => '',
            'no_program' => trim((string) ($trx['kode_program'] ?? '')),
            'no_kegiatan' => trim((string) ($trx['kode_kegiatan'] ?? '')),
            // "Terima dari" boleh dikosongkan — default kosong.
            'terima_dari' => '',
            'jumlah_terbilang' => kuitansi_terbilang($nilai) . ' Rupiah',
            'uraian' => (string) ($trx['uraian'] ?? ''),
            'terbilang_rp' => number_format($nilai, 0, ',', '.'),
            'tempat_tanggal' => kuitansi_tanggal_id($tanggal),
            'kpa_nama' => (string) ($_ENV['KPA_NAMA'] ?? getenv('KPA_NAMA') ?: 'ENDANG HANDAYANI, S.P., M.Si.'),
            'kpa_nip' => (string) ($_ENV['KPA_NIP'] ?? getenv('KPA_NIP') ?: '19760328 200003 2 003'),
            'bendahara_nama' => (string) ($_ENV['BENDAHARA_NAMA'] ?? getenv('BENDAHARA_NAMA') ?: 'ADHITO NUGROHO, S.Kom.'),
            'bendahara_nip' => (string) ($_ENV['BENDAHARA_NIP'] ?? getenv('BENDAHARA_NIP') ?: '19840214 201001 1 011'),
            'penerima_nama' => (string) ($trx['nama_penerima'] ?? ''),
            'nilai' => $nilai,
        ];
    }

    /**
     * Teks contoh per elemen_key dari satu transaksi asli — untuk pratinjau
     * kanvas kalibrasi (bukan dummy). Key tidak dikenal diabaikan pemanggil.
     */
    public function sampleTexts(array $trx): array
    {
        $d = $this->buildData($trx);
        return [
            'no_bku' => $d['no_bku'],
            'no_program' => $d['no_program'],
            'no_kegiatan' => $d['no_kegiatan'],
            'terima_dari' => $d['terima_dari'],
            'jumlah_terbilang' => $d['jumlah_terbilang'],
            'uraian' => $d['uraian'],
            'terbilang_rp' => $d['terbilang_rp'],
            'tempat_tanggal' => $d['tempat_tanggal'],
            'ttd_kpa_nama' => $d['kpa_nama'],
            'ttd_kpa_nip' => $d['kpa_nip'] !== '' ? ('NIP. ' . $d['kpa_nip']) : '',
            'ttd_bendahara_nama' => $d['bendahara_nama'],
            'ttd_bendahara_nip' => $d['bendahara_nip'] !== '' ? ('NIP. ' . $d['bendahara_nip']) : '',
            'ttd_penerima_nama' => $d['penerima_nama'],
        ];
    }

    /**
     * Render dokumen PDF kuitansi (objek FPDF sebelum di-Output).
     */
    private function renderKuitansi(array $trx): \FPDF
    {
        $d = $this->buildData($trx);
        $pdf = $this->newPdf();

        $this->field($pdf, 'no_bku', $d['no_bku']);
        $this->field($pdf, 'no_program', $d['no_program']);
        $this->field($pdf, 'no_kegiatan', $d['no_kegiatan']);
        $this->field($pdf, 'terima_dari', $d['terima_dari']);
        $this->field($pdf, 'jumlah_terbilang', $d['jumlah_terbilang']);
        $this->field($pdf, 'uraian', $d['uraian'], true);
        $this->field($pdf, 'terbilang_rp', $d['terbilang_rp']);
        $this->field($pdf, 'tempat_tanggal', $d['tempat_tanggal']);

        $this->ttdLine($pdf, 'ttd_kpa_nama', $d['kpa_nama'] !== '' ? $d['kpa_nama'] : '........................', 'U');
        $this->ttdLine($pdf, 'ttd_kpa_nip', $d['kpa_nip'] !== '' ? ('NIP. ' . $d['kpa_nip']) : '');
        $this->ttdLine($pdf, 'ttd_bendahara_nama', $d['bendahara_nama'] !== '' ? $d['bendahara_nama'] : '........................', 'U');
        $this->ttdLine($pdf, 'ttd_bendahara_nip', $d['bendahara_nip'] !== '' ? ('NIP. ' . $d['bendahara_nip']) : '');
        $this->ttdLine($pdf, 'ttd_penerima_nama', $d['penerima_nama'] !== '' ? $d['penerima_nama'] : '........................', 'U');

        return $pdf;
    }

    /**
     * Generate PDF kuitansi untuk satu transaksi. Stream langsung ke browser (print).
     * Koordinat dari kalibrasi_kuitansi_elemen (di-inject via setPositions).
     */
    public function streamKuitansi(array $trx): void
    {
        $pdf = $this->renderKuitansi($trx);
        $fname = 'kuitansi_' . preg_replace('/[^A-Za-z0-9-_]+/', '_', (string) ($trx['nomor_bukti'] ?? $trx['id'] ?? 'transaksi')) . '.pdf';
        $pdf->Output('I', $fname);
    }

    /**
     * Simpan PDF kuitansi ke file lokal di server (untuk silent print / SumatraPDF).
     */
    public function savePdfKuitansi(array $trx, string $path): void
    {
        $pdf = $this->renderKuitansi($trx);
        $pdf->Output('F', $path);
    }

    /**
     * "Cetak Uji": crosshair/garis tipis di posisi tiap elemen dari STATE KANVAS
     * client (belum tentu tersimpan). Dummy — bukan data transaksi asli.
     */
    public function streamTestPage(?array $positions = null): void
    {
        if (is_array($positions)) {
            $this->setPositions($positions);
        }
        $pdf = $this->newPdf();
        $pageW = (float) ($this->koordinat['page']['width_mm'] ?? 215);
        $pageH = (float) ($this->koordinat['page']['height_mm'] ?? 165);

        $pdf->SetDrawColor(150, 150, 150);
        $pdf->Rect(2, 2, $pageW - 4, $pageH - 4);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetXY(5, 4);
        $pdf->Cell(200, 5, kuitansi_pdf_text('UJI KALIBRASI KUITANSI 215x165mm (per-elemen)'));

        $labels = [
            'no_bku' => 'No. BKU/HAL', 'no_program' => 'No. Program/Kegiatan',
            'no_kegiatan' => 'No. Kegiatan', 'terima_dari' => 'Terima dari',
            'jumlah_terbilang' => 'Jumlah (terbilang)', 'uraian' => 'Untuk Pembayaran (uraian)',
            'terbilang_rp' => 'Terbilang Rp (angka)', 'tempat_tanggal' => 'Tempat & tanggal',
            'ttd_kpa_nama' => 'TTD KPA - Nama',
            'ttd_kpa_nip' => 'TTD KPA - NIP',
            'ttd_bendahara_nama' => 'TTD Bendahara - Nama', 'ttd_bendahara_nip' => 'TTD Bendahara - NIP',
            'ttd_penerima_nama' => 'TTD Penerima - Nama',
        ];

        foreach (self::ELEMEN_KEYS as $key) {
            $p = $this->pos[$key] ?? ['x_mm' => 0, 'y_mm' => 0];
            $x = (float) $p['x_mm'];
            $y = (float) $p['y_mm'];
            $w = $this->widthOf($key);
            $h = ($key === 'uraian') ? 24 : 6;

            $pdf->SetDrawColor(200, 30, 30);
            $pdf->Rect($x, $y, $w, $h);

            $r = 3;
            $pdf->Line($x - $r, $y, $x + $r, $y);
            $pdf->Line($x, $y - $r, $x, $y + $r);

            $pdf->SetFont('Arial', '', 7);
            $pdf->SetTextColor(200, 30, 30);
            $pdf->SetXY($x + 1, $y + 1);
            $pdf->Cell($w - 2, 4, kuitansi_pdf_text(($labels[$key] ?? $key) . sprintf(' (%g,%g)', $x, $y)));
            $pdf->SetTextColor(0, 0, 0);
        }

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetXY(5, $pageH - 10);
        $pdf->Cell(200, 5, kuitansi_pdf_text('Bandingkan dengan kertas NCR fisik, geser elemen di kanvas, ulangi sampai pas, lalu Simpan.'));

        $pdf->Output('I', 'uji-kalibrasi-kuitansi.pdf');
    }
}
