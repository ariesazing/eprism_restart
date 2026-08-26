<?php

namespace App\Http\Controllers;

use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitPosition;
use App\Models\SubmissionWindow;
use Illuminate\Contracts\View\View;

/**
 * The unauthenticated "start a research submission" page — deliberately has no store()
 * counterpart. It only ever stages fields client-side (see resources/js/guest-draft.js);
 * the only route that can actually create a ResearchSubmission is the authenticated
 * submissions.store, which a guest cannot reach. This keeps "start drafting before you
 * have an account" from ever becoming a way to persist data without one.
 */
class GuestSubmissionController extends Controller
{
    public function create(): View
    {
        return view('guest.start-submission', [
            'organizationalUnits' => OrganizationalUnit::activeOrdered(),
            'schoolPositions' => OrganizationalUnitPosition::schoolPositions(),
            'nonSchoolPositions' => OrganizationalUnitPosition::nonSchoolPositions(),
            'proposalWindowOpen' => SubmissionWindow::isOpenFor('proposal'),
        ]);
    }
}
