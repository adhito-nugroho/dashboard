<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Logika murni (tanpa DB) untuk form rincian biaya perjalanan dinas:
 * komponen default dan parsing/validasi input POST menjadi struktur
 * siap simpan. Dipisah dari controller agar dapat diuji tanpa DB.
 */
class RincianBiayaInputService
{
    /**
     * Komponen default untuk form baru.
     *
     * @return array<int, array<string, mixed>>
     */
    public function defaultKomponen(): array
    {
        return [
            ['nama_komponen' => 'Uang Harian',  'harga_satuan' => 0, 'jumlah_hari' => 1, 'jumlah' => 0, 'keterangan' => ''],
            ['nama_komponen' => 'BBM',           'harga_satuan' => 0, 'jumlah_hari' => null, 'jumlah' => 0, 'keterangan' => ''],
            ['nama_komponen' => 'Tol',           'harga_satuan' => 0, 'jumlah_hari' => null, 'jumlah' => 0, 'keterangan' => ''],
            ['nama_komponen' => 'Hotel',         'harga_satuan' => 0, 'jumlah_hari' => null, 'jumlah' => 0, 'keterangan' => ''],
        ];
    }

    /**
     * Validasi POST dan kembalikan struktur siap simpan.
     *
     * @param array<string, mixed> $post
     * @return array{errors: array<int, string>, details: array<int, array<string, mixed>>, header: array<string, mixed>}
     */
    public function parse(array $post): array
    {
        $errors  = [];
        $details = [];

        $ditetapkan = (float) str_replace(['.', ','], ['', '.'], $post['ditetapkan_sejumlah'] ?? '0');
        $dibayar    = (float) str_replace(['.', ','], ['', '.'], $post['dibayar_semula'] ?? '0');
        $tempatTgl  = trim($post['tempat_tanggal'] ?? '') ?: null;

        // Validasi detail komponen
        $komponenList = $post['komponen'] ?? [];
        if (empty($komponenList) || !is_array($komponenList)) {
            $errors[] = 'Minimal harus ada satu baris komponen biaya.';
        } else {
            $adaIsiValid = false;
            foreach ($komponenList as $i => $k) {
                $nama = trim((string) ($k['nama_komponen'] ?? ''));
                if ($nama === '') {
                    continue; // skip baris kosong
                }

                $harga     = (float) str_replace(['.', ','], ['', '.'], $k['harga_satuan'] ?? '0');
                $hari      = isset($k['jumlah_hari']) && $k['jumlah_hari'] !== '' ? (float) $k['jumlah_hari'] : null;
                $jumlahRaw = str_replace(['.', ','], ['', '.'], $k['jumlah'] ?? '0');
                $jumlah    = (float) $jumlahRaw;

                if ($harga <= 0) {
                    $errors[] = 'Baris ' . ($i + 1) . " ($nama): Harga satuan harus lebih dari 0.";
                    continue;
                }
                if ($jumlah <= 0) {
                    // Hitung ulang dari harga * hari
                    $jumlah = $hari !== null ? $harga * $hari : $harga;
                }

                $details[] = [
                    'nama_komponen' => $nama,
                    'harga_satuan'  => $harga,
                    'jumlah_hari'   => $hari,
                    'jumlah'        => $jumlah,
                    'keterangan'    => trim((string) ($k['keterangan'] ?? '')) ?: null,
                ];
                $adaIsiValid = true;
            }
            if (!$adaIsiValid && empty($errors)) {
                $errors[] = 'Minimal harus ada satu baris komponen biaya yang terisi.';
            }
        }

        $header = [
            'ditetapkan_sejumlah' => $ditetapkan,
            'dibayar_semula'      => $dibayar,
            'tempat_tanggal'      => $tempatTgl,
        ];

        return ['errors' => $errors, 'details' => $details, 'header' => $header];
    }
}
