<?php

namespace App\View\Components;

use App\Models\SimilarityCheck;
use App\Similarity\PendingNotifications;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * The popup that tells whoever started a similarity check (a researcher, reviewer or admin) it has
 * finished, wherever in the app they are when it does — a check takes minutes, so they've usually
 * navigated away from the page that started it. Dropped into both layouts; renders nothing for a
 * guest, and
 * nothing at all (no markup, no script, no polling) while they have no check running and none
 * they haven't been told about. See PendingNotifications for what it reports and the component's
 * view for how it behaves.
 */
class SimilarityNotifier extends Component
{
    /** @var array{active: int, finished: list<array<string, mixed>>} */
    public array $state = ['active' => 0, 'finished' => []];

    public function __construct(PendingNotifications $pending)
    {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        // On a check's own page (its progress screen, then its report) that page already shows
        // everything — announcing the same check on top of it would just be noise.
        $current = request()->route('check');

        $this->state = $pending->for($user, $current instanceof SimilarityCheck ? $current->id : null);
    }

    public function shouldRender(): bool
    {
        return $this->state['active'] > 0 || $this->state['finished'] !== [];
    }

    public function render(): View
    {
        return view('components.similarity-notifier', [
            'statusUrl' => route('similarity.notifications'),
            'dismissUrl' => route('similarity.dismiss', ['check' => '__ID__']),
        ]);
    }
}
