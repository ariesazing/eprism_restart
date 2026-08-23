<?php

namespace Database\Seeders\Concerns;

use DOMDocument;
use DOMNode;

/**
 * Converts a template fixture's HTML into a canvas-editor JSON document, so admins see real
 * starting content instead of a blank editor the first time they open a seeded template.
 * Intentionally narrow: it only understands the exact tag vocabulary the fixture files use
 * (p, strong, table/tr/td, ul/li), not general HTML. If it's wrong somehow, only the seeded
 * editor JSON is affected — body_html/header_html/footer_html (the source of truth for
 * rendering and PDFs) are seeded independently, straight from the fixture files.
 */
trait ConvertsHtmlToCanvasEditorElements
{
    /**
     * @return array<int, array<string, mixed>>
     */
    private function elementsFromHtml(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div>'.$html.'</div>');
        libxml_clear_errors();

        $root = $dom->getElementsByTagName('div')->item(0);
        $elements = [];

        if ($root) {
            foreach ($root->childNodes as $node) {
                $this->appendBlockElements($node, $elements);
            }
        }

        return $elements;
    }

    /**
     * @param  array<int, array<string, mixed>>  $elements
     */
    private function appendBlockElements(DOMNode $node, array &$elements): void
    {
        if ($node->nodeName === 'p') {
            foreach ($node->childNodes as $child) {
                $this->appendInlineElement($child, $elements);
            }
            $elements[] = ['value' => "\n"];

            return;
        }

        if ($node->nodeName === 'ul') {
            foreach ($node->childNodes as $li) {
                if ($li->nodeName !== 'li') {
                    continue;
                }
                $elements[] = ['value' => '• '.trim($li->textContent)];
                $elements[] = ['value' => "\n"];
            }

            return;
        }

        if ($node->nodeName === 'table') {
            $elements[] = $this->tableElement($node);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $elements
     */
    private function appendInlineElement(DOMNode $node, array &$elements): void
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            if ($node->textContent !== '') {
                $elements[] = ['value' => $node->textContent];
            }

            return;
        }

        if ($node->nodeName === 'strong') {
            $elements[] = ['value' => $node->textContent, 'bold' => true];

            return;
        }

        if (trim($node->textContent) !== '') {
            $elements[] = ['value' => $node->textContent];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function tableElement(DOMNode $table): array
    {
        $rows = [];

        foreach ($table->getElementsByTagName('tr') as $tr) {
            $cells = [];

            foreach ($tr->childNodes as $cellNode) {
                if (! in_array($cellNode->nodeName, ['td', 'th'], true)) {
                    continue;
                }

                $cellElements = [];
                foreach ($cellNode->childNodes as $child) {
                    $this->appendBlockElements($child, $cellElements);
                }

                $last = end($cellElements);
                if ($last !== false && ($last['value'] ?? null) === "\n") {
                    array_pop($cellElements);
                }

                $cells[] = [
                    'colspan' => 1,
                    'rowspan' => 1,
                    'value' => $cellElements ?: [['value' => '']],
                ];
            }

            $rows[] = ['height' => 40, 'tdList' => $cells];
        }

        $columnCount = max(array_map(fn (array $row) => count($row['tdList']), $rows) ?: [1]);
        $columnWidth = intdiv(600, max($columnCount, 1));

        return [
            'type' => 'table',
            'value' => '',
            'colgroup' => array_fill(0, $columnCount, ['width' => $columnWidth]),
            'trList' => $rows,
        ];
    }
}
