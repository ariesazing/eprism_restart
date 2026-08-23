<?php

namespace App\Services;

/**
 * The pure placeholder-substitution engine shared by every admin-authored HTML template in
 * this app (research-submission chapters via SubmissionHtmlTemplateRenderer, and RAPM's
 * review-summary/routing-slip documents via RapmTemplateRenderer). Deliberately simple string
 * substitution, not a full templating language — admin-authored content is never evaluated as
 * code, only searched/replaced, so an edited template can't become a code-injection surface.
 *
 * Two placeholder forms:
 *   - ${key}                          scalar substitution (HTML-escaped, unless the caller
 *                                      marks the value 'raw' => true)
 *   - {{#each key}}...${col}...{{/each}}  clones the fragment once per row in `key`, with
 *                                      ${col} scoped to the current row
 *
 * Placeholders with no matching data are left as literal text rather than blanked, so an
 * admin editing a template can see their own mistakes instead of silently losing content.
 */
class PlaceholderEngine
{
    /**
     * @param  array<string, array{value: string, raw: bool}>  $scalars
     */
    public function substituteScalars(string $html, array $scalars): string
    {
        return preg_replace_callback(
            '/\$\{([a-zA-Z0-9_]+)\}/',
            function (array $match) use ($scalars) {
                if (! array_key_exists($match[1], $scalars)) {
                    return $match[0];
                }

                $entry = $scalars[$match[1]];

                return $entry['raw'] ? $entry['value'] : e($entry['value']);
            },
            $html
        );
    }

    /**
     * @param  array<string, array<int, array<string, string>>>  $each
     * @param  (callable(string $key, string $rowHtml, array<string, string> $row): string)|null  $rowPostProcess
     *         Optional hook to further transform each rendered row (e.g. swapping in a real
     *         image for a placeholder marker) after its own ${col} substitution has run.
     */
    public function substituteEachBlocks(string $html, array $each, ?callable $rowPostProcess = null): string
    {
        return preg_replace_callback(
            '/\{\{#each\s+([a-zA-Z0-9_]+)\s*\}\}(.*?)\{\{\/each\}\}/s',
            function (array $match) use ($each, $rowPostProcess) {
                [, $key, $fragment] = $match;
                $rows = $each[$key] ?? [];

                $rendered = '';
                foreach ($rows as $row) {
                    $rowHtml = $this->substituteRow($fragment, $row);

                    if ($rowPostProcess !== null) {
                        $rowHtml = $rowPostProcess($key, $rowHtml, $row);
                    }

                    $rendered .= $rowHtml;
                }

                return $rendered;
            },
            $html
        );
    }

    /**
     * canvas-editor's table element has no way to represent stray text sitting between
     * <tr> elements, so a {{#each key}}...{{/each}} wrapper placed around a table's
     * repeating row is silently dropped the moment an admin opens and saves a template
     * through the WYSIWYG editor — verified by round-tripping a seeded template through
     * it, which left a bare row of literal ${col} placeholders with no {{#each}}/{{/each}}
     * around it at all. substituteEachBlocks() already handles the pristine, still-wrapped
     * case; this is the fallback for the (far more common, once a human has touched the
     * editor) already-corrupted case: any <tr> whose cells reference every column of a
     * table is treated as that table's repeating row template, independent of whether
     * {{#each}} markup survived around it.
     *
     * @param  array<string, array<int, array<string, string>>>  $each
     * @param  array<string, array<int, string>>  $columnKeysByKey  Which ${col} keys identify each each-block's row template.
     */
    public function substituteBareTableRows(string $html, array $each, array $columnKeysByKey): string
    {
        foreach ($columnKeysByKey as $key => $columnKeys) {
            $rows = $each[$key] ?? [];
            $replaced = false;

            $html = preg_replace_callback(
                '/<tr\b[^>]*>.*?<\/tr>/is',
                function (array $match) use ($columnKeys, $rows, &$replaced) {
                    if ($replaced || ! collect($columnKeys)->every(fn ($key) => str_contains($match[0], '${'.$key.'}'))) {
                        return $match[0];
                    }

                    $replaced = true;

                    $rendered = '';
                    foreach ($rows as $row) {
                        $rendered .= $this->substituteRow($match[0], $row);
                    }

                    return $rendered;
                },
                $html
            );
        }

        return $html;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function substituteRow(string $fragment, array $row): string
    {
        return preg_replace_callback(
            '/\$\{([a-zA-Z0-9_]+)\}/',
            fn (array $inner) => array_key_exists($inner[1], $row) ? e($row[$inner[1]]) : $inner[0],
            $fragment
        );
    }
}
