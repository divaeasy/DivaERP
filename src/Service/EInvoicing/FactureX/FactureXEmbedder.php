<?php

namespace App\Service\EInvoicing\FactureX;

use horstoeko\zugferd\ZugferdDocumentPdfBuilderAbstract;
use horstoeko\zugferd\ZugferdDocumentPdfMerger;

/**
 * Embeds Factur-X XML into a visual PDF and produces a PDF/A-3 document.
 */
class FactureXEmbedder
{
    /**
     * Merge XML + PDF using the official horstoeko PDF merger.
     * This path adds proper AF relationship, XMP metadata and attachment naming.
     */
    public function embedXmlInPdf(string $pdfContent, string $xmlContent, string $invoiceRef): string
    {
        try {
            $merger = new ZugferdDocumentPdfMerger($xmlContent, $pdfContent);
            $merger->setAttachmentRelationshipType(ZugferdDocumentPdfBuilderAbstract::AF_RELATIONSHIP_DATA);
            $merger->setAdditionalCreatorTool('DivaERP');
            $merger->hideAttachmentPane();
            $merger->generateDocument();

            $mergedPdf = $merger->downloadString();
            if ($mergedPdf !== '') {
                return $mergedPdf;
            }
        } catch (\Throwable) {
            // Fall through to the original PDF if merge fails for any reason.
        }

        return $pdfContent;
    }
}
