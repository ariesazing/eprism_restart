<?php

namespace App\Similarity;

use App\Similarity\Sources\WebSource;

/**
 * The similarity sources a check runs. Today that's the web alone (via self-hosted SearXNG);
 * new sources plug in here behind SimilaritySource, and are listed in the order they should run
 * — cheapest and most relevant first, so if a check hits its time budget it's the slow lookups
 * that end up partial.
 */
final class SourceRegistry
{
    /** @var list<SimilaritySource> */
    private array $sources;

    public function __construct(WebSource $web)
    {
        $this->sources = [$web];
    }

    /**
     * @return list<SimilaritySource>
     */
    public function all(): array
    {
        return $this->sources;
    }

    /**
     * For the UI: what a check started right now would actually search.
     *
     * @return list<array{type: string, label: string, state: 'ready'|'unconfigured'|'disabled'}>
     */
    public function status(): array
    {
        return array_map(fn (SimilaritySource $source) => [
            'type' => $source->type(),
            'label' => $source->label(),
            'state' => match (true) {
                ! $source->isEnabled() => 'disabled',
                ! $source->isConfigured() => 'unconfigured',
                default => 'ready',
            },
        ], $this->sources);
    }
}
