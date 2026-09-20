<?php

namespace App\Similarity;

/**
 * Splits text into comparable words. Everything similarity matching does hangs off this
 * one definition of "a word" (letters/digits, with an inner apostrophe kept, lowercased) so
 * that the document, another submission and a scraped web page are all normalized the same
 * way — punctuation, casing and curly-vs-straight quotes never make identical text look
 * different.
 */
final class Tokenizer
{
    /**
     * Words with their position in the original string, for mapping a match back onto text
     * that's displayed. Offsets are BYTE offsets (what preg_match_all reports) — they're only
     * ever used with substr() on the same string, and always land on a word boundary, so
     * slicing never splits a multi-byte character.
     *
     * @return list<array{word: string, start: int, end: int}>
     */
    public static function tokens(string $text): array
    {
        if (! preg_match_all("/[\p{L}\p{N}]+(?:['’][\p{L}]+)?/u", $text, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $tokens = [];

        foreach ($matches[0] as [$word, $offset]) {
            $tokens[] = [
                'word' => self::normalize($word),
                'start' => $offset,
                'end' => $offset + strlen($word),
            ];
        }

        return $tokens;
    }

    /**
     * @return list<string>
     */
    public static function words(string $text): array
    {
        return array_column(self::tokens($text), 'word');
    }

    private static function normalize(string $word): string
    {
        return mb_strtolower(str_replace('’', "'", $word));
    }
}
