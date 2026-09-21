<?php

namespace App\Evaluation;

/**
 * A named grouping of items with its own point subtotal. Only the two "completed research"
 * templates actually have more than one of these (Manuscript / Utilization and Dissemination,
 * 50 points each); a proposal template is a single section with $label === null — a renderer
 * uses that null to decide whether to print a section heading at all.
 */
class RubricSection
{
    /**
     * @param  array<int, RubricItem>  $items
     */
    public function __construct(
        public readonly ?string $label,
        public readonly array $items,
    ) {}

    public function max(): int
    {
        return array_sum(array_map(fn (RubricItem $item) => $item->max, $this->items));
    }

    /**
     * @return list<RubricItem>
     */
    public function leaves(): array
    {
        return array_merge(...array_map(fn (RubricItem $item) => $item->leaves(), $this->items));
    }

    /**
     * @return array{label: ?string, max: int, items: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'max' => $this->max(),
            'items' => array_map(fn (RubricItem $item) => $item->toArray(), $this->items),
        ];
    }
}
