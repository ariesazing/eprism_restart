<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Server-side half of the same geometric-sanity contract pdf-review.js enforces client-side
 * (computeRelativeRects()'s real clipping against the page box when a highlight is first made,
 * sanitizeStoredRect()'s defensive re-check when rendering one back) — rejects a highlight
 * anchor whose rects are structurally invalid or wildly out of bounds before it's ever
 * persisted, rather than trusting whatever geometry the client happened to send. An empty
 * `rects` array is valid (a comment with no specific highlight, e.g. a whole-page note).
 */
class ValidHighlightAnchor implements ValidationRule
{
    /**
     * Percentage-of-page-box values are expected in [0, 100] — this small tolerance only
     * absorbs floating-point/rounding noise from the browser's own geometry, not a
     * meaningfully out-of-bounds rect.
     */
    private const TOLERANCE = 1.0;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || ! array_key_exists('rects', $value) || ! is_array($value['rects'])) {
            $fail('The :attribute must include a "rects" array.');

            return;
        }

        foreach ($value['rects'] as $index => $rect) {
            if (! is_array($rect)) {
                $fail("The :attribute's rect #{$index} must be an object.");

                return;
            }

            foreach (['top', 'left', 'width', 'height'] as $key) {
                if (! array_key_exists($key, $rect) || ! is_numeric($rect[$key]) || ! is_finite((float) $rect[$key])) {
                    $fail("The :attribute's rect #{$index} has an invalid \"{$key}\" value.");

                    return;
                }
            }

            $top = (float) $rect['top'];
            $left = (float) $rect['left'];
            $width = (float) $rect['width'];
            $height = (float) $rect['height'];

            if ($width <= 0 || $height <= 0) {
                $fail("The :attribute's rect #{$index} must have a positive width and height.");

                return;
            }

            $bounds = [$top, $left, $top + $height, $left + $width];

            foreach ($bounds as $bound) {
                if ($bound < -self::TOLERANCE || $bound > 100 + self::TOLERANCE) {
                    $fail("The :attribute's rect #{$index} is out of bounds for the page.");

                    return;
                }
            }
        }
    }
}
