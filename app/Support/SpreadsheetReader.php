<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Pembaca file Excel (.xlsx) dan CSV yang ringan — tanpa paket tambahan.
 * Hanya membaca SHEET PERTAMA dan mengembalikan isinya sebagai array baris.
 *
 *   $rows = SpreadsheetReader::read($path, 'xlsx');
 *   // [ ['Nama Produk', 'Kategori', ...], ['Oli MPX', 'Oli & Pelumas', ...], ... ]
 *
 * Butuh ekstensi PHP "zip" untuk .xlsx (aktif secara bawaan di XAMPP / Laragon).
 */
class SpreadsheetReader
{
    /** Batas baris supaya file raksasa tidak menghabiskan memori */
    public const MAX_ROWS = 5000;

    public static function read(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        $rows = match ($extension) {
            'xlsx' => self::readXlsx($path),
            'csv', 'txt' => self::readCsv($path),
            'xls' => throw new RuntimeException('Format .xls lama tidak didukung. Buka filenya di Excel lalu "Save As" menjadi .xlsx.'),
            default => throw new RuntimeException('Format file tidak didukung. Gunakan .xlsx atau .csv.'),
        };

        // buang baris yang benar-benar kosong
        return array_values(array_filter($rows, function (array $row) {
            foreach ($row as $cell) {
                if (trim((string) $cell) !== '') {
                    return true;
                }
            }
            return false;
        }));
    }

    // ============================================================
    // XLSX
    // ============================================================

    private static function readXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP "zip" belum aktif di server. Aktifkan extension=zip di php.ini, atau unggah file .csv.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File tidak bisa dibuka. Pastikan ini file .xlsx yang benar dan tidak rusak.');
        }

        try {
            $strings = self::sharedStrings($zip);
            $sheetXml = $zip->getFromName(self::firstSheetPath($zip));

            if ($sheetXml === false) {
                throw new RuntimeException('Sheet pertama tidak ditemukan di dalam file.');
            }

            $sheet = self::xml($sheetXml);
            $rows = [];

            foreach ($sheet->sheetData->row ?? [] as $row) {
                if (count($rows) >= self::MAX_ROWS + 1) {
                    throw new RuntimeException('File terlalu besar. Maksimal ' . self::MAX_ROWS . ' baris per sekali import.');
                }

                $cells = [];
                $next = 0;

                foreach ($row->c as $cell) {
                    // posisi kolom dari referensi sel ("C5" → 2), supaya sel kosong di tengah tidak menggeser data
                    $ref = (string) $cell['r'];
                    $index = $ref !== '' ? self::columnIndex($ref) : $next;
                    $cells[$index] = self::cellValue($cell, $strings);
                    $next = $index + 1;
                }

                if ($cells === []) {
                    $rows[] = [];
                    continue;
                }

                $filled = [];
                for ($i = 0, $max = max(array_keys($cells)); $i <= $max; $i++) {
                    $filled[] = $cells[$i] ?? '';
                }
                $rows[] = $filled;
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private static function xml(string $content): \SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new RuntimeException('Isi file tidak bisa dibaca. Coba simpan ulang dari Excel sebagai .xlsx.');
        }

        return $xml;
    }

    /** Teks di .xlsx disimpan terpisah di tabel "shared strings" */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $content = $zip->getFromName('xl/sharedStrings.xml');
        if ($content === false) {
            return [];
        }

        $strings = [];
        foreach (self::xml($content)->si as $item) {
            $strings[] = self::richText($item);
        }

        return $strings;
    }

    /** Gabungkan teks biasa (<t>) maupun teks berformat (<r><t>) */
    private static function richText(\SimpleXMLElement $node): string
    {
        if (isset($node->t)) {
            return (string) $node->t;
        }

        $text = '';
        foreach ($node->r as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    private static function firstSheetPath(ZipArchive $zip): string
    {
        $default = 'xl/worksheets/sheet1.xml';

        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $rels === false) {
            return $default;
        }

        $sheet = self::xml($workbook)->sheets->sheet[0] ?? null;
        if (! $sheet) {
            return $default;
        }

        $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];

        foreach (self::xml($rels)->Relationship as $rel) {
            if ((string) $rel['Id'] === $rid) {
                $target = ltrim((string) $rel['Target'], '/');
                return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
            }
        }

        return $default;
    }

    private static function cellValue(\SimpleXMLElement $cell, array $strings): string
    {
        $type = (string) $cell['t'];

        return match ($type) {
            's' => $strings[(int) $cell->v] ?? '',
            'inlineStr' => isset($cell->is) ? self::richText($cell->is) : '',
            'b' => ((string) $cell->v) === '1' ? 'TRUE' : 'FALSE',
            'e' => '',
            default => (string) $cell->v,
        };
    }

    /** "A" → 0, "B" → 1, "AA" → 26 */
    private static function columnIndex(string $ref): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref));
        $index = 0;

        foreach (str_split($letters) as $char) {
            $index = $index * 26 + (ord($char) - 64);
        }

        return max(0, $index - 1);
    }

    // ============================================================
    // CSV
    // ============================================================

    private static function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            throw new RuntimeException('File tidak bisa dibuka.');
        }

        try {
            $first = (string) fgets($handle);
            rewind($handle);

            // Excel Indonesia menyimpan CSV dengan titik koma
            $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
            if (substr_count($first, "\t") > max(substr_count($first, ';'), substr_count($first, ','))) {
                $delimiter = "\t";
            }

            $rows = [];
            while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                if (count($rows) >= self::MAX_ROWS + 1) {
                    throw new RuntimeException('File terlalu besar. Maksimal ' . self::MAX_ROWS . ' baris per sekali import.');
                }

                $rows[] = array_map(function ($value) {
                    $value = (string) $value;
                    // pastikan UTF-8 (CSV dari Excel lama sering Windows-1252)
                    return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
                }, $row);
            }

            // buang tanda BOM di sel pertama
            if (isset($rows[0][0])) {
                $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}
