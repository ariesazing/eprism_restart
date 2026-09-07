<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Sidebar-free counterpart to AppLayout — for a view that's itself a focused task
 * (manuscript review, chapter canvas editing) rather than a page within the app's usual
 * section-to-section browsing. See resources/views/layouts/focus.blade.php.
 */
class FocusLayout extends Component
{
    public function render(): View
    {
        return view('layouts.focus');
    }
}
