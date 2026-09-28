<?php

namespace App\Similarity;

use App\Similarity\Sources\RepositorySource;

/** The system repository is the only source used for new similarity checks. */
final class SourceRegistry
{
    /** @var list<SimilaritySource> */
    private array $sources;

    public function __construct(RepositorySource $repository)
    {
        $this->sources = [$repository];
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
