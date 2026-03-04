<?php

namespace App\Service;

use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
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
     * Export dashboard data to Excel
     */
    public function exportDashboardToExcel(array $kpiData, string $filename = 'dashboard.xlsx'): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Dashboard KPI');

        // Add title
        $sheet->setCellValue('A1', 'Tableau de Bord - Données KPI');
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1:D1')->getFont()->setBold(true)->setSize(14);

        // Add date
        $sheet->setCellValue('A2', 'Date d\'export: ' . date('d/m/Y H:i'));
        $sheet->mergeCells('A2:D2');

        // Headers
        $sheet->setCellValue('A4', 'Métrique');
        $sheet->setCellValue('B4', 'Valeur');
        $sheet->setCellValue('C4', 'Période');
        $sheet->setCellValue('D4', 'Comparaison');
        
        // Style header row
        $sheet->getStyle('A4:D4')->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
        $sheet->getStyle('A4:D4')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF4E73DF');

        // Add KPI data
        $row = 5;
        foreach ($kpiData as $metric => $value) {
            $sheet->setCellValue("A{$row}", (string)$metric);
            if (is_array($value)) {
                $cellValue = $value['value'] ?? '';
                $cellPeriod = $value['period'] ?? '';
                $cellComparison = $value['comparison'] ?? '';
                
                // Convert to string if needed
                $sheet->setCellValue("B{$row}", (string)$cellValue);
                $sheet->setCellValue("C{$row}", (string)$cellPeriod);
                $sheet->setCellValue("D{$row}", (string)$cellComparison);
            } else {
                $sheet->setCellValue("B{$row}", (string)$value);
            }
            $row++;
        }

        // Auto-fit columns
        foreach (['A', 'B', 'C', 'D'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Write to temp file
        $tmpFile = tempnam(sys_get_temp_dir(), 'dashboard_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpFile);

        // Return as downloadable response
        $response = new BinaryFileResponse($tmpFile);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
        $response->deleteFileAfterSend(true);

        return $response;
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
