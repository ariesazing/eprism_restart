<?php

namespace Tests\Feature;

use App\Services\PdfFontFamilyResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

/**
 * Companion to PdfFontFallbackTest: that one covers a font-family dompdf can't match at all
 * (falls through to config('dompdf.options.default_font')). This one covers the four values
 * resources/views/admin/document-templates/partials/auto-format-fields.blade.php actually
 * offers in its "Font Family" dropdown ('serif', 'times', 'sans-serif', 'courier') — dompdf
 * *does* match these (they're real entries in installed-fonts.dist.json), so default_font
 * never kicks in, but they resolve to the non-embedded, WinAnsi/Latin-1-only PDF core fonts
 * (Times-Roman, Helvetica, Courier) rather than a Unicode-capable one.
 */
class PdfAutoFormatFontFamilyTest extends TestCase
{
    public function test_broken_generic_aliases_resolve_to_a_unicode_capable_dejavu_family(): void
    {
        $this->assertSame('DejaVu Serif', PdfFontFamilyResolver::normalize('serif'));
        $this->assertSame('DejaVu Serif', PdfFontFamilyResolver::normalize('times'));
        $this->assertSame('DejaVu Sans', PdfFontFamilyResolver::normalize('sans-serif'));
        $this->assertSame('DejaVu Sans Mono', PdfFontFamilyResolver::normalize('courier'));

        // Case/whitespace from a stray admin edit shouldn't defeat the match.
        $this->assertSame('DejaVu Serif', PdfFontFamilyResolver::normalize(' Times '));
        $this->assertSame('DejaVu Serif', PdfFontFamilyResolver::normalize('SERIF'));
    }

    public function test_already_safe_or_unmatched_families_pass_through_unchanged(): void
    {
        $this->assertSame('DejaVu Sans', PdfFontFamilyResolver::normalize('DejaVu Sans'));
        $this->assertSame('DejaVu Serif', PdfFontFamilyResolver::normalize('DejaVu Serif'));
        $this->assertSame('Times New Roman', PdfFontFamilyResolver::normalize('Times New Roman'));
        $this->assertNull(PdfFontFamilyResolver::normalize(null));
        $this->assertSame('', PdfFontFamilyResolver::normalize(''));
    }

    /**
     * The resolved family names must actually exist in dompdf's font table, or this "fix"
     * would just swap one broken font for a missing one.
     */
    public function test_resolved_families_are_registered_and_unicode_capable_in_dompdf(): void
    {
        $fontMetrics = Pdf::getFontMetrics();

        foreach (['DejaVu Serif', 'DejaVu Sans', 'DejaVu Sans Mono'] as $family) {
            $resolved = $fontMetrics->getFont($family, 'normal');

            $this->assertNotNull($resolved, "Expected \"{$family}\" to be a font dompdf recognizes.");
            $this->assertStringContainsStringIgnoringCase('dejavu', $resolved);
        }
    }
}
