<?php

namespace App\Similarity;

/**
 * HTML to plain-text paragraphs — for both a chapter's sanitized content_html and a fetched
 * web page. Block-level tags become paragraph breaks (so adjacent list items or table cells
 * don't fuse into one nonsense word), and non-content markup (scripts, styles, navigation)
 * is dropped before text is extracted.
 */
final class HtmlText
{
    /**
     * @return list<string> non-empty, whitespace-collapsed paragraphs
     */
    public static function paragraphs(string $html): array
    {
        $html = preg_replace('#<(script|style|noscript|svg|nav|footer|header|aside|form|iframe|template)\b[^>]*>.*?</\1\s*>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<\s*/?\s*(p|div|br|li|ul|ol|tr|td|th|table|h[1-6]|section|article|blockquote|hr|main|figure|figcaption)\b[^>]*>#i', "\n", $html) ?? $html;

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return self::split($text);
    }

    /**
     * @return list<string>
     */
    public static function split(string $text): array
    {
        $paragraphs = [];

        foreach (preg_split('/\R+/u', str_replace("\u{00A0}", ' ', $text)) ?: [] as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line) ?? $line);

            if ($line !== '') {
                $paragraphs[] = $line;
            }
        }

        return $paragraphs;
    }
}
