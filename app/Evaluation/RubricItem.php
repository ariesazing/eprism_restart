<?php

namespace App\Evaluation;

/**
 * One line item on a scoring template. A leaf item (no children) is what a reviewer actually
 * scores, an integer from 0 up to $max. A parent item (children not empty) is never scored
 * directly — its score is always the sum of its children's scores (see
 * ResearchEvaluationRubric::breakdown()) — so $max is redundant with the sum of its children's
 * own $max, but kept here for display and as a self-check (see RubricTemplate's own doc
 * comment on where that's verified).
 *
 * $code is the label exactly as printed on the source form ("A", "D.1", or a bare "1"/"2"/"3"
 * for a sub-item numbered rather than lettered) — display only. $key is this item's own storage
 * key: unique within one rubric, safe as an HTML `name`/array key, and never derived from $code,
 * since two of the four source templates reuse a letter across two different rows (a printed
 * typo — see ResearchEvaluationRubric::basicCompleted()'s own comment on it).
 */
class RubricItem
{
    /**
     * @param  array<int, RubricItem>  $children
     */
    public function __construct(
        public readonly string $key,
        public readonly string $code,
        public readonly string $label,
        public readonly int $max,
        public readonly array $children = [],
    ) {}

    public function isLeaf(): bool
    {
        return $this->children === [];
    }

    /**
     * @return list<RubricItem> every scoreable leaf beneath this item, or itself if it's
     *                          already a leaf
     */
    public function leaves(): array
    {
        if ($this->isLeaf()) {
            return [$this];
        }

        return array_merge(...array_map(fn (RubricItem $child) => $child->leaves(), $this->children));
    }

    /**
     * @return array{key: string, code: string, label: string, max: int, children: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'code' => $this->code,
            'label' => $this->label,
            'max' => $this->max,
            'children' => array_map(fn (RubricItem $child) => $child->toArray(), $this->children),
        ];
    }
}
