<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Export CSV transfer Bank Jatim untuk batch Transfer Perjalanan Dinas.
 *
 * Format mengikuti aplikasi TPP:
 *   Baris 1 : rekening_sumber,total_transfer,jumlah_penerima
 *   Baris 2+: no_rekening,nama,nominal,keterangan,,,
 *
 * Satu baris CSV = satu penerima transfer (grup Transfer Ke), bukan per
 * transaksi. Nominal = penerimaan bruto (nilai) yang dijumlahkan per penerima.
 * Potongan TIDAK dikurangkan di CSV karena dipotong masing-masing pegawai
 * setelah dana diterima.
 */
class BankJatimTransferService
{
    private string $sourceAccount;
    private string $tppDbPath;
    private string $jsonPath;
    private string $keterangan;

    public function __construct(array $config)
    {
        $this->sourceAccount = trim((string) ($config['rekening_sumber'] ?? ''));
        $this->tppDbPath = trim((string) ($config['tpp_db_path'] ?? ''));
        $this->jsonPath = trim((string) ($config['rekening_json_path'] ?? ''));
        $this->keterangan = trim((string) ($config['keterangan'] ?? 'perjadin'));
    }

    /**
     * @param array<int, array{uraian?:string,nama?:string,nilai?:float,transfer_ke?:string,potongan?:float,pegawai_nip?:string}> $rows
     */
    public function buildCsv(array $rows): string
    {
        $groups = (new TransferPerjadinExportService())->groupByTransferKe($rows);
        if ($groups === []) {
            throw new \RuntimeException('Belum ada transaksi perjalanan dinas yang dikonfigurasi transfernya.');
        }

        $accounts = $this->loadAccounts();
        $source = $this->resolveSourceAccount();
        $keterangan = str_replace([',', "\r", "\n"], ' ', $this->keterangan);
        $keterangan = trim($keterangan) !== '' ? trim($keterangan) : 'perjadin';

        // NIP penerima hanya bisa dipakai bila Transfer Ke = nama penerima
        // transaksi. Untuk koordinator, rekening dicari berdasarkan nama.
        $nipByRecipient = [];
        foreach ($rows as $r) {
            $ke = trim((string) ($r['transfer_ke'] ?? ''));
            $nama = trim((string) ($r['nama'] ?? ''));
            $nip = preg_replace('/\D/', '', (string) ($r['pegawai_nip'] ?? '')) ?? '';
            if ($ke === '' || $nama === '' || $nip === '') {
                continue;
            }
            if ($this->normName($ke) === $this->normName($nama)) {
                $nipByRecipient[$this->normName($ke)] = $nip;
            }
        }

        $recipients = [];
        foreach ($groups as $g) {
            $label = trim((string) ($g['label'] ?? ''));
            if ($label === '' || $label === '(Belum ditentukan)') {
                throw new \RuntimeException('Masih ada baris dengan Transfer Ke kosong. Lengkapi konfigurasi transfer terlebih dahulu.');
            }

            $nominal = (float) ($g['penerimaan'] ?? 0);
            if ($nominal < 0) {
                throw new \RuntimeException('Nominal transfer negatif untuk ' . $label . '.');
            }

            // Gabungkan label yang hanya berbeda tanda baca, agar tidak ada
            // dua baris CSV untuk rekening yang sama.
            $key = $this->normName($label);
            if (!isset($recipients[$key])) {
                $recipients[$key] = ['label' => $label, 'nominal' => 0.0];
            }
            $recipients[$key]['nominal'] += $nominal;
        }

        $missing = [];
        $lines = [];
        $total = 0;
        $count = 0;

        foreach ($recipients as $key => $recipient) {
            $label = $recipient['label'];
            $nominal = (int) round((float) $recipient['nominal']);
            if ($nominal < 0) {
                throw new \RuntimeException('Nominal transfer negatif untuk ' . $label . '.');
            }

            $account = $this->resolveAccount($accounts, $label, $nipByRecipient[$key] ?? '');
            if ($account === null) {
                $missing[] = $label;
                continue;
            }
            if (!preg_match('/^\d+$/', $account)) {
                throw new \RuntimeException('Nomor rekening tidak valid untuk ' . $label . '.');
            }

            $lines[] = $account . ',' . $this->cleanName($label) . ',' . $nominal . ',' . $keterangan . ',,,';
            $total += $nominal;
            $count++;
        }

        if ($missing !== []) {
            $missing = array_values(array_unique($missing));
            throw new \RuntimeException(
                'Rekening Bank Jatim belum ditemukan untuk: ' . implode(', ', $missing)
                . '. Lengkapi data rekening pegawai lalu coba lagi.'
            );
        }
        if ($count === 0) {
            throw new \RuntimeException('Tidak ada baris transfer yang bisa dibuat.');
        }

        return $source . ',' . $total . ',' . $count . "\n" . implode("\n", $lines);
    }

