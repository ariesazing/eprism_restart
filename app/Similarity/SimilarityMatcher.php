<?php

namespace App\Similarity;

/**
 * Finds which passages of the indexed document also appear in a candidate text.
 *
 * Every `shingleSize`-word window of the candidate is looked up in the document's index; a
 * hit marks the document's words in that window as matched. Consecutive matched words form
 * a run, nearby runs in the same paragraph are merged (one swapped word shouldn't split a
 * copied sentence in two), and runs too short to be more than a stock phrase are dropped.
 *
 * This is an exact-wording detector: reworded (paraphrased) text is not caught — see the
 * class doc on SimilarityCheckRunner for what that means for how results are presented.
 */
final class SimilarityMatcher
{
    public function __construct(
        private readonly int $minSpanWords,
        private readonly int $mergeGap,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (int) config('similarity.min_span_words'),
            (int) config('similarity.merge_gap'),
        );
    }

    /**
     * @return list<array{from: int, to: int}> inclusive ranges of document token indexes
     */
    public function spans(DocumentIndex $index, string $candidateText): array
    {
        $words = Tokenizer::words($candidateText);
        $size = $index->shingleSize;
        $count = count($words);

        if ($count < $size || $index->shingles === []) {
            return [];
        }

        $matched = [];

        for ($i = 0; $i + $size <= $count; $i++) {
            $hash = DocumentIndex::hash(array_slice($words, $i, $size));

            foreach ($index->shingles[$hash] ?? [] as $start) {
                for ($j = 0; $j < $size; $j++) {
                    $matched[$start + $j] = true;
                }
            }
        }

        return $this->runs($index, array_keys($matched));
    }

    /**
     * @param  list<int>  $matchedIndexes
     * @return list<array{from: int, to: int}>
     */
    private function runs(DocumentIndex $index, array $matchedIndexes): array
    {
        if ($matchedIndexes === []) {
            return [];
        }

        sort($matchedIndexes);

        $tokens = $index->tokens;
        $runs = [];
        $from = $to = $matchedIndexes[0];

        foreach (array_slice($matchedIndexes, 1) as $position) {
            $sameParagraph = $tokens[$position]['para'] === $tokens[$to]['para'];

            if ($sameParagraph && $position - $to - 1 <= $this->mergeGap) {
                $to = $position;

                continue;
            }

            $runs[] = ['from' => $from, 'to' => $to];
            $from = $to = $position;
        }

        $runs[] = ['from' => $from, 'to' => $to];

        return array_values(array_filter($runs, fn (array $run) => $this->minSpanWords <= $run['to'] - $run['from'] + 1));
    }
}
