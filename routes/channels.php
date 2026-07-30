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
