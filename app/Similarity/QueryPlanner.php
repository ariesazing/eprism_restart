<?php

namespace App\Similarity;

/**
 * Picks the exact phrases to look up on external search (CORE, the web). Searching every
 * sentence is neither affordable (per-request pricing, rate limits) nor necessary: copied
 * text tends to run for whole paragraphs, so a handful of well-spread, distinctive phrases
 * reliably lands on the source, and the full-text comparison afterwards finds the rest of
 * the overlap on its own.
 */
final class QueryPlanner
{
    public function __construct(
        private readonly int $max,
        private readonly int $words,
        private readonly int $minSentenceWords,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (int) config('similarity.queries.max'),
            (int) config('similarity.queries.words'),
            (int) config('similarity.queries.min_sentence_words'),
        );
    }

    /**
     * @param  list<array{text: string}>  $paragraphs
     * @return list<string> phrases of normalized words, spread across the whole document
     */
    public function phrases(array $paragraphs): array
    {
        $candidates = [];

        foreach ($paragraphs as $paragraph) {
            foreach (preg_split('/(?<=[.!?])\s+/u', $paragraph['text']) ?: [] as $sentence) {
                $words = Tokenizer::words($sentence);
                $count = count($words);

                if ($count < $this->minSentenceWords || $count < $this->words) {
                    continue;
                }

                $candidates[] = ['words' => $words, 'score' => $this->distinctiveness($words)];
            }
        }

        if ($candidates === [] || $this->max < 1) {
            return [];
        }

        $chosen = count($candidates) <= $this->max
            ? $candidates
            : $this->bestPerBucket($candidates);

        $phrases = [];

        foreach ($chosen as $candidate) {
            $start = intdiv(count($candidate['words']) - $this->words, 2);
            $phrases[] = implode(' ', array_slice($candidate['words'], $start, $this->words));
        }

        return array_values(array_unique($phrases));
    }

    /**
     * Splits the document into `max` equal stretches and takes the most distinctive sentence
     * of each, so queries cover the whole manuscript rather than clustering on whichever
     * chapter happens to be wordiest.
     *
     * @param  list<array{words: list<string>, score: float}>  $candidates
     * @return list<array{words: list<string>, score: float}>
     */
    private function bestPerBucket(array $candidates): array
    {
        $chosen = [];
        $total = count($candidates);

        for ($bucket = 0; $bucket < $this->max; $bucket++) {
            $slice = array_slice(
                $candidates,
                (int) floor($bucket * $total / $this->max),
                max(1, (int) floor(($bucket + 1) * $total / $this->max) - (int) floor($bucket * $total / $this->max)),
            );

            usort($slice, fn (array $a, array $b) => $b['score'] <=> $a['score']);
            $chosen[] = $slice[0];
        }

        return $chosen;
    }

    /**
     * Long, varied words make a phrase unlikely to occur by chance; a sentence of short
     * common words would match half the internet.
     *
     * @param  list<string>  $words
     */
    private function distinctiveness(array $words): float
    {
        $long = count(array_unique(array_filter($words, fn (string $word) => mb_strlen($word) >= 7)));

        return $long / count($words) + min(count($words), 30) / 100;
    }
}
