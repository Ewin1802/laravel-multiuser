<?php

namespace App\Support;

/**
 * Barcode produk tanpa paket tambahan.
 *
 * - internal($id)  → kode toko EAN-13 berawalan "20" (rentang 20–29 memang
 *                    disediakan untuk pemakaian internal toko, tidak bentrok
 *                    dengan barcode pabrik).
 * - svg($kode)     → gambar SVG: EAN-13 / UPC-A kalau angkanya valid,
 *                    selain itu Code 128 (bisa huruf, angka, tanda baca).
 */
class Barcode
{
    public const INTERNAL_PREFIX = '20';

    // ============================================================
    // KODE TOKO
    // ============================================================

    public static function internal(int $id): string
    {
        $body = self::INTERNAL_PREFIX . str_pad((string) $id, 10, '0', STR_PAD_LEFT);

        return $body . self::ean13CheckDigit($body);
    }

    public static function isInternal(?string $code): bool
    {
        return $code !== null
            && str_starts_with($code, self::INTERNAL_PREFIX)
            && self::isEan13($code);
    }

    public static function ean13CheckDigit(string $twelve): int
    {
        $sum = 0;

        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $twelve[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10;
    }

    public static function isEan13(?string $code): bool
    {
        return $code !== null
            && preg_match('/^\d{13}$/', $code) === 1
            && (int) $code[12] === self::ean13CheckDigit(substr($code, 0, 12));
    }

    /** Bersihkan hasil ketik/scan: spasi di tepi dibuang, karakter tak terlihat ditolak. */
    public static function normalize(?string $code): ?string
    {
        $code = trim((string) $code);

        return $code === '' ? null : $code;
    }

    // ============================================================
    // SVG
    // ============================================================

    /**
     * @param  float  $height  tinggi batang dalam satuan modul (lebar batang tertipis = 1)
     */
    public static function svg(string $code, float $height = 40): string
    {
        // UPC-A (12 digit, umum di kemasan Amerika) = EAN-13 dengan angka 0 di depan
        if (preg_match('/^\d{12}$/', $code) && self::isEan13('0' . $code)) {
            $code = '0' . $code;
        }

        $bars = self::isEan13($code) ? self::ean13Modules($code) : self::code128Modules($code);

        if ($bars === null) {
            return '';
        }

        $quiet = 10;
        $width = strlen($bars) + $quiet * 2;
        $rects = '';
        $x = 0;
        $len = strlen($bars);

        while ($x < $len) {
            if ($bars[$x] === '1') {
                $start = $x;

                while ($x < $len && $bars[$x] === '1') {
                    $x++;
                }

                $rects .= sprintf(
                    '<rect x="%d" y="0" width="%d" height="%s"/>',
                    $start + $quiet,
                    $x - $start,
                    $height
                );
            } else {
                $x++;
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %s" preserveAspectRatio="none" shape-rendering="crispEdges" role="img" aria-label="Barcode %s"><rect width="100%%" height="100%%" fill="#fff"/><g fill="#000">%s</g></svg>',
            $width,
            $height,
            htmlspecialchars($code, ENT_QUOTES),
            $rects
        );
    }

    // ============================================================
    // EAN-13
    // ============================================================

    private const EAN_L = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];
    private const EAN_G = ['0100111', '0110011', '0011011', '0100001', '0011101', '0111001', '0000101', '0010001', '0001001', '0010111'];
    private const EAN_R = ['1110010', '1100110', '1101100', '1000010', '1011100', '1001110', '1010000', '1000100', '1001000', '1110100'];
    private const EAN_PARITY = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL'];

    private static function ean13Modules(string $code): string
    {
        $parity = self::EAN_PARITY[(int) $code[0]];
        $out = '101';

        for ($i = 1; $i <= 6; $i++) {
            $digit = (int) $code[$i];
            $out .= $parity[$i - 1] === 'L' ? self::EAN_L[$digit] : self::EAN_G[$digit];
        }

        $out .= '01010';

        for ($i = 7; $i <= 12; $i++) {
            $out .= self::EAN_R[(int) $code[$i]];
        }

        return $out . '101';
    }

    // ============================================================
    // CODE 128 (B untuk teks, C untuk angka berpasangan)
    // ============================================================

    private const C128 = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const C128_START_B = 104;
    private const C128_START_C = 105;
    private const C128_CODE_B = 100;
    private const C128_STOP = 106;

    private static function code128Modules(string $code): ?string
    {
        // hanya ASCII yang bisa dicetak
        if ($code === '' || preg_match('/^[\x20-\x7E]+$/', $code) !== 1) {
            return null;
        }

        $values = [];

        if (preg_match('/^\d+$/', $code) && strlen($code) % 2 === 0 && strlen($code) >= 4) {
            // angka genap → set C (2 digit per simbol, barcode jadi pendek)
            $values[] = self::C128_START_C;

            foreach (str_split($code, 2) as $pair) {
                $values[] = (int) $pair;
            }
        } else {
            $values[] = self::C128_START_B;

            foreach (str_split($code) as $char) {
                $values[] = ord($char) - 32;
            }
        }

        $checksum = $values[0];

        for ($i = 1, $n = count($values); $i < $n; $i++) {
            $checksum += $values[$i] * $i;
        }

        $values[] = $checksum % 103;
        $values[] = self::C128_STOP;

        $out = '';

        foreach ($values as $value) {
            $bar = true;

            foreach (str_split(self::C128[$value]) as $w) {
                $out .= str_repeat($bar ? '1' : '0', (int) $w);
                $bar = ! $bar;
            }
        }

        return $out;
    }
}
