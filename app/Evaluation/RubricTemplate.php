<?php

namespace App\Evaluation;

/**
 * One of the four official DepEd scoring templates — see ResearchEvaluationRubric's own class
 * doc for what they are and where they came from. $key matches
 * App\SubmissionTemplates\SubmissionTemplateRegistry's own "{research_type}_{classification}"
 * naming exactly, since the two registries are two independent lookups over the same four-way
 * split (one for what a researcher fills in, this one for how a reviewer scores it).
 */
class RubricTemplate
{
    /**
     * @param  array<int, RubricSection>  $sections
     */
    public function __construct(
        public readonly string $key,
        public readonly string $researchType,
        public readonly string $classification,
        public readonly string $label,
        public readonly array $sections,
    ) {}

    public function max(): int
    {
        return array_sum(array_map(fn (RubricSection $section) => $section->max(), $this->sections));
    }

    /**
     * @return list<RubricItem> every scoreable leaf across every section, in template order
     */
    public function leaves(): array
    {
        return array_merge(...array_map(fn (RubricSection $section) => $section->leaves(), $this->sections));
    }

    /**
     * @return list<string>
     */
    public function leafKeys(): array
    {
        return array_map(fn (RubricItem $item) => $item->key, $this->leaves());
    }

    public function leaf(string $key): ?RubricItem
    {
        foreach ($this->leaves() as $item) {
            if ($item->key === $key) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array{key: string, label: string, max: int, sections: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'max' => $this->max(),
            'sections' => array_map(fn (RubricSection $section) => $section->toArray(), $this->sections),
        ];
    }
}
