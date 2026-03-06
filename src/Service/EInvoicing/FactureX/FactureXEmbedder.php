<?php

namespace App\Service\EInvoicing\FactureX;

use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Creates PDF with embedded Factur-X XML attachments
 * Uses multiple strategies: QPDF (preferred) → PHP raw PDF → Returns PDF if all fail
 */
class FactureXEmbedder
{
    private Filesystem $filesystem;
    private string $tempDir;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir();
    }

    /**
     * Embed XML into PDF to create Factur-X compliant document
     * 
     * @param string $pdfContent Original PDF binary
     * @param string $xmlContent XML content to embed
     * @param string $invoiceRef Invoice reference
     * @return string PDF with embedded XML
     */
    public function embedXmlInPdf(string $pdfContent, string $xmlContent, string $invoiceRef): string
    {
        // Try QPDF first (most reliable)
        $result = $this->embedUsingQpdf($pdfContent, $xmlContent, $invoiceRef);
        if ($result !== null) {
            return $result;
        }

        // Fallback 1: Direct PDF attachment injection (PHP-only)
        $result = $this->embedUsingDirectPdfInjection($pdfContent, $xmlContent);
        if ($result !== null && $result !== $pdfContent) {
            return $result;
        }

        // Last resort: Return PDF without attachment
        // (XML can still be accessed via separate file or embedded in future)
        return $pdfContent;
    }

    /**
     * Embed XML using QPDF command-line tool (Most reliable)
     * QPDF perfectly handles PDF attachment embedding
     * 
     * @return string|null PDF with embedded attachment, or null if QPDF not available
     */
    private function embedUsingQpdf(string $pdfContent, string $xmlContent, string $invoiceRef): ?string
    {
        if (!$this->isQpdfAvailable()) {
            return null;
        }

        try {
            // Create temporary files
            $inputPdfPath = tempnam($this->tempDir, 'input_pdf_');
            $outputPdfPath = tempnam($this->tempDir, 'output_pdf_');
            $xmlPath = tempnam($this->tempDir, 'facturex_');
            
            file_put_contents($inputPdfPath, $pdfContent);
            file_put_contents($xmlPath, $xmlContent);
            
            // Run QPDF to attach the XML
            $command = [
                'qpdf',
                '--attach-file', $xmlPath,
                '--',
                $inputPdfPath,
                $outputPdfPath
            ];

            $process = new Process($command);
            $process->setTimeout(30);
            $process->run();

            if ($process->isSuccessful() && file_exists($outputPdfPath)) {
                $result = file_get_contents($outputPdfPath);
                
                // Clean up
                @unlink($inputPdfPath);
                @unlink($outputPdfPath);
                @unlink($xmlPath);
                
                return $result;
            }

            // Clean up on failure
            @unlink($inputPdfPath);
            @unlink($outputPdfPath);
            @unlink($xmlPath);
            return null;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if QPDF is available on system
     */
    private function isQpdfAvailable(): bool
    {
        try {
            $process = new Process(['qpdf', '--version']);
            $process->setTimeout(5);
            $process->run();
            return $process->isSuccessful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Embed using direct PDF injection (PHP-only solution)
     * 
     * @return string|null Modified PDF with attachment, or null if failed
     */
    private function embedUsingDirectPdfInjection(string $pdfContent, string $xmlContent): ?string
    {
        try {
            // Step 1: Validate input
            if (!str_starts_with($pdfContent, '%PDF')) {
                return null;
            }
            
            // Step 2: Remove old xref/trailer by finding LAST occurrence of xref
            // This ensures we have a clean PDF body without old metadata
            $xrefPos = strrpos($pdfContent, 'xref');
            if ($xrefPos === false) {
                return null;
            }
            
            $pdfBody = substr($pdfContent, 0, $xrefPos);
             
            // Step 3: Find all existing objects in the PDF body
            $objects = [];
            preg_match_all('/(\d+)\s+0\s+obj/m', $pdfBody, $matches);
            if (empty($matches[1])) {
                return null;
            }
            
            foreach ($matches[1] as $num) {
                $objects[(int)$num] = true;
            }
            
            $maxObjNum = max(array_keys($objects));
            
            // Step 4: Assign object numbers for attachments
            $streamObjNum = $maxObjNum + 1;
            $specObjNum = $maxObjNum + 2;
            $namesObjNum = $maxObjNum + 3;
            
            // Step 5: Compress XML and create stream object
            $xmlGzipped = gzcompress($xmlContent, 9);
            
            $streamObj = "$streamObjNum 0 obj\n";
            $streamObj .= "<< /Type /EmbeddedFile /Subtype /application#2Fxml /Length " . strlen($xmlGzipped) . " /Filter /FlateDecode >>\n";
            $streamObj .= "stream\n";
            $streamObj .= $xmlGzipped;
            $streamObj .= "\nendstream\n";
            $streamObj .= "endobj\n";
            
            // Step 6: Create filespec object
            $specObj = "$specObjNum 0 obj\n";
            $specObj .= "<< /Type /Filespec /F (factur-x.xml) /UF (factur-x.xml) /EF << /F $streamObjNum 0 R >> >>\n";
            $specObj .= "endobj\n";
            
            // Step 7: Create names tree object
            $namesObj = "$namesObjNum 0 obj\n";
            $namesObj .= "<< /Names [(factur-x.xml) $specObjNum 0 R] >>\n";
            $namesObj .= "endobj\n";
            
            // Step 8: Append all new objects to PDF body
            $newPdfBody = $pdfBody . $streamObj . $specObj . $namesObj;
            
            // Step 9: Update catalog to add /Names reference
            $newPdfBody = $this->addNamesToCatalog($newPdfBody, $namesObjNum);
            
            // Step 10: Rebuild xref and trailer
            $xrefTable = $this->buildXrefTableComplete($newPdfBody);
            
            return $newPdfBody . $xrefTable;
            
        } catch (\Exception $e) {
            error_log("PDF Embed Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Try to add /Names reference to PDF catalog
     * Returns modified body, or original if catalog found but not modifiable
     */
    private function addNamesToCatalog(string $pdfBody, int $namesObjNum): string
    {
        // Find ANY /Type /Catalog object, not just object 1
        if (!preg_match('/(\d+)\s+0\s+obj\s*<<(.+?\/Type\s*\/Catalog(.+?))>>\s*endobj/ms', $pdfBody, $m)) {
            return $pdfBody; // Can't find catalog
        }
        
        $catNum = (int)$m[1];
        $catDict = $m[2];
        
        // Check if /Names already exists
        if (strpos($catDict, '/Names') !== false) {
            return $pdfBody; // Already has /Names
        }
        
        // Build new catalog dictionary with /Names reference BEFORE the closing >>
        $newCatDict = rtrim($catDict) . "\n/Names << /EmbeddedFiles $namesObjNum 0 R >>";
        
        // Build replacement strings
        $oldObj = "$catNum 0 obj\n<<" . $catDict . ">>\nendobj";
        $newObj = "$catNum 0 obj\n<<" . $newCatDict . ">>\nendobj";
        
        return str_replace($oldObj, $newObj, $pdfBody);
    }
    
    /**
     * Build complete xref table and trailer
     */
    private function buildXrefTableComplete(string $pdfBody): string
    {
        // Use simple, reliable method: find all object declarations using strpos
        $offsets = [];
        
        //Find all "N 0 obj" patterns
        $searchPat = ' 0 obj';
        $lastPos = 0;
        
        while (($pos = strpos($pdfBody, $searchPat, $lastPos)) !== false) {
            // Go backwards from pos to find the object number
            $searchStart = max(0, $pos - 10);
            $substr = substr($pdfBody, $searchStart, $pos - $searchStart);
            
            if (preg_match('/(\d+)\s+$/', $substr, $m)) {
                $objNum = (int)$m[1];
                // Calculate actual byte offset: searchStart + length to get to start of object number
                $numLen = strlen($m[1]);
                $offset = $searchStart + strlen($substr) - $numLen;
                $offsets[$objNum] = $offset;
            }
            
            $lastPos = $pos + 1;
        }
        
        if (empty($offsets)) {
            // Fallback to regex
            preg_match_all('/(\d+)\s+0\s+obj/m', $pdfBody, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[1] as $idx => $m) {
                $objNum = (int)$m[0];
                $offsets[$objNum] = $m[1];
            }
        }
        
        if (empty($offsets)) {
            return ''; // Failure
        }
        
        // Find root (catalog) object - look for /Type /Catalog
        $rootObj = 1; // Default
        foreach ($offsets as $objNum => $offset) {
            if ($offset < strlen($pdfBody)) {
                $snippet = substr($pdfBody, $offset, 300);
                if (preg_match('/\/Type\s*\/Catalog/', $snippet)) {
                    $rootObj = $objNum;
                    break;
                }
            }
        }
        
        // Build xref section
        ksort($offsets);
        $maxObj = max(array_keys($offsets));
        
        $xrefStartOffset = strlen($pdfBody);
        
        $xref = "xref\n";
        $xref .= "0 " . ($maxObj + 1) . "\n";
        $xref .= "0000000000 65535 f \n";
        
        for ($i = 1; $i <= $maxObj; $i++) {
            if (isset($offsets[$i])) {
                $xref .= sprintf("%010d 00000 n \n", $offsets[$i]);
            } else {
                $xref .= "0000000000 00000 f \n";
            }
        }
        
        // Build trailer
        $fileId = md5($pdfBody . time());
        
        $trailer = "trailer\n";
        $trailer .= "<<\n";
        $trailer .= "/Size " . ($maxObj + 1) . "\n";
        $trailer .= "/Root $rootObj 0 R\n";
        $trailer .= "/ID [<" . $fileId . "><" . $fileId . ">]\n";
        $trailer .= ">>\n";
        $trailer .= "startxref\n";
        $trailer .= $xrefStartOffset . "\n";
        $trailer .= "%%EOF\n";
        
        return $xref . $trailer;
    }
}
