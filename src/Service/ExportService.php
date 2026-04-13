<?php

namespace App\Service;

use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls as XlsWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /**
     * Export data to CSV as a streamed response.
     *
     * @param string   $filename  The download filename (e.g., 'clients.csv')
     * @param string[] $headers   Column headers
     * @param array    $rows      Array of associative arrays or arrays of values
     * @param callable|null $rowMapper Optional: transform each row to an array of values
     */
    public function exportCsv(string $filename, array $headers, array $rows, ?callable $rowMapper = null): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headers, $rows, $rowMapper) {
            $handle = fopen('php://output', 'w');

            // BOM for UTF-8 Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row
            fputcsv($handle, $headers, ';');

            // Data rows
            foreach ($rows as $row) {
                if ($rowMapper) {
                    $row = $rowMapper($row);
                }
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }

    /**
     * Export dashboard data to Excel with professional styling
     */
    public function exportDashboardToExcel(array $kpiData, string $filename = 'dashboard.xlsx'): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Dashboard KPI');

        // Set up page for printing (margins, orientation)
        $sheet->getPageSetup()->setOrientation('portrait');
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
        $sheet->getPageMargins()->setLeft(0.5);
        $sheet->getPageMargins()->setRight(0.5);
        $sheet->getPageMargins()->setTop(0.5);
        $sheet->getPageMargins()->setBottom(0.5);

        // ========== HEADER SECTION ==========
        // Title row with background color
        $sheet->setCellValue('A1', 'TABLEAU DE BORD');
        $sheet->mergeCells('A1:D1');
        $titleStyle = $sheet->getStyle('A1');
        $titleStyle->getFont()->setBold(true)->setSize(16)->setColor(new Color('FFFFFFFF'));
        $titleStyle->getFill()->setFillType('solid')->getStartColor()->setARGB('FF1E3A8A'); // Dark blue
        $titleStyle->getAlignment()->setHorizontal('center')->setVertical('center');
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Subtitle with date
        $sheet->setCellValue('A2', 'Indicateurs Clés de Performance (KPI) - Export du ' . date('d/m/Y à H:i'));
        $sheet->mergeCells('A2:D2');
        $subtitleStyle = $sheet->getStyle('A2');
        $subtitleStyle->getFont()->setSize(10)->setColor(new Color('FF666666'))->setItalic(true);
        $subtitleStyle->getFill()->setFillType('solid')->getStartColor()->setARGB('FFF3F4F6'); // Light gray
        $subtitleStyle->getAlignment()->setHorizontal('center');
        $sheet->getRowDimension(2)->setRowHeight(18);

        // Empty row for spacing
        $sheet->getRowDimension(3)->setRowHeight(8);

        // ========== METRICS TABLE ==========
        // Header row
        $headerCells = ['A4' => 'Métrique', 'B4' => 'Valeur', 'C4' => 'Période', 'D4' => 'Comparaison'];
        foreach ($headerCells as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Style header row
        $headerStyle = $sheet->getStyle('A4:D4');
        $headerStyle->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'))->setSize(11);
        $headerStyle->getFill()->setFillType('solid')->getStartColor()->setARGB('FF4F46E5'); // Professional indigo
        $headerStyle->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
        $headerStyle->getBorders()->getAllBorders()
            ->setBorderStyle('thin')
            ->setColor(new Color('FFE5E7EB'));
        $sheet->getRowDimension(4)->setRowHeight(22);

        // Add KPI data with alternating row colors
        $row = 5;
        $rowCount = 0;
        foreach ($kpiData as $metric => $value) {
            // Alternate row background colors
            $bgColor = ($rowCount % 2 === 0) ? 'FFFBFCFD' : 'FFFFFFFF';
            $rowStyle = $sheet->getStyle("A{$row}:D{$row}");
            $rowStyle->getFill()->setFillType('solid')->getStartColor()->setARGB($bgColor);
            $rowStyle->getBorders()->getAllBorders()
                ->setBorderStyle('thin')
                ->setColor(new Color('FFE5E7EB'));

            // Metric name
            $sheet->setCellValue("A{$row}", (string)$metric);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(10)->setColor(new Color('FF1F2937'));
            $sheet->getStyle("A{$row}")->getAlignment()->setVertical('center')->setWrapText(true);

            if (is_array($value)) {
                $cellValue = $value['value'] ?? '-';
                $cellPeriod = $value['period'] ?? '-';
                $cellComparison = $value['comparison'] ?? '-';

                // Value cell
                $sheet->setCellValue("B{$row}", (string)$cellValue);
                $sheet->getStyle("B{$row}")->getFont()->setSize(10)->setBold(true);
                $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal('right')->setVertical('center');

                // Period cell
                $sheet->setCellValue("C{$row}", (string)$cellPeriod);
                $sheet->getStyle("C{$row}")->getFont()->setSize(9)->setColor(new Color('FF6B7280'));
                $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal('center')->setVertical('center');

                // Comparison cell with conditional coloring
                $sheet->setCellValue("D{$row}", (string)$cellComparison);
                $comparisonStr = (string)$cellComparison;
                $compStyle = $sheet->getStyle("D{$row}");
                
                if (strpos($comparisonStr, '+') === 0 && $comparisonStr !== '+0%') {
                    // Positive growth
                    $compStyle->getFont()->setColor(new Color('FF059669'))->setBold(true);
                } elseif (strpos($comparisonStr, '-') === 0 && $comparisonStr !== '-0%') {
                    // Negative growth
                    $compStyle->getFont()->setColor(new Color('FFC1121B'))->setBold(true);
                } else {
                    // Neutral
                    $compStyle->getFont()->setColor(new Color('FF6B7280'));
                }
                $compStyle->getAlignment()->setHorizontal('center')->setVertical('center');
            } else {
                $sheet->setCellValue("B{$row}", (string)$value);
                $sheet->getStyle("B{$row}")->getFont()->setSize(10)->setBold(true);
                $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal('right')->setVertical('center');
            }

            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;
            $rowCount++;
        }

        // ========== COLUMN WIDTHS ==========
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(18);

        // ========== FOOTER SECTION ==========
        $footerRow = $row + 1;
        $sheet->setCellValue("A{$footerRow}", 'Généré par DivaERP - ' . date('Y-m-d H:i:s'));
        $sheet->mergeCells("A{$footerRow}:D{$footerRow}");
        $footerStyle = $sheet->getStyle("A{$footerRow}");
        $footerStyle->getFont()->setSize(8)->setColor(new Color('FF9CA3AF'))->setItalic(true);
        $footerStyle->getAlignment()->setHorizontal('right')->setVertical('center');

        // Write to temp file
        $tmpFile = tempnam(sys_get_temp_dir(), 'dashboard_');
        $useZip = class_exists(\ZipArchive::class);
        $writer = $useZip ? new Xlsx($spreadsheet) : new XlsWriter($spreadsheet);
        $writer->save($tmpFile);

        // Return as downloadable response
        $response = new BinaryFileResponse($tmpFile);
        $downloadFilename = $useZip ? $filename : preg_replace('/\.xlsx$/i', '.xls', $filename);
        $response->headers->set('Content-Type', $useZip
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $downloadFilename));
        $response->deleteFileAfterSend(true);

        return $response;
    }

    /**
     * Export list data to Excel with professional styling
     * 
     * @param string $filename The download filename
     * @param string $sheetTitle The sheet title and table header
     * @param string[] $headers Column headers
     * @param array $rows Data rows (array of arrays)
     */
    public function exportListToExcel(
        string $filename,
        string $sheetTitle,
        array $headers,
        array $rows,
        ?array $notices = null,
        array $options = []
    ): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Export');

        // Set up page for printing
        $sheet->getPageSetup()->setOrientation('landscape');
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
        $sheet->getPageMargins()->setLeft(0.4);
        $sheet->getPageMargins()->setRight(0.4);
        $sheet->getPageMargins()->setTop(0.5);
        $sheet->getPageMargins()->setBottom(0.5);

        // ========== HEADER SECTION ==========
        $sheet->setCellValue('A1', strtoupper($sheetTitle));
        $sheet->mergeCells('A1:' . $this->getColumnLetter(count($headers)) . '1');
        $titleStyle = $sheet->getStyle('A1');
        $titleStyle->getFont()->setBold(true)->setSize(14)->setColor(new Color('FFFFFFFF'));
        $titleStyle->getFill()->setFillType('solid')->getStartColor()->setARGB('FF1E3A8A');
        $titleStyle->getAlignment()->setHorizontal('center')->setVertical('center');
        $sheet->getRowDimension(1)->setRowHeight(25);

        // Subtitle with date and row count
        $sheet->setCellValue('A2', 'Export du ' . date('d/m/Y à H:i') . ' - ' . count($rows) . ' enregistrement(s)');
        $sheet->mergeCells('A2:' . $this->getColumnLetter(count($headers)) . '2');
        $subtitleStyle = $sheet->getStyle('A2');
        $subtitleStyle->getFont()->setSize(9)->setColor(new Color('FF666666'))->setItalic(true);
        $subtitleStyle->getFill()->setFillType('solid')->getStartColor()->setARGB('FFF3F4F6');
        $subtitleStyle->getAlignment()->setHorizontal('center');
        $sheet->getRowDimension(2)->setRowHeight(16);

        // Empty row for spacing
        $sheet->getRowDimension(3)->setRowHeight(6);

        // ========== HEADER ROW ==========
        $headerRow = 4;
        foreach ($headers as $colIndex => $header) {
            $cell = $this->getColumnLetter($colIndex + 1) . $headerRow;
            $sheet->setCellValue($cell, $header);
        }

        $headerRange = 'A' . $headerRow . ':' . $this->getColumnLetter(count($headers)) . $headerRow;
        $headerStyle = $sheet->getStyle($headerRange);
        $headerStyle->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'))->setSize(10);
        $headerStyle->getFill()->setFillType('solid')->getStartColor()->setARGB('FF4F46E5');
        $headerStyle->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
        $headerStyle->getBorders()->getAllBorders()
            ->setBorderStyle('thin')
            ->setColor(new Color('FFE5E7EB'));
        $sheet->getRowDimension($headerRow)->setRowHeight(20);

        // ========== DATA ROWS ==========
        $dataRow = $headerRow + 1;
        $rowCount = 0;
        foreach ($rows as $row) {
            // Alternate row background colors
            $bgColor = ($rowCount % 2 === 0) ? 'FFFBFCFD' : 'FFFFFFFF';
            $rowRange = 'A' . $dataRow . ':' . $this->getColumnLetter(count($headers)) . $dataRow;
            $rowStyle = $sheet->getStyle($rowRange);
            $rowStyle->getFill()->setFillType('solid')->getStartColor()->setARGB($bgColor);
            $rowStyle->getBorders()->getAllBorders()
                ->setBorderStyle('thin')
                ->setColor(new Color('FFE5E7EB'));
            $rowStyle->getFont()->setSize(9);
            $rowStyle->getAlignment()->setVertical('center');

            foreach ($row as $colIndex => $value) {
                $cell = $this->getColumnLetter($colIndex + 1) . $dataRow;
                $sheet->setCellValue($cell, $value);
                
                // Center align for ID columns, left align for text, right align for numbers
                $cellStyle = $sheet->getStyle($cell);
                if ($colIndex === 0 || stripos($headers[$colIndex] ?? '', 'ID') !== false) {
                    $cellStyle->getAlignment()->setHorizontal('center');
                } elseif (is_numeric($value) && $colIndex > 0) {
                    $cellStyle->getAlignment()->setHorizontal('right');
                } else {
                    $cellStyle->getAlignment()->setHorizontal('left');
                }
            }

            $sheet->getRowDimension($dataRow)->setRowHeight(18);
            $dataRow++;
            $rowCount++;
        }

        // ========== COLUMN WIDTHS ==========
        for ($i = 0; $i < count($headers); $i++) {
            $col = $this->getColumnLetter($i + 1);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        if ((string) ($options['template'] ?? '') === 'articles_import') {
            $this->applyArticlesImportTemplate(
                $spreadsheet,
                $sheet,
                $headerRow + 1,
                max($dataRow - 1, $headerRow + 1),
                $options
            );
        }

        // ========== FOOTER SECTION ==========
        if ((bool) ($options['include_footer'] ?? true)) {
            $footerRow = $dataRow + 1;
            $sheet->setCellValue('A' . $footerRow, 'Généré par DivaERP - ' . date('Y-m-d H:i:s'));
            $sheet->mergeCells('A' . $footerRow . ':' . $this->getColumnLetter(count($headers)) . $footerRow);
            $footerStyle = $sheet->getStyle('A' . $footerRow);
            $footerStyle->getFont()->setSize(8)->setColor(new Color('FF9CA3AF'))->setItalic(true);
            $footerStyle->getAlignment()->setHorizontal('right');
        }

        if ($notices !== null && $notices !== []) {
            $this->appendNoticesSheet($spreadsheet, $notices);
        }

        // Write to temp file
        $tmpFile = tempnam(sys_get_temp_dir(), 'export_list_');
        $preferredFormat = strtolower((string) ($options['format'] ?? 'xlsx'));
        if (!in_array($preferredFormat, ['xlsx', 'xls'], true)) {
            $preferredFormat = preg_match('/\.xls$/i', $filename) ? 'xls' : 'xlsx';
        }
        if ($preferredFormat === 'xlsx' && !class_exists(\ZipArchive::class)) {
            $preferredFormat = 'xls';
        }

        $writer = $preferredFormat === 'xlsx'
            ? new Xlsx($spreadsheet)
            : new XlsWriter($spreadsheet);
        $writer->save($tmpFile);

        // Return as downloadable response
        $response = new BinaryFileResponse($tmpFile);
        $downloadFilename = preg_replace('/\.(xlsx|xls)$/i', '', $filename) . '.' . $preferredFormat;
        $response->headers->set('Content-Type', $preferredFormat === 'xlsx'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $downloadFilename));
        $response->deleteFileAfterSend(true);

        return $response;
    }

    /**
     * @param array<int, array{title: string, value: string}> $notices
     */
    private function appendNoticesSheet(Spreadsheet $spreadsheet, array $notices): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Notices');

        $sheet->setCellValue('A1', 'NOTICES IMPORT');
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1:B1')->getFont()->setBold(true)->setSize(14)->setColor(new Color('FFFFFFFF'));
        $sheet->getStyle('A1:B1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF1E3A8A');
        $sheet->getStyle('A1:B1')->getAlignment()->setHorizontal('center')->setVertical('center');
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->setCellValue('A3', 'Champ');
        $sheet->setCellValue('B3', 'Instruction');
        $sheet->getStyle('A3:B3')->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
        $sheet->getStyle('A3:B3')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF4F46E5');
        $sheet->getStyle('A3:B3')->getAlignment()->setHorizontal('center')->setVertical('center');

        $row = 4;
        foreach ($notices as $notice) {
            $sheet->setCellValue('A' . $row, $notice['title']);
            $sheet->setCellValue('B' . $row, $notice['value']);
            $sheet->getStyle('A' . $row . ':B' . $row)->getBorders()->getAllBorders()->setBorderStyle('thin');
            $sheet->getStyle('A' . $row . ':B' . $row)->getAlignment()->setVertical('top')->setWrapText(true);
            $sheet->getRowDimension($row)->setRowHeight(34);
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(90);
    }

    private function applyArticlesImportTemplate(
        Spreadsheet $spreadsheet,
        Worksheet $sheet,
        int $dataStartRow,
        int $lastDataRow,
        array $options = []
    ): void
    {
        $maxRow = max($lastDataRow + 500, 2000);
        $validationRanges = $this->createArticlesValidationSheet(
            $spreadsheet,
            $options['article_unite_options'] ?? [],
            $options['article_tarif_options'] ?? []
        );

        // Excel validations only (no full sheet lock), so users can type freely
        // and only get an error when value is invalid.
        for ($row = $dataStartRow; $row <= $maxRow; $row++) {
            $existingId = trim((string) $sheet->getCell('A' . $row)->getFormattedValue());

            $idValidation = new DataValidation();
            $idValidation->setType(DataValidation::TYPE_CUSTOM);
            $idValidation->setErrorStyle(DataValidation::STYLE_STOP);
            $idValidation->setAllowBlank($existingId === '');
            $idValidation->setShowInputMessage(false);
            $idValidation->setShowErrorMessage(true);
            $idValidation->setErrorTitle('ID non modifiable');
            $idValidation->setError('La colonne ID est geree automatiquement. Ne la modifiez pas.');
            if ($existingId !== '' && ctype_digit($existingId)) {
                $idValidation->setFormula1('$A' . $row . '=' . $existingId);
            } else {
                $idValidation->setFormula1('$A' . $row . '=""');
            }
            $sheet->getCell('A' . $row)->setDataValidation($idValidation);

            // Designation must be filled only for new rows (ID empty).
            $validation = new DataValidation();
            $validation->setType(DataValidation::TYPE_CUSTOM);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(false);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Valeur invalide');
            $validation->setError('Pour une creation, renseignez Designation.');
            $validation->setFormula1('OR($A' . $row . '<>"",LEN(TRIM($B' . $row . '))>0)');
            $sheet->getCell('B' . $row)->setDataValidation($validation);

            $uniteValidation = new DataValidation();
            $uniteValidation->setType($validationRanges['unites'] !== null ? DataValidation::TYPE_LIST : DataValidation::TYPE_CUSTOM);
            $uniteValidation->setErrorStyle(DataValidation::STYLE_STOP);
            $uniteValidation->setAllowBlank(true);
            $uniteValidation->setShowInputMessage(false);
            $uniteValidation->setShowErrorMessage(true);
            $uniteValidation->setErrorTitle('Unite invalide');
            $uniteValidation->setError('Choisissez une unite existante dans la liste ou laissez la cellule vide.');
            $uniteValidation->setFormula1($validationRanges['unites'] ?? 'TRUE');
            $sheet->getCell('C' . $row)->setDataValidation($uniteValidation);

            $tarifValidation = new DataValidation();
            $tarifValidation->setType($validationRanges['tarifs'] !== null ? DataValidation::TYPE_LIST : DataValidation::TYPE_CUSTOM);
            $tarifValidation->setErrorStyle(DataValidation::STYLE_STOP);
            $tarifValidation->setAllowBlank(true);
            $tarifValidation->setShowInputMessage(false);
            $tarifValidation->setShowErrorMessage(true);
            $tarifValidation->setErrorTitle('Tarif invalide');
            $tarifValidation->setError('Choisissez un tarif existant dans la liste ou laissez la cellule vide.');
            $tarifValidation->setFormula1($validationRanges['tarifs'] ?? 'TRUE');
            $sheet->getCell('D' . $row)->setDataValidation($tarifValidation);
        }
    }

    /**
     * @param array<int, string> $uniteOptions
     * @param array<int, string> $tarifOptions
     *
     * @return array{unites: ?string, tarifs: ?string}
     */
    private function createArticlesValidationSheet(Spreadsheet $spreadsheet, array $uniteOptions, array $tarifOptions): array
    {
        $uniteOptions = $this->normalizeValidationOptions($uniteOptions);
        $tarifOptions = $this->normalizeValidationOptions($tarifOptions);

        if ($uniteOptions === [] && $tarifOptions === []) {
            return ['unites' => null, 'tarifs' => null];
        }

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('DivaLists');
        $sheet->setCellValue('A1', 'Unites');
        $sheet->setCellValue('B1', 'Tarifs');

        $row = 2;
        foreach ($uniteOptions as $option) {
            $sheet->setCellValue('A' . $row, $option);
            $row++;
        }

        $row = 2;
        foreach ($tarifOptions as $option) {
            $sheet->setCellValue('B' . $row, $option);
            $row++;
        }

        $sheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
        if ($uniteOptions !== []) {
            $spreadsheet->addNamedRange(new NamedRange('ArticleUniteOptions', $sheet, '$A$2:$A$' . (count($uniteOptions) + 1)));
        }
        if ($tarifOptions !== []) {
            $spreadsheet->addNamedRange(new NamedRange('ArticleTarifOptions', $sheet, '$B$2:$B$' . (count($tarifOptions) + 1)));
        }

        return [
            'unites' => $uniteOptions !== []
                ? '=ArticleUniteOptions'
                : null,
            'tarifs' => $tarifOptions !== []
                ? '=ArticleTarifOptions'
                : null,
        ];
    }

    /**
     * @param array<int, string> $options
     *
     * @return array<int, string>
     */
    private function normalizeValidationOptions(array $options): array
    {
        $normalized = [];

        foreach ($options as $option) {
            $value = trim((string) $option);
            if ($value === '') {
                continue;
            }

            $normalized[$value] = $value;
        }

        return array_values($normalized);
    }

    /**
     * Helper function to convert column number to letter
     */
    private function getColumnLetter(int $colNum): string
    {
        $colLetter = '';
        while ($colNum > 0) {
            $colNum--;
            $colLetter = chr(65 + ($colNum % 26)) . $colLetter;
            $colNum = intdiv($colNum, 26);
        }
        return $colLetter;
    }

    /**
     * Export dashboard data to PDF
     */
    public function exportDashboardToPdf(array $kpiData, array $chartImages = [], string $filename = 'dashboard.pdf'): BinaryFileResponse
    {
        $html = $this->generateDashboardHtml($kpiData, $chartImages);

        $mpdf = new Mpdf([
            'orientation' => 'P',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
        ]);

        $mpdf->WriteHTML($html);
        
        $tmpFile = tempnam(sys_get_temp_dir(), 'dashboard_pdf_');
        $mpdf->Output($tmpFile, 'F');

        $response = new BinaryFileResponse($tmpFile);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
        $response->deleteFileAfterSend(true);

        return $response;
    }

    /**
     * Generate HTML for PDF dashboard
     */
    private function generateDashboardHtml(array $kpiData, array $chartImages = []): string
    {
        $html = '<html><head><meta charset="UTF-8"><style>';
        $html .= 'body { font-family: Arial, sans-serif; color: #333; margin: 0; }';
        $html .= 'h1 { color: #4e73df; text-align: center; margin: 20px 0 10px 0; }';
        $html .= 'h2 { color: #4e73df; border-bottom: 3px solid #4e73df; padding-bottom: 10px; margin-top: 30px; margin-bottom: 15px; }';
        $html .= 'h3 { color: #666; margin: 15px 0 10px 0; }';
        $html .= '.header-info { text-align: center; font-size: 12px; color: #999; margin-bottom: 20px; }';
        $html .= 'table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }';
        $html .= 'th { background: #4e73df; color: white; border: 1px solid #ddd; padding: 12px; text-align: left; font-weight: bold; }';
        $html .= 'td { border: 1px solid #ddd; padding: 12px; }';
        $html .= 'tr:nth-child(even) { background: #f9f9f9; }';
        $html .= 'tr:hover { background: #f0f0f0; }';
        $html .= '.page-break { page-break-after: always; }';
        $html .= '.summary-box { background: #e7f0ff; border-left: 4px solid #4e73df; padding: 15px; margin-bottom: 15px; }';
        $html .= '.summary-box p { margin: 5px 0; }';
        $html .= '.metric-label { font-weight: bold; color: #4e73df; }';
        $html .= '.top-products-list { background: #f5f5f5; padding: 15px; border-radius: 5px; }';
        $html .= '.product-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dotted #ddd; }';
        $html .= '.product-item:last-child { border-bottom: none; }';
        $html .= '.product-rank { background: #4e73df; color: white; border-radius: 50%; width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 10px; }';
        $html .= '</style></head><body>';

        // Header
        $html .= '<h1>Rapport du Tableau de Bord</h1>';
        $html .= '<div class="header-info">Généré le ' . date('d/m/Y à H:i') . '</div>';

        // Summary boxes
        $html .= '<div class="summary-box">';
        $html .= '<p><span class="metric-label">Aperçu général:</span> Ce rapport contient les indicateurs clés de performance (KPIs), les produits les plus vendus et les statistiques de votre tableau de bord.</p>';
        $html .= '</div>';

        // KPI Section
        $html .= '<h2>Indicateurs Clés de Performance (KPIs)</h2>';
        $html .= '<table>';
        $html .= '<thead><tr>';
        $html .= '<th>Métrique</th>';
        $html .= '<th>Valeur</th>';
        $html .= '<th>Période</th>';
        $html .= '<th>Comparaison</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($kpiData as $metric => $value) {
            $html .= '<tr>';
            $html .= '<td><strong>' . htmlspecialchars($metric) . '</strong></td>';
            
            if (is_array($value)) {
                $html .= '<td>' . htmlspecialchars((string)($value['value'] ?? '-')) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)($value['period'] ?? '-')) . '</td>';
                
                $comparison = (string)($value['comparison'] ?? '-');
                $comparisonClass = strpos($comparison, '+') === 0 ? 'style="color: green; font-weight: bold;"' : (strpos($comparison, '-') === 0 ? 'style="color: red; font-weight: bold;"' : '');
                $html .= '<td ' . $comparisonClass . '>' . htmlspecialchars($comparison) . '</td>';
            } else {
                $html .= '<td>' . htmlspecialchars((string)$value) . '</td>';
                $html .= '<td>-</td>';
                $html .= '<td>-</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        // Chart images section (if provided)
        if (!empty($chartImages)) {
            $html .= '<div class="page-break"></div>';
            $html .= '<h2>Top 5 Produits</h2>';
            
            if (is_array($chartImages) && count($chartImages) > 0) {
                $html .= '<table>';
                $html .= '<thead><tr>';
                $html .= '<th style="width: 10%;">Rang</th>';
                $html .= '<th style="width: 60%;">Produit</th>';
                $html .= '<th style="width: 30%;">Quantité Vendue</th>';
                $html .= '</tr></thead><tbody>';
                
                $rank = 1;
                foreach ($chartImages as $product) {
                    if (is_array($product) && isset($product['product_name']) && isset($product['total_qty'])) {
                        $html .= '<tr>';
                        $html .= '<td><span class="product-rank">' . $rank . '</span></td>';
                        $html .= '<td>' . htmlspecialchars((string)$product['product_name']) . '</td>';
                        $html .= '<td><strong>' . htmlspecialchars((string)$product['total_qty']) . ' unités</strong></td>';
                        $html .= '</tr>';
                        $rank++;
                    }
                }
                
                $html .= '</tbody></table>';
            }
        }

        $html .= '<div class="page-break"></div>';
        $html .= '<div style="text-align: center; color: #999; font-size: 11px; margin-top: 30px;">';
        $html .= '<p>Document généré automatiquement par le système DivaERP</p>';
        $html .= '<p style="margin-top: 20px;">© ' . date('Y') . ' - Tous droits réservés</p>';
        $html .= '</div>';

        $html .= '</body></html>';
        return $html;
    }
}
