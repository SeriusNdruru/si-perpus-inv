<?php

namespace App\Services\Reports;

use DateTimeInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SimpleExcelReportService
{
    /**
     * @param array<int, string> $meta
     * @param array<int, string> $headers
     */
    public function download(
        string $filename,
        string $institution,
        string $title,
        array $meta,
        array $headers,
        iterable $rows,
        callable $mapper,
        string $sheetName = 'Laporan',
    ): BinaryFileResponse {
        $filename = preg_replace('/\.xlsx$/i', '', $filename).'.xlsx';
        $path = $this->buildWorkbook($institution, $title, $meta, $headers, $rows, $mapper, $sheetName);

        $response = new BinaryFileResponse($path, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
        $response->setContentDisposition('attachment', $filename);
        $response->deleteFileAfterSend(true);

        return $response;
    }

    /**
     * @param array<int, string> $meta
     * @param array<int, string> $headers
     */
    private function buildWorkbook(
        string $institution,
        string $title,
        array $meta,
        array $headers,
        iterable $rows,
        callable $mapper,
        string $sheetName,
    ): string {
        if ($headers === []) {
            throw new RuntimeException('Header laporan Excel tidak boleh kosong.');
        }

        $basePath = tempnam(sys_get_temp_dir(), 'rius-xlsx-');
        if ($basePath === false) {
            throw new RuntimeException('Tidak dapat membuat file sementara laporan Excel.');
        }

        @unlink($basePath);
        $zipPath = $basePath.'.zip';
        $xlsxPath = $basePath.'.xlsx';
        $dataPath = $basePath.'.rows';
        $sheetPath = $basePath.'.sheet.xml';

        $dataHandle = fopen($dataPath, 'wb');
        if ($dataHandle === false) {
            throw new RuntimeException('Tidak dapat menyiapkan data laporan Excel.');
        }

        $columnCount = count($headers);
        $widths = [];
        foreach ($headers as $index => $header) {
            $widths[$index] = min(max($this->textLength($header) + 2, 10), 48);
        }

        $rowCount = 0;
        try {
            foreach ($rows as $row) {
                $values = array_values((array) $mapper($row, $rowCount + 1));
                $values = array_slice(array_pad($values, $columnCount, null), 0, $columnCount);

                foreach ($values as $index => $value) {
                    $widths[$index] = min(max($widths[$index], $this->displayLength($value) + 2), 48);
                }

                fwrite($dataHandle, json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
                $rowCount++;
            }
        } finally {
            fclose($dataHandle);
        }

        $sheetName = $this->sanitizeSheetName($sheetName);
        $meta = array_values(array_filter(array_map(static fn ($line) => trim((string) $line), $meta), static fn ($line) => $line !== ''));
        $headerRow = 4 + count($meta);
        $lastColumn = $this->columnLetter($columnCount);
        $lastDataRow = max($headerRow + $rowCount, $headerRow);

        $sheetHandle = fopen($sheetPath, 'wb');
        if ($sheetHandle === false) {
            throw new RuntimeException('Tidak dapat membuat lembar laporan Excel.');
        }

        try {
            fwrite($sheetHandle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>');
            fwrite($sheetHandle, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">');
            fwrite($sheetHandle, '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>');
            fwrite($sheetHandle, '<dimension ref="A1:'.$lastColumn.$lastDataRow.'"/>');
            fwrite($sheetHandle, '<sheetViews><sheetView workbookViewId="0">');
            fwrite($sheetHandle, '<pane ySplit="'.$headerRow.'" topLeftCell="A'.($headerRow + 1).'" activePane="bottomLeft" state="frozen"/>');
            fwrite($sheetHandle, '<selection pane="bottomLeft" activeCell="A'.($headerRow + 1).'" sqref="A'.($headerRow + 1).'"/>');
            fwrite($sheetHandle, '</sheetView></sheetViews>');
            fwrite($sheetHandle, '<sheetFormatPr defaultRowHeight="18"/>');
            fwrite($sheetHandle, '<cols>');
            foreach ($widths as $index => $width) {
                $column = $index + 1;
                fwrite($sheetHandle, '<col min="'.$column.'" max="'.$column.'" width="'.number_format($width, 2, '.', '').'" customWidth="1"/>');
            }
            fwrite($sheetHandle, '</cols><sheetData>');

            $this->writeMergedTextRow($sheetHandle, 1, $lastColumn, $institution, 1, 26);
            $this->writeMergedTextRow($sheetHandle, 2, $lastColumn, $title, 2, 24);

            $currentRow = 3;
            foreach ($meta as $line) {
                $this->writeMergedTextRow($sheetHandle, $currentRow, $lastColumn, $line, 3, 20);
                $currentRow++;
            }

            fwrite($sheetHandle, '<row r="'.($headerRow - 1).'" ht="8" customHeight="1"/>');
            fwrite($sheetHandle, '<row r="'.$headerRow.'" ht="30" customHeight="1">');
            foreach ($headers as $index => $header) {
                $this->writeInlineCell($sheetHandle, $this->columnLetter($index + 1).$headerRow, $header, 4);
            }
            fwrite($sheetHandle, '</row>');

            $dataHandle = fopen($dataPath, 'rb');
            if ($dataHandle === false) {
                throw new RuntimeException('Tidak dapat membaca data laporan Excel.');
            }

            try {
                $excelRow = $headerRow + 1;
                while (($line = fgets($dataHandle)) !== false) {
                    $values = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    fwrite($sheetHandle, '<row r="'.$excelRow.'">');
                    foreach ($values as $index => $value) {
                        $cell = $this->columnLetter($index + 1).$excelRow;
                        $this->writeDataCell($sheetHandle, $cell, $headers[$index], $value);
                    }
                    fwrite($sheetHandle, '</row>');
                    $excelRow++;
                }
            } finally {
                fclose($dataHandle);
            }

            fwrite($sheetHandle, '</sheetData>');

            // Urutan elemen worksheet harus mengikuti skema Office Open XML.
            // autoFilter wajib ditulis sebelum mergeCells. Jika terbalik,
            // Microsoft Excel akan mencoba memperbaiki file saat dibuka.
            fwrite($sheetHandle, '<autoFilter ref="A'.$headerRow.':'.$lastColumn.$lastDataRow.'"/>');

            $mergeCount = 2 + count($meta);
            fwrite($sheetHandle, '<mergeCells count="'.$mergeCount.'">');
            fwrite($sheetHandle, '<mergeCell ref="A1:'.$lastColumn.'1"/>');
            fwrite($sheetHandle, '<mergeCell ref="A2:'.$lastColumn.'2"/>');
            for ($row = 3; $row < 3 + count($meta); $row++) {
                fwrite($sheetHandle, '<mergeCell ref="A'.$row.':'.$lastColumn.$row.'"/>');
            }
            fwrite($sheetHandle, '</mergeCells>');
            fwrite($sheetHandle, '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>');
            fwrite($sheetHandle, '<pageSetup orientation="'.($columnCount > 7 ? 'landscape' : 'portrait').'" fitToWidth="1" fitToHeight="0" paperSize="9"/>');
            fwrite($sheetHandle, '</worksheet>');
        } finally {
            fclose($sheetHandle);
        }

        try {
            // Hindari PharData untuk XLSX. Arsip ZIP yang dibuat PharData dapat
            // memiliki field versi 0.0 dan extra field Unix yang diterima oleh
            // sebagian pembaca ZIP, tetapi dapat dianggap rusak oleh Excel desktop.
            // Writer internal ini membuat ZIP standar tanpa extra field.
            $this->writeStandardZip($zipPath, [
                '[Content_Types].xml' => ['content' => $this->contentTypesXml()],
                '_rels/.rels' => ['content' => $this->rootRelationshipsXml()],
                'docProps/app.xml' => ['content' => $this->appPropertiesXml()],
                'docProps/core.xml' => ['content' => $this->corePropertiesXml($title)],
                'xl/workbook.xml' => ['content' => $this->workbookXml($sheetName)],
                'xl/_rels/workbook.xml.rels' => ['content' => $this->workbookRelationshipsXml()],
                'xl/styles.xml' => ['content' => $this->stylesXml()],
                'xl/worksheets/sheet1.xml' => ['path' => $sheetPath],
            ]);

            if (! @rename($zipPath, $xlsxPath)) {
                throw new RuntimeException('Tidak dapat menyelesaikan file laporan Excel.');
            }
        } catch (Throwable $exception) {
            @unlink($zipPath);
            @unlink($xlsxPath);
            throw $exception;
        } finally {
            @unlink($dataPath);
            @unlink($sheetPath);
        }

        return $xlsxPath;
    }

    /**
     * Membuat arsip ZIP standar untuk file XLSX tanpa ketergantungan eksternal.
     * Semua entry disimpan tanpa kompresi. Format ini sah untuk OOXML dan
     * menghindari metadata ZIP nonstandar yang memicu repair pada Excel.
     *
     * @param array<string, array{content?: string, path?: string}> $entries
     */
    private function writeStandardZip(string $targetPath, array $entries): void
    {
        $handle = fopen($targetPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Tidak dapat membuat arsip laporan Excel.');
        }

        [$dosTime, $dosDate] = $this->dosDateTime();
        $centralDirectory = '';
        $entryCount = 0;
        $offset = 0;

        try {
            foreach ($entries as $name => $source) {
                $name = str_replace('\\', '/', $name);
                if ($name === '' || str_contains($name, '../')) {
                    throw new RuntimeException('Nama entry XLSX tidak valid.');
                }

                $path = $source['path'] ?? null;
                $content = $source['content'] ?? null;

                if ($path !== null) {
                    $size = filesize($path);
                    if ($size === false) {
                        throw new RuntimeException('Tidak dapat membaca ukuran komponen XLSX.');
                    }
                    $crcHex = hash_file('crc32b', $path);
                    if ($crcHex === false) {
                        throw new RuntimeException('Tidak dapat menghitung checksum komponen XLSX.');
                    }
                } else {
                    $content = (string) $content;
                    $size = strlen($content);
                    $crcHex = hash('crc32b', $content);
                }

                if ($size > 0xFFFFFFFF || $offset > 0xFFFFFFFF) {
                    throw new RuntimeException('Ukuran laporan Excel melebihi batas ZIP standar.');
                }

                $nameLength = strlen($name);
                $flags = 0x0800; // Nama entry UTF-8.
                $method = 0; // Stored, tanpa kompresi.
                $crcBytes = $this->littleEndianCrc32($crcHex);

                $localHeader = pack(
                    'Vvvvvv',
                    0x04034b50,
                    10,
                    $flags,
                    $method,
                    $dosTime,
                    $dosDate,
                ).$crcBytes.pack('VVvv', $size, $size, $nameLength, 0).$name;

                if (fwrite($handle, $localHeader) !== strlen($localHeader)) {
                    throw new RuntimeException('Gagal menulis header komponen XLSX.');
                }

                if ($path !== null) {
                    $sourceHandle = fopen($path, 'rb');
                    if ($sourceHandle === false) {
                        throw new RuntimeException('Tidak dapat membuka komponen XLSX.');
                    }

                    try {
                        while (! feof($sourceHandle)) {
                            $chunk = fread($sourceHandle, 1024 * 1024);
                            if ($chunk === false) {
                                throw new RuntimeException('Gagal membaca komponen XLSX.');
                            }
                            if ($chunk !== '' && fwrite($handle, $chunk) !== strlen($chunk)) {
                                throw new RuntimeException('Gagal menulis komponen XLSX.');
                            }
                        }
                    } finally {
                        fclose($sourceHandle);
                    }
                } elseif ($content !== '' && fwrite($handle, $content) !== $size) {
                    throw new RuntimeException('Gagal menulis komponen XLSX.');
                }

                $centralDirectory .= pack(
                    'Vvvvvvv',
                    0x02014b50,
                    20,
                    10,
                    $flags,
                    $method,
                    $dosTime,
                    $dosDate,
                ).$crcBytes.pack(
                    'VVvvvvvVV',
                    $size,
                    $size,
                    $nameLength,
                    0,
                    0,
                    0,
                    0,
                    0,
                    $offset,
                ).$name;

                $offset += strlen($localHeader) + $size;
                $entryCount++;
            }

            $centralOffset = $offset;
            $centralSize = strlen($centralDirectory);

            if (fwrite($handle, $centralDirectory) !== $centralSize) {
                throw new RuntimeException('Gagal menulis direktori XLSX.');
            }

            $endRecord = pack(
                'VvvvvVVv',
                0x06054b50,
                0,
                0,
                $entryCount,
                $entryCount,
                $centralSize,
                $centralOffset,
                0,
            );

            if (fwrite($handle, $endRecord) !== strlen($endRecord)) {
                throw new RuntimeException('Gagal menyelesaikan arsip XLSX.');
            }
        } finally {
            fclose($handle);
        }
    }

    /** @return array{0: int, 1: int} */
    private function dosDateTime(): array
    {
        $now = getdate();
        $year = max(1980, min(2107, (int) $now['year']));
        $dosTime = ((int) $now['hours'] << 11)
            | ((int) $now['minutes'] << 5)
            | intdiv((int) $now['seconds'], 2);
        $dosDate = (($year - 1980) << 9)
            | ((int) $now['mon'] << 5)
            | (int) $now['mday'];

        return [$dosTime, $dosDate];
    }

    private function littleEndianCrc32(string $hex): string
    {
        $binary = hex2bin(str_pad(strtolower($hex), 8, '0', STR_PAD_LEFT));
        if ($binary === false || strlen($binary) !== 4) {
            throw new RuntimeException('Checksum komponen XLSX tidak valid.');
        }

        return strrev($binary);
    }

    private function writeMergedTextRow($handle, int $row, string $lastColumn, string $text, int $style, int $height): void
    {
        fwrite($handle, '<row r="'.$row.'" ht="'.$height.'" customHeight="1">');
        $this->writeInlineCell($handle, 'A'.$row, $text, $style);
        fwrite($handle, '</row>');
    }

    private function writeDataCell($handle, string $reference, string $header, mixed $value): void
    {
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        }

        if ($value === null || $value === '') {
            fwrite($handle, '<c r="'.$reference.'" s="5"/>');

            return;
        }

        if (is_bool($value)) {
            fwrite($handle, '<c r="'.$reference.'" s="6" t="n"><v>'.($value ? '1' : '0').'</v></c>');

            return;
        }

        if (is_int($value) || is_float($value)) {
            $style = $this->numericStyle($header, $value);
            fwrite($handle, '<c r="'.$reference.'" s="'.$style.'" t="n"><v>'.$this->numericValue($value).'</v></c>');

            return;
        }

        $this->writeInlineCell($handle, $reference, (string) $value, 5);
    }

    private function writeInlineCell($handle, string $reference, string $value, int $style): void
    {
        $value = $this->cleanXmlText($value);
        fwrite($handle, '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->escape($value).'</t></is></c>');
    }

    private function numericStyle(string $header, int|float $value): int
    {
        if (preg_match('/denda|dibayar|tagihan|nilai|harga|biaya/i', $header) === 1) {
            return 8;
        }

        return is_float($value) && floor($value) !== $value ? 7 : 6;
    }

    private function numericValue(int|float $value): string
    {
        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }

        return (string) $value;
    }

    private function displayLength(mixed $value): int
    {
        if ($value instanceof DateTimeInterface) {
            return 19;
        }

        if ($value === null) {
            return 0;
        }

        return $this->textLength((string) $value);
    }

    private function textLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    private function columnLetter(int $column): string
    {
        $result = '';
        while ($column > 0) {
            $column--;
            $result = chr(65 + ($column % 26)).$result;
            $column = intdiv($column, 26);
        }

        return $result;
    }

    private function sanitizeSheetName(string $name): string
    {
        $name = preg_replace('~[\\/?*\[\]:]~', ' ', trim($name)) ?: 'Laporan';

        return function_exists('mb_substr') ? mb_substr($name, 0, 31) : substr($name, 0, 31);
    }

    private function cleanXmlText(string $value): string
    {
        return preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function appPropertiesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            .'<Application>Rius Sistem Inventaris dan Perpustakaan</Application>'
            .'</Properties>';
    }

    private function corePropertiesXml(string $title): string
    {
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>'.$this->escape($title).'</dc:title>'
            .'<dc:creator>Rius</dc:creator>'
            .'<cp:lastModifiedBy>Rius</cp:lastModifiedBy>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$timestamp.'</dcterms:created>'
            .'<dcterms:modified xsi:type="dcterms:W3CDTF">'.$timestamp.'</dcterms:modified>'
            .'</cp:coreProperties>';
    }

    private function workbookXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<bookViews><workbookView xWindow="0" yWindow="0" windowWidth="19200" windowHeight="10800"/></bookViews>'
            .'<sheets><sheet name="'.$this->escape($sheetName).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="3">'
            .'<numFmt numFmtId="164" formatCode="#,##0"/>'
            .'<numFmt numFmtId="165" formatCode="#,##0.00"/>'
            .'<numFmt numFmtId="166" formatCode="&quot;Rp&quot; #,##0"/>'
            .'</numFmts>'
            .'<fonts count="4">'
            .'<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><color rgb="FFFFFFFF"/><sz val="15"/><name val="Calibri"/></font>'
            .'<font><b/><color rgb="FF16324F"/><sz val="13"/><name val="Calibri"/></font>'
            .'<font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="4">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF16324F"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF2F6B8A"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FFD5DEE8"/></left><right style="thin"><color rgb="FFD5DEE8"/></right><top style="thin"><color rgb="FFD5DEE8"/></top><bottom style="thin"><color rgb="FFD5DEE8"/></bottom><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="9">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="top"/></xf>'
            .'<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="top"/></xf>'
            .'<xf numFmtId="166" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="top"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
