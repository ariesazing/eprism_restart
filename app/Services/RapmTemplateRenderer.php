<?php

namespace App\Services;

/**
 * Fills an admin-authored RAPM template (review-summary/routing-slip HTML) with prebuilt
 * data. Unlike SubmissionHtmlTemplateRenderer, this has no notion of a submission's own
 * chapters/proponents — RapmDataBuilder is responsible for shaping the review/routing data
 * into the same scalar/each vocabulary PlaceholderEngine expects.
 */
class RapmTemplateRenderer
{
    public function __construct(
        private readonly PlaceholderEngine $engine,
    ) {}

    /**
     * @param  array<string, array{value: string, raw: bool}>  $scalars
     * @param  array<string, array<int, array<string, string>>>  $each
     */
    public function render(string $templateHtml, array $scalars, array $each): string
    {
        $html = $this->engine->substituteEachBlocks($templateHtml, $each);

        // Column keys for the bare-table fallback are read straight off the row data itself
        // (rather than a separate section/column registry, which RAPM templates don't have)
        // — every row in an each-block already carries the same set of keys.
        $columnKeysByKey = array_map(fn (array $rows) => array_keys($rows[0] ?? []), $each);
        $html = $this->engine->substituteBareTableRows($html, $each, $columnKeysByKey);

        return $this->engine->substituteScalars($html, $scalars);
    }
}
