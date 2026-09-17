<?php

namespace Tests\Unit;

use App\Services\SubmissionPdfMerger;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * constrainImages() is private (pure string-rewriting, no PDF/DB dependency), so reflection
 * is the direct way to exercise the regression this locks in: a header/footer <img>'s
 * declared width/height are authored in the same 96dpi CSS-px convention the composed
 * content pages use, but TCPDF (used only for attachment pages) interprets a bare
 * width/height attribute as 72dpi points — every image must be unit-converted, not just
 * ones that already exceed the max height.
 */
class SubmissionPdfMergerConstrainImagesTest extends TestCase
{
    private function constrainImages(string $html, float $maxHeightPt): string
    {
        $method = new ReflectionMethod(SubmissionPdfMerger::class, 'constrainImages');
        $method->setAccessible(true);

        return $method->invoke(new SubmissionPdfMerger, $html, $maxHeightPt);
    }

    public function test_a_normal_sized_image_is_still_converted_from_px_to_pt(): void
    {
        // 100x40 is well under the 60pt max — previously left completely untouched.
        $html = $this->constrainImages('<img src="logo.png" width="100" height="40">', 60.0);

        $this->assertStringContainsString('width="75"', $html);
        $this->assertStringContainsString('height="30"', $html);
    }

    public function test_an_oversized_image_is_converted_then_clamped_to_the_max_height(): void
    {
        // 200x160 converts to 150x120pt, which still exceeds a 60pt max — must scale further.
        $html = $this->constrainImages('<img src="logo.png" width="200" height="160">', 60.0);

        $this->assertStringContainsString('height="60"', $html);
        $this->assertStringContainsString('width="75"', $html);
    }

    public function test_no_max_height_still_applies_the_unit_conversion(): void
    {
        $html = $this->constrainImages('<img src="logo.png" width="100" height="40">', 0.0);

        $this->assertStringContainsString('width="75"', $html);
        $this->assertStringContainsString('height="30"', $html);
    }
}
