<?php

use App\Models\ResearchSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('submission.{submission}', function (User $user, ResearchSubmission $submission) {
    if ($user->isAdmin()) {
        return true;
    }

    if ($user->isReviewer()) {
        return $submission->reviewers()->whereKey($user->id)->exists();
    }

    if ($user->isResearcher()) {
        if ($submission->researcher_id !== $user->id) {
            return false;
        }

        return $submission->reviews()->whereNotNull('submitted_at')->exists();
    }

    return false;
});

// Reviewer discussion is deliberately reviewer/admin-only — unlike the channel above,
// there is no researcher branch at all, so a researcher's browser can never even
// subscribe to it, regardless of what the HTTP endpoints additionally enforce.
Broadcast::channel('submission.{submission}.discussion', function (User $user, ResearchSubmission $submission) {
    if ($user->isAdmin()) {
        return true;
    }

    if ($user->isReviewer()) {
        return $submission->reviewers()->whereKey($user->id)->exists();
    }

    return false;
});
