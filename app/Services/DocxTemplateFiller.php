<?php

namespace App\Services;

use PhpOffice\PhpWord\Exception\Exception as PhpWordException;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;

/**
 * Fills an admin-authored .docx template's `${key}` placeholders with real data — the docx
 * counterpart to PlaceholderEngine, which does the same job by regex-substituting HTML strings.
 * Backed by PHPWord's TemplateProcessor, whose default macro delimiters are already `${`/`}`,
 * matching the placeholder syntax admins already know from the HTML-template era exactly — no
 * retraining needed for scalars.
 *
 * Repeating data (proponents, a table-type chapter's rows) no longer needs the old
 * `{{#each key}}...{{/each}}` wrapper syntax as literal text — but the admin does still get an
 * equivalent, real-Word-native version of it for content that shouldn't be a table: a paragraph
 * containing exactly `${key}`, the repeating content, then a paragraph containing exactly
 * `${/key}` — TemplateProcessor::cloneBlock() repeats everything between those two markers (and
 * removes the markers themselves), indexing `${col}` tokens inside it the same `#1`/`#2`... way
 * cloneRow() indexes a table row's own tokens. Tables are still fully supported (and tried
 * first, see fillEachBlock()) for chapters that genuinely are tabular data (cost estimates, a
 * work plan) — this is purely an alternative for content an admin doesn't want tabled, most
 * commonly the proponents list. Either way, this also retires
 * PlaceholderEngine::substituteBareTableRows()'s "canvas-editor strips the wrapper" repair hack
 * for the table case — there's no wrapper left to strip there.
 *
 * An image column (e.g. a proponent's photo) works the same way, just via setImageValue()
 * instead of setValue() — PHPWord adds the image to the docx's own media/relationships itself,
 * so this doesn't run into the "splicing XML from a different docx package" problem described
 * below for chapters. The admin can size it inline in the template text itself, PHPWord's own
 * convention: `${proponent_photo:width=80:height=80}`.
 *
 * Does not (and cannot) handle a rich_text chapter's own content — that's assembled at the PDF
 * level instead (see SubmissionPdfMerger), because a chapter's docx paragraphs carry image/
 * style/numbering references that only resolve within that chapter's own docx package; splicing
 * that XML into a different template without remapping every one of those references would
 * produce broken images and mangled formatting.
 */
class DocxTemplateFiller
{
    /**
     * `$each`'s `columns` is each block's own known column schema — column key => `'text'` or
     * `'image'` — independent of how many `rows` there actually are, needed because
     * TemplateProcessor::cloneRow() locates the template's table row by searching for a literal
     * `${column}` placeholder inside it (there's no `${key}` token for the group itself, since
     * admins only ever type the individual column tokens they can see in the table), and that
     * lookup has to work even for a zero-row group.
     *
     * @param  array<string, array{value: string, raw?: bool}|string>  $scalars
     * @param  array<string, array{columns: array<string, string>, rows: array<int, array<string, string>>}>  $each
     */
    public function fill(string $docxPath, array $scalars, array $each, bool $optionalBlocks = false): string
    {
        $processor = new TemplateProcessor($docxPath);

        foreach ($scalars as $key => $entry) {
            $processor->setValue($key, is_array($entry) ? (string) ($entry['value'] ?? '') : (string) $entry);
        }

        foreach ($each as $key => $group) {
            if ($optionalBlocks) {
                $variables = $processor->getVariables();
                $present = in_array($key, $variables, true);
                foreach (array_keys($group['columns']) as $column) {
                    $present = $present || collect($variables)->contains(fn ($variable) => $variable === $column || str_starts_with($variable, $column.':'));
                }
                if (! $present) {
                    continue;
                }
            }
            $this->fillEachBlock($processor, $key, $group['columns'], $group['rows']);
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'docx-fill-').'.docx';

        try {
            $processor->saveAs($tmpPath);

            return file_get_contents($tmpPath);
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * @param  array<string, string>  $columns
     * @param  array<int, array<string, string>>  $rows
     */
    private function fillEachBlock(TemplateProcessor $processor, string $key, array $columns, array $rows): void
    {
        if ($columns === []) {
            return;
        }

        // Any one of the group's own columns works as the row/block locator — cloneRow()/
        // cloneBlock() both index and clone *every* placeholder found inside what they clone,
        // not just the one used to find it — but the locator itself needs an exact, argument-
        // free `${column}` match, so a plain text column is preferred over an image column
        // (which the admin may have given inline sizing args, e.g. `${proponent_photo:width=80}`,
        // that a bare search string would never find).
        $locator = array_search('text', $columns, true) ?: array_key_first($columns);

        $rowException = null;

        try {
            $processor->cloneRow($locator, count($rows));
        } catch (PhpWordException $e) {
            $rowException = $e;
        }

        if ($rowException !== null) {
            // Table row not found — try the non-tabular alternative before giving up. Tried
            // second, not first: a genuinely tabular chapter (cost estimates, a work plan) is
            // the far more common case, and would otherwise pay for a failed regex match on
            // every single fill. Note this searches for the *first* occurrence of $locator in
            // the whole document and walks outward from there (a PHPWord limitation, not one
            // introduced here) — a template with unrelated tables *before* this each-block's own
            // content could in principle mismatch; not a real risk for content that (like
            // proponents) sits near the top of the document, ahead of any chapter tables.
            $blockFound = $processor->cloneBlock($key, count($rows), true, true) !== null;

            if (! $blockFound) {
                // PHPWord's own message doesn't name the block, and a missing/misauthored
                // each-block is the single most likely admin mistake here — surfacing which
                // key/column is missing turns a stack trace into something an admin can
                // actually act on, matching this codebase's "leave placeholders visible/errors
                // loud, never silently blank" stance.
                throw new RuntimeException(
                    "Template is missing the \"{$key}\" each-block — expected a \${$locator} placeholder either inside one table row, or between a \${$key} paragraph and a \${/{$key}} paragraph.",
                    previous: $rowException
                );
            }
        }

        foreach ($rows as $index => $row) {
            foreach ($row as $column => $value) {
                $placeholder = "{$column}#".($index + 1);

                // A blank image value (no photo on file for this row) clears the token to
                // nothing rather than calling setImageValue() with an empty path, which would
                // error — an absent photo is a normal, expected case (a proponent who hasn't
                // uploaded one yet), not a template authoring mistake.
                if (($columns[$column] ?? 'text') === 'image' && $value !== '') {
                    $processor->setImageValue($placeholder, (string) $value);
                } else {
                    $processor->setValue($placeholder, (string) $value);
                }
            }
        }
    }
}
