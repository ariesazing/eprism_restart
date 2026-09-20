<?php

namespace App\Similarity;

/**
 * One piece of source text a similarity source hands the runner to compare the document
 * against — an ePrism submission, a CORE paper's full text, a fetched web page.
 */
final class Candidate
{
    /**
     * @param  array<string, mixed>  $meta  source-specific extras shown in the report (authors, year, DOI, …)
     */
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly ?string $url,
        public readonly string $text,
        public readonly array $meta = [],
    ) {}
}
