<?php

namespace Tests\Concerns;

use App\Services\OnlyOfficeService;
use ZipArchive;

trait FakesRapmConversion
{
    private function docxXml(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rapm-test-');
        file_put_contents($path, $bytes);
        $zip = new ZipArchive;
        try {
            $this->assertTrue($zip->open($path));
            $xml = $zip->getFromName('word/document.xml');
            $this->assertNotFalse($xml);
            $this->assertTrue((new \DOMDocument)->loadXML($xml));

            return $xml;
        } finally {
            $zip->close();
            unlink($path);
        }
    }

    private function fakeRapmConversion(): void
    {
        // Exercise real DOCX generation/filling; only the external converter is mocked.
        $this->mock(OnlyOfficeService::class, function ($mock) {
            $mock->shouldReceive('convertFilledDocxToPdf')->withArgs(fn ($bytes, $normalize) => $normalize === false)
                ->andReturnUsing(fn ($bytes) => '%PDF-test '.html_entity_decode(strip_tags($this->docxXml($bytes)), ENT_QUOTES | ENT_XML1));
        });
    }
}
