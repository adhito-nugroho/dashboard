<?php

declare(strict_types=1);

/**
 * Normalisasi tagar: rekening tanpa tagar diisi `#PAD`.
 *
 * Di RKA SIPD, rekening tanpa tag tematik ditandai "[ # ] PAD".
 * Aplikasi menampilkan/menyimpannya sebagai `#PAD` agar konsisten.
 *
 * Catatan: $pdo tersedia dari migrate.php runner.
 */

$pdo->exec("
    UPDATE `rekening`
    SET `tagar` = '#PAD'
    WHERE `tagar` IS NULL OR TRIM(`tagar`) = ''
");
