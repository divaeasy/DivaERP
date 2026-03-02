<?php

namespace App\Service;

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
}
