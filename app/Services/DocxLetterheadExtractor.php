<?php

namespace App\Services;

use DOMDocument;
use RuntimeException;
use ZipArchive;

/**
 * Produces a blank-body variant of an admin's front-matter .docx template: same headers/
 * footers/styles/theme/page setup, but with every paragraph and table the admin actually typed
 * into the body removed. Used purely as the background SubmissionDocxPdfMerger stamps onto
 * every attachment page (see SubmissionDocxComposer::composeLetterhead()) — without this, the
 * front matter's own body content (title, proponents, tables) would bleed onto every other page
 * it's stamped behind, not just its own.
 */
class DocxLetterheadExtractor
{
    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public function extractBlankBodyDocx(string $templateDocxPath): string
    {
        $workingPath = tempnam(sys_get_temp_dir(), 'letterhead-').'.docx';
        copy($templateDocxPath, $workingPath);

        try {
            $zip = new ZipArchive;

            if ($zip->open($workingPath) !== true) {
                throw new RuntimeException("Could not open template docx at [{$templateDocxPath}].");
            }

            $documentXml = $zip->getFromName('word/document.xml');

            if ($documentXml === false) {
                $zip->close();

                throw new RuntimeException("Template docx at [{$templateDocxPath}] has no document body.");
            }

            $zip->addFromString('word/document.xml', $this->blankBody($documentXml));
            $zip->close();

            return file_get_contents($workingPath);
        } finally {
            @unlink($workingPath);
        }
    }

    private function blankBody(string $xml): string
    {
        $dom = new DOMDocument;
        $dom->loadXML($xml);

        $body = $dom->getElementsByTagNameNS(self::WORD_NS, 'body')->item(0);

        if ($body === null) {
            return $xml;
        }

        // sectPr carries the page's own setup (size, margins, header/footer references) and
        // must survive — everything else is exactly the body content a blank letterhead needs
        // to drop.
        foreach (iterator_to_array($body->childNodes) as $node) {
            if ($node->localName !== 'sectPr') {
                $body->removeChild($node);
            }
        }

        return $dom->saveXML();
    }
}
