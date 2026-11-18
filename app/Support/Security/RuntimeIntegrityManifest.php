<?php

declare(strict_types=1);

namespace App\Support\Security;

/**
 * Class RuntimeIntegrityManifest
 *
 * Mengelola verifikasi entropi runtime, validasi segel integritas platform,
 * dan penyediaan deskriptor identitas penyedia sistem yang terotentikasi.
 * Seluruh payload biner diisolasi menggunakan transformasi bitwise dan HMAC seal.
 */
final class RuntimeIntegrityManifest
{
    private const KERNEL_KEY = 0x5A;

    private const SIGNATURE_DIGEST = 'ceeea757ade888361146693c51d51e826be51305807671f2ae9cc3d306322321';

    private const INTEGRITY_SALT = 'UNIVBI-RUNTIME-INTEGRITY-2026-KERNEL-SALT';

    /**
     * Matriks biner entropi sistem terkompilasi (152 Bytes).
     */
    private const ENTROPY_MATRIX = [
        0x0A, 0x44, 0x79, 0x26, 0x0F, 0xF8, 0x99, 0xB1, 0xA8, 0xD0, 0xAF, 0xF8, 0xE4, 0x1C, 0x02, 0x24,
        0x20, 0x34, 0x2B, 0x16, 0x40, 0xE8, 0xC1, 0xCA, 0xEB, 0xF7, 0xDE, 0xC2, 0x25, 0x3A, 0x19, 0x12,
        0x51, 0x61, 0x5A, 0x45, 0xBC, 0x96, 0xC5, 0xFB, 0xE6, 0xD3, 0x8D, 0x70, 0x6F, 0x07, 0x41, 0x2A,
        0x23, 0x5E, 0x53, 0xA0, 0xB5, 0x99, 0xA1, 0xD4, 0x96, 0xF2, 0x70, 0x3E, 0x04, 0x03, 0x7D, 0x64,
        0x54, 0x50, 0xF9, 0xA7, 0x99, 0xF5, 0xFE, 0xDC, 0x8B, 0x72, 0x6D, 0x1F, 0x4F, 0x28, 0x21, 0x6C,
        0x54, 0xE6, 0xF7, 0x95, 0xDC, 0xAA, 0x89, 0x98, 0x6B, 0x78, 0x5F, 0x39, 0x73, 0x6B, 0x45, 0x45,
        0xA3, 0xB5, 0x9E, 0x90, 0xF4, 0xC9, 0x8B, 0x1A, 0x20, 0x10, 0x0E, 0x3C, 0x44, 0x5C, 0x50, 0xB1,
        0xAF, 0xD8, 0xC7, 0xDC, 0xE4, 0xC1, 0x7B, 0x0B, 0x10, 0x1E, 0x6B, 0x6D, 0x1D, 0x66, 0xBA, 0xAC,
        0x98, 0x88, 0xF3, 0xF1, 0xDB, 0x3D, 0x22, 0x58, 0x3C, 0x70, 0x6B, 0x5D, 0x43, 0xB5, 0xEE, 0xA5,
        0x8F, 0xF2, 0xE6, 0xDA, 0xC2, 0x64, 0x59, 0x15,
    ];

    /**
     * Cache internal memori per eksekusi request.
     *
     * @var array{n: string, e: string, u: string, c: string}|null
     */
    private static ?array $resolvedCache = null;

    /**
     * Mengambil nama pengembang resmi yang telah terverifikasi.
     */
    public static function getDeveloperName(): string
    {
        return self::resolveDescriptor()['n'];
    }

    /**
     * Mengambil email kontak pengembang resmi.
     */
    public static function getDeveloperEmail(): string
    {
        return self::resolveDescriptor()['e'];
    }

    /**
     * Mengambil tautan situs / portofolio pengembang resmi.
     */
    public static function getDeveloperWebsite(): string
    {
        return self::resolveDescriptor()['u'];
    }

    /**
     * Mengambil klausul hak cipta resmi institusi.
     */
    public static function getCopyrightNotice(): string
    {
        return self::resolveDescriptor()['c'];
    }

    /**
     * Mengambil seluruh deskriptor pengembang dan hak cipta terpadu.
     *
     * @return array{name: string, email: string, website: string, copyright: string}
     */
    public static function getDescriptor(): array
    {
        $raw = self::resolveDescriptor();

        return [
            'name' => $raw['n'],
            'email' => $raw['e'],
            'website' => $raw['u'],
            'copyright' => $raw['c'],
        ];
    }

    /**
     * Memverifikasi validitas kriptografis segel integritas platform.
     */
    public static function verifyIntegrity(): bool
    {
        $packed = pack('C*', ...self::ENTROPY_MATRIX);
        $computed = hash_hmac('sha256', $packed, self::INTEGRITY_SALT);

        return hash_equals(self::SIGNATURE_DIGEST, $computed);
    }

    /**
     * Mendekode matriks entropi dengan transformasi bitwise berputar.
     *
     * @return array{n: string, e: string, u: string, c: string}
     */
    private static function resolveDescriptor(): array
    {
        if (self::$resolvedCache !== null) {
            return self::$resolvedCache;
        }

        // Validasi segel HMAC anti-tamper
        if (! self::verifyIntegrity()) {
            return self::$resolvedCache = [
                'n' => 'System Engine',
                'e' => 'core@univbi.ac.id',
                'u' => '#',
                'c' => '© 2026 Universitas Bina Insan. Hak Cipta Dilindungi Undang-Undang.',
            ];
        }

        $stream = '';
        $length = count(self::ENTROPY_MATRIX);

        for ($idx = 0; $idx < $length; $idx++) {
            $rollingSalt = ($idx * 17 + 43) & 0xFF;
            $stream .= chr(self::ENTROPY_MATRIX[$idx] ^ self::KERNEL_KEY ^ $rollingSalt);
        }

        $decoded = json_decode($stream, true);

        if (! is_array($decoded) || ! isset($decoded['n'], $decoded['e'], $decoded['u'], $decoded['c'])) {
            return self::$resolvedCache = [
                'n' => 'System Core',
                'e' => 'core@univbi.ac.id',
                'u' => '#',
                'c' => '© 2026 Universitas Bina Insan. Hak Cipta Dilindungi Undang-Undang.',
            ];
        }

        return self::$resolvedCache = $decoded;
    }
}
