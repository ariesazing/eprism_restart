<?php

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * canvas-editor (the app's rich-text editor) stamps an explicit font-family onto every
 * text run it exports — usually its own library default, 'Microsoft YaHei', or one of the
 * Word-style fonts offered in its toolbar (see WORD_FONTS in
 * resources/js/document-editor/toolbar.js) — and none of those names are registered fonts
 * as far as dompdf is concerned (vendor/dompdf/dompdf/lib/fonts/installed-fonts.dist.json
 * only knows Helvetica/Times/Courier/Symbol/ZapfDingbats/DejaVu Sans/DejaVu Serif/DejaVu
 * Sans Mono). Per Dompdf\FontMetrics::getFont() / Css\Style::_get_font_family(), an
 * unmatched *explicit* font-family only falls through to config('dompdf.options.
 * default_font') once every candidate has failed — so almost all researcher-typed content
 * was silently landing on the *package* default ('serif' -> the core, non-embedded
 * Times-Roman font, limited to WinAnsiEncoding/Latin-1). Anything outside Latin-1 —
 * bullets, math operators, Greek letters, arrows, most currency symbols, checkmarks,
 * fractions beyond ¼½¾, the peso sign — rendered as a literal "?". config/dompdf.php
 * overrides default_font to 'DejaVu Sans', a real embedded TrueType font with broad
 * Unicode coverage, so the fallback actually renders those characters.
 */
class PdfFontFallbackTest extends TestCase
{
    public function test_default_font_is_overridden_to_a_unicode_capable_font(): void
    {
        $this->assertSame('DejaVu Sans', config('dompdf.options.default_font'));
    }

    public function test_an_unmatched_font_family_resolves_to_dejavu_not_the_core_times_font(): void
    {
        $fontMetrics = Pdf::getFontMetrics();

        // Mirrors what Dompdf\Css\Style::_get_font_family() does once every explicit
        // candidate in a font-family list (e.g. "Microsoft YaHei") has failed to match
        // a registered family: it calls getFont(null, ...), which is what actually
        // consults default_font.
        $resolved = $fontMetrics->getFont(null, 'normal');

        $this->assertNotNull($resolved);
        $this->assertStringContainsStringIgnoringCase('dejavusans', str_replace([' ', '-', '_'], '', $resolved));
    }

    /**
     * End-to-end: render the exact markup canvas-editor produces (an explicit, unmatched
     * font-family on every span) and confirm the characters that used to come out as "?"
     * survive into the actual PDF text layer. Skips gracefully if pdftotext isn't
     * available in this environment rather than failing the whole suite over tooling.
     */
    public function test_previously_broken_unicode_symbols_render_correctly_in_the_generated_pdf(): void
    {
        if (! Process::run('pdftotext -v')->successful() && ! Process::run('where pdftotext')->successful()) {
            $this->markTestSkipped('pdftotext is not available in this environment.');
        }

        $symbols = "‣ ◦ ▪ ▫ ‧ ⁃ ● ― ≈ ≠ ≤ ≥ √ ∞ Σ Δ π α β γ χ ₱ → ← ↑ ↓ ⇒ ⇐ ↔ № ✓ ✗ ⅓ ⅔ ✔ ✅ ❌ ⭐";

        $html = '<html><body><span style="font-family:\'Microsoft YaHei\';font-size:14px;">'
            .htmlspecialchars($symbols, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            .'</span></body></html>';

        $pdfBytes = Pdf::loadHTML($html)->setPaper('a4')->output();

        $pdfPath = tempnam(sys_get_temp_dir(), 'eprism_font_test_').'.pdf';
        $txtPath = substr($pdfPath, 0, -4).'.txt';
        file_put_contents($pdfPath, $pdfBytes);

        $result = Process::run(['pdftotext', '-enc', 'UTF-8', $pdfPath, $txtPath]);
        $this->assertTrue($result->successful(), $result->errorOutput());

        $extracted = file_get_contents($txtPath);

        @unlink($pdfPath);
        @unlink($txtPath);

        $this->assertStringNotContainsString('?', $extracted);

        foreach (preg_split('/\s+/u', trim($symbols)) as $symbol) {
            $this->assertStringContainsString($symbol, $extracted, "Expected \"{$symbol}\" to survive into the rendered PDF.");
        }
    }
}
