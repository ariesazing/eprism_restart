<?php

namespace App\Similarity;

interface SimilaritySource
{
    /** Stable identifier — also the SimilarityMatch::$source_type and the config key under similarity.sources. */
    public function type(): string;

    public function label(): string;

    public function isEnabled(): bool;

    public function isConfigured(): bool;

    /** Shown on the report when the source is enabled but can't run. */
    public function notConfiguredMessage(): string;

    /**
     * Candidate texts to compare the document against — lazily, so a source with a large
     * corpus (every other submission) never holds more than one candidate in memory at once.
     *
     * @return iterable<Candidate>
     *
     * @throws SourceUnavailableException
     */
    public function candidates(CheckContext $context): iterable;
}