    /**
     * @param array<int, array{uraian?:string,nama?:string,nilai?:float,transfer_ke?:string,potongan?:float,pegawai_nip?:string}> $rows
     */
    public function download(array $rows, string $batchName): void
    {
        $csv = $this->buildCsv($rows);
        $filename = 'BankJatim_Transfer_Perjadin_'
            . TransferPerjadinExportService::safeFilename($batchName)
            . '_' . date('Ymd_His') . '.csv';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($csv));
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        echo $csv;
        exit;
    }

    /**
     * @return array{by_nip:array<string,string>, by_name:array<string,string>, ambiguous:array<string,bool>}
     */
    private function loadAccounts(): array
    {
        $rows = [];

        // Prioritas 1: DB SQLite TPP (biasanya ada di laptop, bukan server).
        if ($this->tppDbPath !== '' && is_file($this->tppDbPath)) {
            try {
                $pdo = new \PDO('sqlite:' . $this->tppDbPath);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $rows = $pdo->query("
                    SELECT nip, nama, no_rekening, status_aktif
                    FROM pegawai
                    WHERE no_rekening IS NOT NULL AND TRIM(no_rekening) <> ''
                ")->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                error_log('BankJatimTransferService: gagal membaca DB TPP: ' . $e->getMessage());
                $rows = [];
            }
        }

        // Prioritas 2: salinan JSON di server.
        if ($rows === [] && $this->jsonPath !== '' && is_file($this->jsonPath)) {
            $json = json_decode((string) file_get_contents($this->jsonPath), true);
            if (is_array($json)) {
                $rows = $json;
            }
        }

        if ($rows === []) {
            throw new \RuntimeException(
                'Data rekening pegawai belum tersedia. Jalankan tools/export_rekening_tpp.php '
                . 'lalu unggah storage/rekening_pegawai.json ke server.'
            );
        }

        $byNip = [];
        $byName = [];
        $ambiguous = [];
        foreach ($rows as $r) {
            $nip = preg_replace('/\D/', '', (string) ($r['nip'] ?? '')) ?? '';
            $nama = trim((string) ($r['nama'] ?? ''));
            $rekening = trim((string) ($r['no_rekening'] ?? ''));
            if ($rekening === '') {
                continue;
            }
            if ($nip !== '') {
                $byNip[$nip] = $rekening;
            }
            $key = $this->normName($nama);
            if ($key === '') {
                continue;
            }
            if (!isset($byName[$key])) {
                $byName[$key] = $rekening;
            } elseif ($byName[$key] !== $rekening) {
                $ambiguous[$key] = true;
            }
        }

        return ['by_nip' => $byNip, 'by_name' => $byName, 'ambiguous' => $ambiguous];
    }

    /**
     * @param array{by_nip:array<string,string>, by_name:array<string,string>, ambiguous:array<string,bool>} $accounts
     */
    private function resolveAccount(array $accounts, string $label, string $nip): ?string
    {
        $nip = trim($nip);
        if ($nip !== '' && isset($accounts['by_nip'][$nip])) {
            return $accounts['by_nip'][$nip];
        }

        $key = $this->normName($label);
        if ($key === '' || isset($accounts['ambiguous'][$key])) {
            return null;
        }
        return $accounts['by_name'][$key] ?? null;
    }

    private function resolveSourceAccount(): string
    {
        $source = $this->sourceAccount;

        // Samakan dengan pengaturan TPP bila DB-nya tersedia.
        if ($this->tppDbPath !== '' && is_file($this->tppDbPath)) {
            try {
                $pdo = new \PDO('sqlite:' . $this->tppDbPath);
                $stmt = $pdo->prepare("SELECT value FROM app_setting WHERE key = 'rekening_sumber'");
                $stmt->execute();
                $value = trim((string) $stmt->fetchColumn());
                if ($value !== '') {
                    $source = $value;
                }
            } catch (\Throwable $e) {
                // Pakai nilai config bila tabel setting tidak bisa dibaca.
            }
        }

        $source = preg_replace('/\D/', '', $source) ?? '';
        if ($source === '') {
            throw new \RuntimeException('Rekening sumber Bank Jatim belum diatur pada config/bank_jatim.php.');
        }
        return $source;
    }

    private function normName(string $s): string
    {
        // Bandingkan nama tanpa spasi/tanda baca: "S.P." dan "SP" dianggap sama.
        $s = strtoupper(trim($s));
        return preg_replace('/[^A-Z0-9]+/', '', $s) ?? '';
    }

    private function cleanName(string $s): string
    {
        return trim(str_replace([',', '.'], '', $s));
    }
}
