<?php
/**
 * Konfigurasi export CSV Bank Jatim untuk Transfer Perjalanan Dinas.
 */
return [
    // Rekening sumber transfer. Bila DB SQLite TPP tersedia, nilai
    // app_setting.rekening_sumber dari TPP dipakai lebih dulu; ini fallback server.
    'rekening_sumber' => getenv('BANK_JATIM_REKENING_SUMBER') ?: '0081051470',

    // DB SQLite aplikasi TPP (dibaca bila ada di mesin ini).
    'tpp_db_path' => getenv('TPP_DB_PATH') ?: 'D:/laragon/www/tpp/data/sigap_tpp.db',

    // Salinan rekening pegawai untuk server, hasil tools/export_rekening_tpp.php.
    'rekening_json_path' => __DIR__ . '/../storage/rekening_pegawai.json',

    // Teks keterangan pada baris CSV (tanpa koma).
    'keterangan' => 'perjadin',
];
