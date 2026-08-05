<?php

namespace App\Services\Reports;

use Illuminate\Http\Response;

class SimplePdfReportService
{
    /**
     * @param array<int, string> $meta
     * @param array<int, array{label:string,width:float,align?:string}> $columns
     * @param iterable<int, array<int, scalar|null>> $rows
     */
    public function download(
        string $filename,
        string $institution,
        string $title,
        array $meta,
        array $columns,
        iterable $rows,
        string $orientation = 'landscape',
    ): Response {
        $landscape = $orientation === 'landscape';
        $pageWidth = $landscape ? 842.0 : 595.0;
        $pageHeight = $landscape ? 595.0 : 842.0;
        $margin = 28.0;
        $usableWidth = $pageWidth - ($margin * 2);
        $sumWidth = array_sum(array_column($columns, 'width')) ?: 1.0;

        foreach ($columns as &$column) {
            $column['width'] = ($column['width'] / $sumWidth) * $usableWidth;
        }
        unset($column);

        $pages = [];
        $content = [];
        $cursorY = 0.0;

        $startPage = function () use (&$content, &$cursorY, $pageHeight, $margin, $institution, $title, $meta, $columns): void {
            $content = [];
            $cursorY = $pageHeight - $margin;
            $content[] = $this->text($margin, $cursorY, 14, $institution, true);
            $cursorY -= 20;
            $content[] = $this->text($margin, $cursorY, 12, $title, true);
            $cursorY -= 17;

            foreach ($meta as $line) {
                $content[] = $this->text($margin, $cursorY, 8, $line);
                $cursorY -= 12;
            }

            $cursorY -= 4;
            $x = $margin;
            $headerHeight = 22.0;
            foreach ($columns as $column) {
                $content[] = $this->rect($x, $cursorY - $headerHeight, $column['width'], $headerHeight, true);
                $content[] = $this->text($x + 4, $cursorY - 14, 7.5, $column['label'], true);
                $x += $column['width'];
            }
            $cursorY -= $headerHeight;
        };

        $finishPage = function () use (&$pages, &$content): void {
            $pages[] = implode("\n", $content);
        };

        $startPage();
        $rowNumber = 0;

        foreach ($rows as $row) {
            $rowNumber++;
            $wrappedCells = [];
            $maxLines = 1;

            foreach ($columns as $index => $column) {
                $value = $row[$index] ?? '';
                $maxChars = max((int) floor($column['width'] / 4.4), 4);
                $lines = $this->wrap((string) $value, $maxChars);
                $wrappedCells[] = $lines;
                $maxLines = max($maxLines, count($lines));
            }

            $rowHeight = max(20.0, ($maxLines * 9.0) + 8.0);
            if ($cursorY - $rowHeight < $margin + 18) {
                $finishPage();
                $startPage();
            }

            $x = $margin;
            foreach ($columns as $index => $column) {
                $content[] = $this->rect($x, $cursorY - $rowHeight, $column['width'], $rowHeight, false);
                $lineY = $cursorY - 12;
                foreach ($wrappedCells[$index] as $line) {
                    $content[] = $this->text($x + 4, $lineY, 7, $line);
                    $lineY -= 9;
                }
                $x += $column['width'];
            }
            $cursorY -= $rowHeight;
        }

        if ($rowNumber === 0) {
            $content[] = $this->text($margin, $cursorY - 18, 9, 'Tidak ada data yang sesuai dengan filter.');
        }

        $finishPage();
        $pdf = $this->buildPdf($pages, $pageWidth, $pageHeight);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($pdf),
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function buildPdf(array $pages, float $pageWidth, float $pageHeight): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $kids = [];
        $objectNumber = 5;
        foreach ($pages as $stream) {
            $pageObject = $objectNumber++;
            $contentObject = $objectNumber++;
            $kids[] = $pageObject.' 0 R';
            $objects[$pageObject] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                $pageWidth,
                $pageHeight,
                $contentObject,
            );
            $objects[$contentObject] = '<< /Length '.strlen($stream).' >>' . "\nstream\n" . $stream . "\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $maxObject = max(array_keys($objects));
        $pdf .= "xref\n0 ".($maxObject + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObject; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= 'trailer << /Size '.($maxObject + 1).' /Root 1 0 R >>'."\n";
        $pdf .= "startxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    private function text(float $x, float $y, float $size, string $text, bool $bold = false): string
    {
        return sprintf(
            'BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET',
            $bold ? 'F2' : 'F1',
            $size,
            $x,
            $y,
            $this->escape($text),
        );
    }

    private function rect(float $x, float $y, float $width, float $height, bool $filled): string
    {
        if ($filled) {
            return sprintf('q 0.93 g %.2F %.2F %.2F %.2F re B Q', $x, $y, $width, $height);
        }

        return sprintf('q 0.78 G 0.35 w %.2F %.2F %.2F %.2F re S Q', $x, $y, $width, $height);
    }

    /** @return array<int, string> */
    private function wrap(string $value, int $maxChars): array
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if ($value === '') {
            return ['-'];
        }

        $wrapped = wordwrap($value, $maxChars, "\n", true);
        return array_values(array_filter(explode("\n", $wrapped), static fn (string $line): bool => $line !== ''));
    }

    private function escape(string $value): string
    {
        $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        $converted = $converted === false ? $value : $converted;

        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $converted);
    }
}
