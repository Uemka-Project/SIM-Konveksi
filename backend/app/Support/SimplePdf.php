<?php

namespace App\Support;

/**
 * Minimal dependency-free PDF writer for tabular management reports.
 *
 * The output uses the built-in Courier and Helvetica-Bold fonts so it stays
 * compatible with common PDF readers without adding a composer dependency.
 */
final class SimplePdf
{
    /**
     * @param  list<string>  $lines
     */
    public static function render(string $title, array $lines): string
    {
        $title = self::ascii($title);
        $lines = array_map([self::class, 'ascii'], array_values($lines));
        $linesPerPage = 42;
        $pages = array_chunk($lines, $linesPerPage);

        if ($pages === []) {
            $pages = [[]];
        }

        $pageCount = count($pages);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];

        $kids = [];

        for ($index = 0; $index < $pageCount; $index++) {
            $kids[] = (5 + ($index * 2)).' 0 R';
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$pageCount.' >>';

        foreach ($pages as $index => $pageLines) {
            $pageId = 5 + ($index * 2);
            $contentId = $pageId + 1;
            $stream = 'BT /F2 13 Tf 40 800 Td ('.self::escape($title).') Tj ET'."\n";
            $y = 776;

            foreach ($pageLines as $line) {
                $stream .= 'BT /F1 8 Tf 40 '.$y.' Td ('.self::escape($line).') Tj ET'."\n";
                $y -= 15;
            }

            $stream .= 'BT /F1 8 Tf 40 34 Td (Halaman '.($index + 1).' dari '
                .$pageCount.') Tj ET'."\n";

            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
                .'/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> '
                .'/Contents '.$contentId.' 0 R >>';
            $objects[$contentId] = '<< /Length '.strlen($stream).' >>'
                ."\nstream\n".$stream.'endstream';
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxId = max(array_keys($objects));
        $pdf .= "xref\n0 ".($maxId + 1)."\n0000000000 65535 f \n";

        for ($id = 1; $id <= $maxId; $id++) {
            $pdf .= isset($offsets[$id])
                ? sprintf("%010d 00000 n \n", $offsets[$id])
                : "0000000000 65535 f \n";
        }

        $pdf .= "trailer\n<< /Size ".($maxId + 1)." /Root 1 0 R >>\n"
            ."startxref\n".$xrefOffset."\n%%EOF\n";

        return $pdf;
    }

    private static function ascii(string $text): string
    {
        $text = strtr($text, [
            '—' => '-',
            '–' => '-',
            '‘' => "'",
            '’' => "'",
            '“' => '"',
            '”' => '"',
            '…' => '...',
            '·' => '-',
            '×' => 'x',
        ]);

        return preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '';
    }

    private static function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
