<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use ZipArchive;

/**
 * Builds a synthetic chapter .docx exercising every category a manuscript formatting policy can
 * target — body text, a level-2 heading, a level-3 heading, a numbered list with a nested level,
 * a table, an image with a caption, and a manual page break — used only to preview an admin's
 * manuscript_format_options policy against their own real template (see
 * DocumentTemplateController::previewManuscriptFormat()); never part of a real researcher's
 * submission.
 *
 * PHPWord's own built-in Heading styles carry no real <w:outlineLvl> the way a genuine
 * Word-authored heading style always does (see scripts/manuscript.py's own
 * resolve_outline_level(), which is deliberately never a style-name heuristic) — addOutlineLevels()
 * patches that in afterward so this fixture is classified as heading1/heading23 content the same
 * structural way any real admin template's own headings are, rather than silently falling through
 * to "body" for lack of it.
 */
class ManuscriptFormatPreviewBuilder
{
    private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public function build(): string
    {
        $phpWord = new PhpWord;
        $phpWord->addTitleStyle(1, ['bold' => true, 'size' => 16]);
        $phpWord->addTitleStyle(2, ['bold' => true, 'size' => 13]);
        $phpWord->addTitleStyle(3, ['bold' => true, 'size' => 12]);
        $phpWord->addParagraphStyle('Caption', ['alignment' => Jc::CENTER, 'spaceBefore' => 120]);
        $phpWord->addNumberingStyle('previewList', [
            'type' => 'multilevel',
            'levels' => [
                ['format' => 'decimal', 'text' => '%1.', 'left' => 360, 'hanging' => 360, 'tabPos' => 360],
                ['format' => 'lowerLetter', 'text' => '%2.', 'left' => 720, 'hanging' => 360, 'tabPos' => 720],
            ],
        ]);

        $section = $phpWord->addSection();

        $section->addText(
            'This is sample body text demonstrating the body formatting rule below - font, '
            .'size, alignment, line spacing, paragraph spacing and first-line indent all come '
            .'from the "Body text" section of this policy, applied only to this chapter\'s own '
            .'content.'
        );

        $section->addTitle('Sample Level-2 Heading', 2);
        $section->addText('Body text immediately following a level-2 heading.');

        $section->addTitle('Sample Level-3 Heading', 3);
        $section->addText('Body text immediately following a level-3 heading.');

        $section->addListItem('First numbered item', 0, null, 'previewList');
        $section->addListItem('Second numbered item', 0, null, 'previewList');
        $section->addListItem('A nested item', 1, null, 'previewList');

        $table = $section->addTable();
        $table->addRow();
        $table->addCell(2500)->addText('Column A');
        $table->addCell(2500)->addText('Column B');
        $table->addRow();
        $table->addCell(2500)->addText('1');
        $table->addCell(2500)->addText('2');

        $imagePath = $this->swatchImagePath();
        $section->addImage($imagePath, ['width' => 90, 'height' => 60]);

        $section->addText('Figure 1: Sample caption text.', null, 'Caption');

        $section->addPageBreak();
        $section->addText('Content appearing after a manual page break.');

        $tmpPath = tempnam(sys_get_temp_dir(), 'manuscript-preview-').'.docx';

        try {
            // The writer reads the image's bytes from $imagePath at save() time, not when
            // addImage() was called — it must still exist on disk here.
            IOFactory::createWriter($phpWord, 'Word2007')->save($tmpPath);
            $this->addOutlineLevels($tmpPath, ['Heading1' => 0, 'Heading2' => 1, 'Heading3' => 2]);

            return file_get_contents($tmpPath);
        } finally {
            @unlink($tmpPath);
            @unlink($imagePath);
        }
    }

    private function swatchImagePath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'manuscript-preview-swatch-').'.png';
        $image = imagecreatetruecolor(90, 60);
        imagefill($image, 0, 0, imagecolorallocate($image, 178, 34, 52));
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    /**
     * @param  array<string, int>  $levelsByStyleId
     */
    private function addOutlineLevels(string $docxPath, array $levelsByStyleId): void
    {
        $zip = new ZipArchive;
        $zip->open($docxPath);
        $xml = $zip->getFromName('word/styles.xml');

        $document = new DOMDocument;
        $document->loadXML($xml);

        foreach ($document->getElementsByTagNameNS(self::NS_W, 'style') as $style) {
            /** @var DOMElement $style */
            $styleId = $style->getAttributeNS(self::NS_W, 'styleId');

            if (! isset($levelsByStyleId[$styleId])) {
                continue;
            }

            $pPr = $document->createElementNS(self::NS_W, 'w:pPr');
            $outlineLvl = $document->createElementNS(self::NS_W, 'w:outlineLvl');
            $outlineLvl->setAttributeNS(self::NS_W, 'w:val', (string) $levelsByStyleId[$styleId]);
            $pPr->appendChild($outlineLvl);

            $rPr = $style->getElementsByTagNameNS(self::NS_W, 'rPr')->item(0);

            if ($rPr !== null) {
                $style->insertBefore($pPr, $rPr);
            } else {
                $style->appendChild($pPr);
            }
        }

        $zip->deleteName('word/styles.xml');
        $zip->addFromString('word/styles.xml', $document->saveXML($document->documentElement));
        $zip->close();
    }
}
