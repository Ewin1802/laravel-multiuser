<?php

namespace App\Support;

use App\Models\Product;

/**
 * MENCARI PRODUK YANG NAMANYA MIRIP
 *
 * Dipakai form tambah/edit produk supaya toko tidak membuat produk dobel
 * (mis. lupa produk itu sudah pernah di-import).
 *
 * Skor 0–1, diambil yang tertinggi dari tiga cara:
 *  - tulisan dipadatkan sama persis           "Air Mineral 600 ml" = "air mineral 600ML"   → 1.0
 *  - satu nama memuat nama lain               "Aqua 600ml" ⊂ "Aqua 600ml Botol"         → 0.9
 *  - kata yang sama (Jaccard)                 "Air Mineral 600 ml" ~ "Air Mineral Aqua 600ml" → 0.75
 *  - salah ketik (Levenshtein)                "Gula Psir 1kg" ~ "Gula Pasir 1 kg"         → ± 0.9
 * Angka + satuan disatukan dulu: "600 ml" dan "600ml" dianggap sama.
 */
class ProductMatcher
{
    /** di atas skor ini dianggap "hampir pasti produk yang sama" */
    public const STRONG = 0.85;

    /** di atas skor ini ditampilkan sebagai "mirip" */
    public const WEAK = 0.6;

    /**
     * @return array<int, array{product: Product, score: float}>  urut skor tertinggi
     */
    public static function find(string $name, ?int $excludeId = null, int $limit = 5): array
    {
        $needle = self::prepare($name);
        if (mb_strlen($needle['compact']) < 3) {
            return [];
        }

        $matches = [];

        Product::query()
            ->select(['id', 'name', 'code', 'barcode', 'stock', 'base_unit', 'price', 'cost_price', 'status', 'track_expiry'])
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->orderBy('id')
            ->chunk(500, function ($products) use ($needle, &$matches) {
                foreach ($products as $product) {
                    $score = self::score($needle, self::prepare($product->name));
                    if ($score >= self::WEAK) {
                        $matches[] = ['product' => $product, 'score' => round($score, 3)];
                    }
                }
            });

        usort($matches, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($matches, 0, $limit);
    }

    public static function score(array $a, array $b): float
    {
        if ($a['compact'] === '' || $b['compact'] === '') {
            return 0.0;
        }
        if ($a['compact'] === $b['compact']) {
            return 1.0;
        }

        $best = 0.0;

        // satu nama memuat nama lain (yang pendek minimal 5 huruf supaya "Teh" tidak cocok ke semua)
        $short = strlen($a['compact']) <= strlen($b['compact']) ? $a['compact'] : $b['compact'];
        $long = $short === $a['compact'] ? $b['compact'] : $a['compact'];
        if (strlen($short) >= 5 && str_contains($long, $short)) {
            $best = 0.9;
        }

        // kata yang sama
        if ($a['tokens'] && $b['tokens']) {
            $inter = count(array_intersect($a['tokens'], $b['tokens']));
            $union = count(array_unique(array_merge($a['tokens'], $b['tokens'])));
            if ($union > 0) {
                $best = max($best, $inter / $union);
            }
        }

        // salah ketik
        $longest = max(strlen($a['compact']), strlen($b['compact']));
        if ($longest <= 255) {
            $best = max($best, 1 - levenshtein($a['compact'], $b['compact']) / $longest);
        }

        return $best;
    }

    /** @return array{compact: string, tokens: array<int, string>} */
    public static function prepare(string $name): array
    {
        $text = mb_strtolower(trim($name));
        $text = str_replace(['&', '+'], ' dan ', $text);
        // "600 ml" → "600ml", "1 kg" → "1kg", "0,5 L" → "0.5l"
        $text = preg_replace('/(\d),(\d)/', '$1.$2', $text);
        $text = preg_replace('/(\d)\s+([a-z])/u', '$1$2', $text);

        $tokens = array_values(array_unique(array_filter(
            preg_split('/[^a-z0-9.]+/u', $text),
            fn ($t) => $t !== '' && $t !== '.'
        )));

        return [
            'compact' => preg_replace('/[^a-z0-9]/', '', $text),
            'tokens' => $tokens,
        ];
    }
}
