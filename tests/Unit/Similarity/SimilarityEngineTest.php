<?php

namespace Tests\Unit\Similarity;

use App\Similarity\DocumentIndex;
use App\Similarity\HtmlText;
use App\Similarity\QueryPlanner;
use App\Similarity\SimilarityMatcher;
use App\Similarity\Tokenizer;
use PHPUnit\Framework\TestCase;

class SimilarityEngineTest extends TestCase
{
    private const PASSAGE = 'the quick brown fox jumps over the lazy dog while the researchers observed the behaviour of the animals';

    public function test_tokenizer_normalizes_words_and_reports_byte_offsets_that_slice_back_to_the_original(): void
    {
        $text = 'Don’t stop — café ÉCOLE 2024!';

        $tokens = Tokenizer::tokens($text);

        $this->assertSame(["don't", 'stop', 'café', 'école', '2024'], array_column($tokens, 'word'));
        $this->assertSame(['Don’t', 'stop', 'café', 'ÉCOLE', '2024'], array_map(
            fn (array $token) => substr($text, $token['start'], $token['end'] - $token['start']),
            $tokens,
        ));
    }

    public function test_a_copied_passage_is_found_and_maps_back_to_the_exact_text(): void
    {
        $paragraph = 'Our study began in June. '.self::PASSAGE.' We then analysed the data carefully.';
        $index = DocumentIndex::build([['text' => $paragraph]], 6);

        $spans = (new SimilarityMatcher(8, 2))->spans(
            $index,
            'Totally different opening words. '.self::PASSAGE.' A different ending for the other page.',
        );

        $this->assertCount(1, $spans);

        $first = $index->tokens[$spans[0]['from']];
        $last = $index->tokens[$spans[0]['to']];

        $this->assertSame(self::PASSAGE, substr($paragraph, $first['start'], $last['end'] - $first['start']));
    }

    public function test_matching_ignores_case_and_punctuation(): void
    {
        $index = DocumentIndex::build([['text' => self::PASSAGE]], 6);

        $spans = (new SimilarityMatcher(8, 2))->spans($index, strtoupper(str_replace(' ', ', ', self::PASSAGE)));

        $this->assertCount(1, $spans);
    }

    public function test_a_short_coincidental_overlap_is_not_a_match(): void
    {
        $index = DocumentIndex::build([['text' => 'We surveyed schools in the field for several months last year.']], 6);

        // Six shared words — exactly one window, below the eight-word minimum.
        $spans = (new SimilarityMatcher(8, 2))->spans($index, 'Other people work in the field for several months too.');

        $this->assertSame([], $spans);
    }

    public function test_one_edited_word_does_not_split_a_copied_passage(): void
    {
        $index = DocumentIndex::build([['text' => self::PASSAGE]], 6);
        $edited = str_replace('lazy dog', 'lazy cat', self::PASSAGE);

        $merged = (new SimilarityMatcher(8, 2))->spans($index, $edited);
        $split = (new SimilarityMatcher(8, 0))->spans($index, $edited);

        $this->assertCount(1, $merged);
        $this->assertSame(['from' => 0, 'to' => 17], $merged[0]);
        $this->assertCount(2, $split);
    }

    public function test_a_match_never_spans_two_paragraphs(): void
    {
        $index = DocumentIndex::build([
            ['text' => 'alpha beta gamma delta epsilon zeta'],
            ['text' => 'eta theta iota kappa lambda mu'],
        ], 6);
        $candidate = 'alpha beta gamma delta epsilon zeta eta theta iota kappa lambda mu';

        // Each paragraph's own six words match, but they are unrelated text — never merged.
        $this->assertSame([], (new SimilarityMatcher(8, 2))->spans($index, $candidate));
        $this->assertSame(
            [['from' => 0, 'to' => 5], ['from' => 6, 'to' => 11]],
            (new SimilarityMatcher(6, 2))->spans($index, $candidate),
        );
    }

    public function test_query_planner_spreads_fixed_length_phrases_across_the_document(): void
    {
        $paragraphs = [];
        foreach (range(1, 10) as $number) {
            $paragraphs[] = ['text' => "Sentence one discusses elaborate methodological considerations regarding participant{$number} instruments, procedures, analysis, and reporting standards today."];
        }

        $phrases = (new QueryPlanner(4, 10, 14))->phrases($paragraphs);

        $this->assertCount(4, $phrases);
        $this->assertSame($phrases, array_values(array_unique($phrases)));
        foreach ($phrases as $phrase) {
            $this->assertCount(10, explode(' ', $phrase));
        }

        // One phrase from each stretch of the document — not four from the opening.
        $numbers = array_map(fn (string $phrase) => (int) preg_replace('/.*participant(\d+).*/', '$1', $phrase), $phrases);
        $this->assertCount(4, array_unique($numbers));
        $this->assertLessThanOrEqual(2, min($numbers));
        $this->assertGreaterThanOrEqual(8, max($numbers));
    }

    public function test_query_planner_skips_sentences_that_are_too_short_to_search_on(): void
    {
        $phrases = (new QueryPlanner(5, 10, 14))->phrases([['text' => 'Too short to search. Also short.']]);

        $this->assertSame([], $phrases);
    }

    public function test_html_text_makes_block_tags_paragraph_breaks_and_drops_scripts(): void
    {
        $html = '<nav>Menu</nav><p>Hello&nbsp;<strong>world</strong></p><ul><li>One</li><li>Two</li></ul><script>alert(1)</script>';

        $this->assertSame(['Hello world', 'One', 'Two'], HtmlText::paragraphs($html));
    }
}
