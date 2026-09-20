<?php

namespace App\Similarity;

use App\Models\ResearchSubmission;

/**
 * Everything a similarity source needs to know about the check it's contributing to, plus
 * the channel back for anything that shouldn't fail the whole check (a rate limit, a bad key)
 * but the researcher should still hear about: warnings end up on the report, so a partial
 * result is never mistaken for a clean one.
 */
final class CheckContext
{
    /** @var list<string> */
    public array $warnings = [];

    /**
     * @param  list<array{section: string, label: string, text: string}>  $paragraphs
     * @param  list<string>  $queries  exact phrases worth looking up on external search
     */
    public function __construct(
        public readonly ResearchSubmission $submission,
        public readonly array $paragraphs,
        public readonly DocumentIndex $index,
        public readonly array $queries,
        private readonly float $deadline,
    ) {}

    public function warn(string $message): void
    {
        if (! in_array($message, $this->warnings, true)) {
            $this->warnings[] = $message;
        }
    }

    public function expired(): bool
    {
        return microtime(true) >= $this->deadline;
    }
}
