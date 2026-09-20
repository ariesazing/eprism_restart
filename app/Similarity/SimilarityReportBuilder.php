<?php

namespace App\Similarity;

use App\Models\SimilarityCheck;

/**
 * Turns a finished check into what the report page draws: the checked text cut into
 * plain/highlighted segments, and the list of sources those highlights belong to.
 *
 * Where several sources overlap the same words, each word is painted for the *best* matching
 * source only (sources are ranked by matched words, so rank 1 wins) — one colour per word, as
 * in Turnitin's report — while every source still lists its own full overlap in the side panel.
 */
final class SimilarityReportBuilder
{
    // Distinct at a glance and readable as a translucent highlight on white.
    private const PALETTE = [
        '#2563eb', '#db2777', '#059669', '#d97706', '#7c3aed', '#0891b2',
        '#dc2626', '#65a30d', '#c026d3', '#ea580c', '#0d9488', '#4f46e5',
    ];

    /**
     * @return array{
     *     sources: list<array{id: int, rank: int, title: string, url: ?string, host: ?string, percent: float, words: int, color: string}>,
     *     sections: list<array{label: string, paragraphs: list<list<array{text: string, source: ?int, rank: ?int, sources: list<int>, color: ?string}>>}>
     * }
     */
    public function build(SimilarityCheck $check): array
    {
        $matches = $check->matches()->get();
        $document = $check->document ?? [];

        $sources = [];
        $spansByParagraph = [];

        foreach ($matches as $position => $match) {
            $color = self::PALETTE[$position % count(self::PALETTE)];
            $url = $this->safeUrl($match->url);

            $sources[] = [
                'id' => $match->id,
                'rank' => $match->rank,
                'title' => $match->title ?: 'Untitled source',
                'url' => $url,
                'host' => $url ? parse_url($url, PHP_URL_HOST) : null,
                'percent' => $match->percent,
                'words' => $match->matched_words,
                'color' => $color,
            ];

            foreach ($match->spans as $span) {
                $spansByParagraph[$span['para']][] = $span + ['source' => $match->id, 'rank' => $match->rank, 'color' => $color];
            }
        }

        $colors = array_column($sources, 'color', 'id');
        $sections = [];

        foreach ($document as $paraIndex => $paragraph) {
            $last = count($sections) - 1;

            if ($last < 0 || $sections[$last]['key'] !== $paragraph['section']) {
                $sections[] = ['key' => $paragraph['section'], 'label' => $paragraph['label'], 'paragraphs' => []];
                $last++;
            }

            $segments = $this->segments($paragraph['text'], $spansByParagraph[$paraIndex] ?? []);

            $sections[$last]['paragraphs'][] = array_map(
                fn (array $segment) => $segment + ['color' => $segment['source'] !== null ? $colors[$segment['source']] : null],
                $segments,
            );
        }

        return [
            'sources' => $sources,
            'sections' => array_map(fn (array $section) => ['label' => $section['label'], 'paragraphs' => $section['paragraphs']], $sections),
        ];
    }

    /**
     * Cuts one paragraph into consecutive segments. `$spans` arrive best source first; each is
     * painted only where a better-ranked span hasn't already claimed the words.
     *
     * A painted segment also lists *every* source whose match overlaps it (`sources`, the
     * painting winner first) — a source whose whole passage was claimed by a better match
     * paints nothing of its own, but selecting it must still be able to light up the text it
     * matched.
     *
     * @param  list<array{start: int, end: int, source: int, rank: int}>  $spans
     * @return list<array{text: string, source: ?int, rank: ?int, sources: list<int>}>
     */
    private function segments(string $text, array $spans): array
    {
        $painted = [];
        $pieces = [];

        foreach ($spans as $span) {
            $free = [[$span['start'], $span['end']]];

            foreach ($painted as [$paintedStart, $paintedEnd]) {
                $remaining = [];

                foreach ($free as [$start, $end]) {
                    if ($paintedEnd <= $start || $paintedStart >= $end) {
                        $remaining[] = [$start, $end];

                        continue;
                    }

                    if ($start < $paintedStart) {
                        $remaining[] = [$start, $paintedStart];
                    }

                    if ($paintedEnd < $end) {
                        $remaining[] = [$paintedEnd, $end];
                    }
                }

                $free = $remaining;
            }

            foreach ($free as [$start, $end]) {
                // What's left of a span after a better match took its share can begin or end in
                // the whitespace that sat between two words — don't highlight the gap itself.
                while ($start < $end && ctype_space($text[$start])) {
                    $start++;
                }

                while ($end > $start && ctype_space($text[$end - 1])) {
                    $end--;
                }

                if ($start >= $end) {
                    continue;
                }

                $pieces[] = ['start' => $start, 'end' => $end, 'source' => $span['source'], 'rank' => $span['rank']];
                $painted[] = [$start, $end];
            }
        }

        usort($pieces, fn (array $a, array $b) => $a['start'] <=> $b['start']);

        $segments = [];
        $cursor = 0;

        foreach ($pieces as $piece) {
            if ($piece['start'] > $cursor) {
                $segments[] = ['text' => substr($text, $cursor, $piece['start'] - $cursor), 'source' => null, 'rank' => null, 'sources' => []];
            }

            $covering = array_values(array_unique(array_column(
                array_filter($spans, fn (array $span) => $span['start'] < $piece['end'] && $span['end'] > $piece['start']),
                'source',
            )));

            $segments[] = [
                'text' => substr($text, $piece['start'], $piece['end'] - $piece['start']),
                'source' => $piece['source'],
                'rank' => $piece['rank'],
                'sources' => $covering,
            ];
            $cursor = $piece['end'];
        }

        if ($cursor < strlen($text)) {
            $segments[] = ['text' => substr($text, $cursor), 'source' => null, 'rank' => null, 'sources' => []];
        }

        return $segments === [] ? [['text' => $text, 'source' => null, 'rank' => null, 'sources' => []]] : $segments;
    }

    /**
     * URLs in a match come from third-party APIs — never render one that isn't plain
     * http(s) (a `javascript:` URL in an href is script execution).
     */
    private function safeUrl(?string $url): ?string
    {
        if ($url === null || ! preg_match('#^https?://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $url;
    }
}
