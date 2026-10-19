<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Identitas instansi dan pejabat penandatangan dokumen resmi
 * (SPD, Kuitansi, Rincian Biaya). Nilai KPA dapat dioverride via .env
 * (KPA_NAMA / KPA_NIP).
 */
final class Instance
{
    public const INSTANSI          = 'Cabang Dinas Kehutanan Wilayah Bojonegoro';
    public const TEMPAT_BERANGKAT  = 'Bojonegoro';
    public const KPA_NAMA_DEFAULT  = 'Endang Handayani, S.P., M.Si';
    public const KPA_NIP_DEFAULT   = '197603282000032003';

    public static function kpaNama(): string
    {
        return trim((string) ($_ENV['KPA_NAMA'] ?? getenv('KPA_NAMA') ?: self::KPA_NAMA_DEFAULT));
    }

    /** NIP KPA dalam bentuk digit saja (tanpa spasi/titik). */
    public static function kpaNip(): string
    {
        return (string) preg_replace(
            '/[^0-9]/',
            '',
            (string) ($_ENV['KPA_NIP'] ?? getenv('KPA_NIP') ?: self::KPA_NIP_DEFAULT)
        );
    }
}
