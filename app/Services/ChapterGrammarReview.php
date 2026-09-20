<?php

namespace App\Services;

use App\Exceptions\GrammarCheckUnavailableException;
use App\Models\SubmissionSection;
use App\Similarity\TextExtractor;
use App\Similarity\Tokenizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A one-shot, read-only grammar review of a chapter, for the editors that can't underline as you
 * type. ONLYOFFICE has no API for drawing squiggles inside its document (its own LanguageTool
 * plugin is manual check-and-replace, and it's switched off in this app's stripped-down chapter
 * editor anyway), so instead of a live checker the researcher opens this review: their chapter's
 * *saved* text, with every issue LanguageTool finds highlighted and its suggestions on hover.
 *
 * Returns the paragraphs grouped into chunks, each with the LanguageTool matches for that chunk's
 * text — and deliberately NOT offsets mapped onto individual paragraphs. LanguageTool reports
 * offsets in UTF-16 code units (Java chars); the browser's own string indexes are UTF-16 too, so
 * the front end can slice a chunk's text with them directly. Converting them here, in PHP's
 * byte/codepoint strings, is exactly where emoji or other astral characters would shift every
 * highlight after them by a character.
 */
class ChapterGrammarReview
{
    // LanguageTool answers a request in time proportional to its text; a chapter is checked in
    // pieces of about this size so each request stays quick and no single one hits the client
    // timeout (GrammarCheckService::check()). A single paragraph longer than this goes alone.
    private const CHUNK_CHARS = 6000;

    // A ceiling on the whole review, so a pathological (or pasted-in-a-whole-thesis) chapter can't
    // hold a request open for minutes. Well past any real chapter (~10,000 words).
    private const MAX_CHARS = 60000;

    private const MAX_SUGGESTIONS = 5;

    public function __construct(
        private readonly GrammarCheckService $grammar,
        private readonly TextExtractor $extractor,
    ) {}

    /**
     * @return array{
     *     available: bool,
     *     saved_at: ?string,
     *     word_count: int,
     *     issue_count: int,
     *     truncated: bool,
     *     incomplete: bool,
     *     chunks: list<array{paragraphs: list<string>, matches: list<array<string, mixed>>}>
     * }
     */
    public function review(SubmissionSection $section): array
    {
        $paragraphs = $this->extractor->sectionParagraphs($section);
        [$paragraphs, $truncated] = $this->limit($paragraphs);

        $chunks = array_map(fn (array $group) => ['paragraphs' => $group, 'matches' => []], $this->group($paragraphs));

        $available = true;
        $incomplete = false;

        foreach ($chunks as $index => $chunk) {
            try {
                $chunks[$index]['matches'] = array_map($this->slim(...), $this->grammar->check(implode("\n\n", $chunk['paragraphs'])));
            } catch (GrammarCheckUnavailableException) {
                // Nothing checked at all = "unavailable"; some chunks checked before it failed =
                // a partial review, said so — never presented as a clean bill of health.
                if ($index === 0) {
                    $available = false;
                } else {
                    $incomplete = true;
                }

                break;
            }
        }

        return [
            'available' => $available,
            'saved_at' => $this->savedAt($section)?->toIso8601String(),
            'word_count' => count(Tokenizer::words(implode(' ', $paragraphs))),
            'issue_count' => array_sum(array_map(fn (array $chunk) => count($chunk['matches']), $chunks)),
            'truncated' => $truncated,
            'incomplete' => $incomplete,
            'chunks' => $chunks,
        ];
    }

    /**
     * When the researcher's editor last saved this chapter — what the review's text reflects.
     * Anything typed since (ONLYOFFICE only saves on its own schedule, plus the force-save the
     * page asks for) isn't in it, so the review says exactly how fresh it is.
     */
    private function savedAt(SubmissionSection $section): ?Carbon
    {
        $disk = Storage::disk('local');

        if ($section->onlyoffice_path === null || ! $disk->exists($section->onlyoffice_path)) {
            return null;
        }

        return Carbon::createFromTimestamp($disk->lastModified($section->onlyoffice_path));
    }

    /**
     * @param  list<string>  $paragraphs
     * @return array{0: list<string>, 1: bool}
     */
    private function limit(array $paragraphs): array
    {
        $kept = [];
        $total = 0;

        foreach ($paragraphs as $paragraph) {
            $length = mb_strlen($paragraph);

            if ($total + $length > self::MAX_CHARS && $kept !== []) {
                return [$kept, true];
            }

            $kept[] = $paragraph;
            $total += $length;
        }

        return [$kept, false];
    }

    /**
     * @param  list<string>  $paragraphs
     * @return list<list<string>>
     */
    private function group(array $paragraphs): array
    {
        $groups = [];
        $current = [];
        $size = 0;

        foreach ($paragraphs as $paragraph) {
            $length = mb_strlen($paragraph) + 2;

            if ($current !== [] && $size + $length > self::CHUNK_CHARS) {
                $groups[] = $current;
                $current = [];
                $size = 0;
            }

            $current[] = $paragraph;
            $size += $length;
        }

        if ($current !== []) {
            $groups[] = $current;
        }

        return $groups;
    }

    /**
     * Only what the review draws — LanguageTool's full match objects carry a context sentence, URLs
     * and rule internals that would multiply the payload for no visible benefit.
     *
     * @param  array<string, mixed>  $match
     * @return array<string, mixed>
     */
    private function slim(array $match): array
    {
        return [
            'offset' => (int) ($match['offset'] ?? 0),
            'length' => (int) ($match['length'] ?? 0),
            'message' => (string) ($match['message'] ?? ''),
            'short' => (string) ($match['shortMessage'] ?? ''),
            'type' => (string) ($match['rule']['issueType'] ?? ''),
            'category' => (string) ($match['rule']['category']['name'] ?? ''),
            'suggestions' => array_slice(array_values(array_filter(array_map(
                fn ($replacement) => is_array($replacement) ? ($replacement['value'] ?? null) : null,
                (array) ($match['replacements'] ?? []),
            ), fn ($value) => is_string($value) && $value !== '')), 0, self::MAX_SUGGESTIONS),
        ];
    }
}
