<?php

namespace App\Services;

class PdfFontFamilyResolver
{
    /**
     * Generic CSS font-family keywords that dompdf *does* successfully match (they're real
     * entries in vendor/dompdf/dompdf/lib/fonts/installed-fonts.dist.json), so — unlike a
     * genuinely unmatched family such as canvas-editor's 'Microsoft YaHei' — they never fall
     * through to config('dompdf.options.default_font') (see config/dompdf.php and
     * PdfFontFallbackTest). The catch: every one of these entries resolves to a PDF "Base 14"
     * core font (Times-Roman, Helvetica, Courier) that isn't embedded and is limited to
     * WinAnsiEncoding/Latin-1, so any symbol outside that range — bullets, math operators,
     * Greek letters, arrows, most currency symbols, checkmarks, fractions beyond ¼½¾, ₱, etc.
     * — renders as a literal "?", exactly like the default_font gap the other fix covers.
     *
     * These four exact values ('serif', 'times', 'sans-serif', 'courier') are also literally
     * what resources/views/admin/document-templates/partials/auto-format-fields.blade.php
     * offers in its "Font Family" dropdown, so an admin picking "Times (serif)" or "Courier
     * (monospace)" for a template's auto-format was hitting this gap on every submission that
     * template applies to. Map each to the same Unicode-capable, already-embedded DejaVu
     * family this app already relies on elsewhere, rather than the non-embedded core font.
     */
    private const ALIASES = [
        'serif' => 'DejaVu Serif',
        'times' => 'DejaVu Serif',
        'sans-serif' => 'DejaVu Sans',
        'helvetica' => 'DejaVu Sans',
        'courier' => 'DejaVu Sans Mono',
        'monospace' => 'DejaVu Sans Mono',
    ];

    /**
     * Rewrites a known-broken generic font-family alias to its Unicode-capable equivalent.
     * Anything else — 'DejaVu Sans'/'DejaVu Serif' already, or a researcher/Word-style family
     * name canvas-editor stamped on (e.g. 'Times New Roman', 'Calibri') that dompdf doesn't
     * match at all and therefore already falls through to default_font — passes through
     * unchanged.
     */
    public static function normalize(?string $family): ?string
    {
        if ($family === null || $family === '') {
            return $family;
        }

        $key = strtolower(trim($family, " \t\n\r\0\x0B'\""));

        return self::ALIASES[$key] ?? $family;
    }
}
