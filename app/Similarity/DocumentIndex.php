<?php

namespace App\Similarity;

/**
 * The checked document, pre-processed once so any number of candidate sources can be
 * compared against it cheaply: every word in one flat, ordered token list, plus a lookup from
 * each `shingleSize`-word window's hash to where in the document that window starts.
 *
 * Windows never straddle two paragraphs — the last words of one paragraph and the first of
 * the next are unrelated text, and a match spanning that seam would be an artifact.
 */
final class DocumentIndex
{
    /**
     * @param  list<array{word: string, start: int, end: int, para: int}>  $tokens
     * @param  array<string, list<int>>  $shingles  window hash => token indexes where it starts
     */
    private function __construct(
        public readonly array $tokens,
        public readonly array $shingles,
        public readonly int $shingleSize,
    ) {}

    /**
     * @param  list<array{text: string}>  $paragraphs
     */
    public static function build(array $paragraphs, int $shingleSize): self
    {
        $tokens = [];
        $shingles = [];

        foreach ($paragraphs as $paraIndex => $paragraph) {
            $paraTokens = Tokenizer::tokens($paragraph['text']);
            $first = count($tokens);

            foreach ($paraTokens as $token) {
                $tokens[] = $token + ['para' => $paraIndex];
            }

            $count = count($paraTokens);

            for ($i = 0; $i + $shingleSize <= $count; $i++) {
                $window = array_column(array_slice($paraTokens, $i, $shingleSize), 'word');
                $shingles[self::hash($window)][] = $first + $i;
            }
        }

        return new self($tokens, $shingles, $shingleSize);
    }

    public function tokenCount(): int
    {
        return count($this->tokens);
    }

    /**
     * @param  list<string>  $words
     */
    public static function hash(array $words): string
    {
        return hash('xxh3', implode(' ', $words));
    }
}
