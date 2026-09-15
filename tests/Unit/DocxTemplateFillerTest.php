<?php

namespace Tests\Unit;

use App\Services\DocxTemplateFiller;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\TemplateProcessor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DocxTemplateFillerTest extends TestCase
{
    /**
     * Builds a tiny fixture .docx programmatically (rather than committing a binary file):
     * one scalar placeholder paragraph, and a table whose single data row carries the
     * proponents each-block's column placeholders (including an image placeholder, when
     * $withPhoto is true, matching how a real proponents table also carries one).
     */
    private function makeFixtureDocx(bool $withPhoto = false): string
    {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('${title}');

        $table = $section->addTable();
        $table->addRow();
        $table->addCell()->addText('${proponent_name}');
        $table->addCell()->addText('${proponent_position}');

        if ($withPhoto) {
            $table->addCell()->addText('${proponent_photo}');
        }

        $path = tempnam(sys_get_temp_dir(), 'docx-fixture-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    private function makeFixtureImage(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'docx-fixture-img-').'.png';
        $image = imagecreatetruecolor(4, 4);
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    private function mainPartXml(string $docxBytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'docx-check-').'.docx';
        file_put_contents($path, $docxBytes);

        $zip = new \ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($path);

        return $xml;
    }

    public function test_fills_scalars_and_clones_a_table_row_per_data_item(): void
    {
        $fixture = $this->makeFixtureDocx();

        try {
            $result = (new DocxTemplateFiller)->fill($fixture, [
                'title' => ['value' => 'Community-Based Learning Interventions'],
            ], [
                'proponents' => [
                    'columns' => ['proponent_name' => 'text', 'proponent_position' => 'text'],
                    'rows' => [
                        ['proponent_name' => 'Delacruz, Ana', 'proponent_position' => 'Teacher I'],
                        ['proponent_name' => 'Santos, Ben', 'proponent_position' => 'Teacher II'],
                    ],
                ],
            ]);
        } finally {
            unlink($fixture);
        }

        $xml = $this->mainPartXml($result);

        $this->assertStringContainsString('Community-Based Learning Interventions', $xml);
        $this->assertStringContainsString('Delacruz, Ana', $xml);
        $this->assertStringContainsString('Teacher I', $xml);
        $this->assertStringContainsString('Santos, Ben', $xml);
        $this->assertStringContainsString('Teacher II', $xml);
        $this->assertStringNotContainsString('${title}', $xml);
        $this->assertStringNotContainsString('${proponent_name}', $xml);
    }

    public function test_an_empty_each_block_removes_the_template_row_instead_of_leaving_placeholders(): void
    {
        $fixture = $this->makeFixtureDocx();

        try {
            $result = (new DocxTemplateFiller)->fill($fixture, [
                'title' => ['value' => 'Draft'],
            ], [
                'proponents' => ['columns' => ['proponent_name' => 'text', 'proponent_position' => 'text'], 'rows' => []],
            ]);
        } finally {
            unlink($fixture);
        }

        $xml = $this->mainPartXml($result);

        $this->assertStringNotContainsString('${proponent_name}', $xml);
        $this->assertStringNotContainsString('proponent_name#1', $xml);
    }

    public function test_a_missing_each_block_placeholder_raises_a_named_error_instead_of_failing_silently(): void
    {
        $fixture = $this->makeFixtureDocx();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing the "cost_estimates" each-block');

        try {
            (new DocxTemplateFiller)->fill($fixture, [], [
                'cost_estimates' => ['columns' => ['item' => 'text', 'amount' => 'text'], 'rows' => [['item' => 'Bond paper', 'amount' => '500']]],
            ]);
        } finally {
            unlink($fixture);
        }
    }

    public function test_an_image_column_is_inserted_as_a_real_image_not_a_path_string(): void
    {
        $fixture = $this->makeFixtureDocx(withPhoto: true);
        $photo = $this->makeFixtureImage();

        try {
            $result = (new DocxTemplateFiller)->fill($fixture, [
                'title' => ['value' => 'Draft'],
            ], [
                'proponents' => [
                    'columns' => ['proponent_name' => 'text', 'proponent_position' => 'text', 'proponent_photo' => 'image'],
                    'rows' => [
                        ['proponent_name' => 'Delacruz, Ana', 'proponent_position' => 'Teacher I', 'proponent_photo' => $photo],
                    ],
                ],
            ]);
        } finally {
            unlink($fixture);
            unlink($photo);
        }

        $path = tempnam(sys_get_temp_dir(), 'docx-check-').'.docx';
        file_put_contents($path, $result);

        $zip = new \ZipArchive;
        $zip->open($path);
        $mediaFound = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_starts_with($zip->getNameIndex($i), 'word/media/')) {
                $mediaFound = true;
                break;
            }
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($path);

        $this->assertTrue($mediaFound, 'Expected the filled docx to contain an embedded media file for the photo.');
        $this->assertStringNotContainsString('${proponent_photo}', $xml);
        // The photo's own on-disk path must never leak into the document as literal text —
        // only setImageValue()'s real <w:pict>/relationship wiring should reference it.
        $this->assertStringNotContainsString($photo, $xml);
    }

    public function test_a_blank_image_value_clears_the_token_instead_of_erroring(): void
    {
        // A proponent who hasn't uploaded a photo yet is a normal, expected case — not a
        // template authoring mistake — so this must clear the placeholder cleanly rather than
        // calling PHPWord's setImageValue() with an empty path (which throws).
        $fixture = $this->makeFixtureDocx(withPhoto: true);

        try {
            $result = (new DocxTemplateFiller)->fill($fixture, [
                'title' => ['value' => 'Draft'],
            ], [
                'proponents' => [
                    'columns' => ['proponent_name' => 'text', 'proponent_position' => 'text', 'proponent_photo' => 'image'],
                    'rows' => [
                        ['proponent_name' => 'Delacruz, Ana', 'proponent_position' => 'Teacher I', 'proponent_photo' => ''],
                    ],
                ],
            ]);
        } finally {
            unlink($fixture);
        }

        $xml = $this->mainPartXml($result);

        $this->assertStringNotContainsString('${proponent_photo}', $xml);
        $this->assertStringContainsString('Delacruz, Ana', $xml);
    }

    /**
     * A block-marker fixture: a paragraph containing exactly ${proponents}, the repeating
     * content, then a paragraph containing exactly ${/proponents} — no table at all. Mirrors
     * how an admin who doesn't want the proponents list tabled would author it.
     */
    private function makeBlockFixtureDocx(): string
    {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('${title}');
        $section->addText('${proponents}');
        $section->addText('${proponent_name} — ${proponent_position}');
        $section->addText('${/proponents}');

        $path = tempnam(sys_get_temp_dir(), 'docx-fixture-block-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    public function test_a_non_tabular_block_marker_repeats_the_same_as_a_table_row(): void
    {
        $fixture = $this->makeBlockFixtureDocx();

        try {
            $result = (new DocxTemplateFiller)->fill($fixture, [
                'title' => ['value' => 'Draft'],
            ], [
                'proponents' => [
                    'columns' => ['proponent_name' => 'text', 'proponent_position' => 'text'],
                    'rows' => [
                        ['proponent_name' => 'Delacruz, Ana', 'proponent_position' => 'Teacher I'],
                        ['proponent_name' => 'Santos, Ben', 'proponent_position' => 'Teacher II'],
                    ],
                ],
            ]);
        } finally {
            unlink($fixture);
        }

        $xml = $this->mainPartXml($result);

        $this->assertStringContainsString('Delacruz, Ana', $xml);
        $this->assertStringContainsString('Teacher I', $xml);
        $this->assertStringContainsString('Santos, Ben', $xml);
        $this->assertStringContainsString('Teacher II', $xml);
        $this->assertStringNotContainsString('${proponents}', $xml);
        $this->assertStringNotContainsString('${/proponents}', $xml);
        $this->assertStringNotContainsString('${proponent_name}', $xml);
    }

    public function test_neither_a_table_row_nor_block_markers_raises_a_named_error(): void
    {
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText('${title} with no proponents markup at all.');
        $path = tempnam(sys_get_temp_dir(), 'docx-fixture-neither-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing the "proponents" each-block');

        try {
            (new DocxTemplateFiller)->fill($path, [], [
                'proponents' => ['columns' => ['proponent_name' => 'text'], 'rows' => [['proponent_name' => 'Delacruz, Ana']]],
            ]);
        } finally {
            unlink($path);
        }
    }

    public function test_template_processor_still_usable_confirms_fixture_is_valid(): void
    {
        // Sanity check on the fixture-building helper itself, independent of DocxTemplateFiller
        // — if this fails, a DocxTemplateFiller test failure means the fixture is broken, not
        // the class under test.
        $fixture = $this->makeFixtureDocx();

        try {
            $processor = new TemplateProcessor($fixture);
            $this->assertContains('title', $processor->getVariables());
            $this->assertContains('proponent_name', $processor->getVariables());
        } finally {
            unlink($fixture);
        }
    }
}
