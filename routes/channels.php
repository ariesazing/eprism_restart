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
        return $submission->assigned_reviewer_id === $user->id;
    }

    if ($user->isResearcher()) {
        if ($submission->researcher_id !== $user->id) {
            return false;
        }

        $review = $submission->reviews()->where('reviewer_id', $submission->assigned_reviewer_id)->latest()->first();

        return $review !== null && $review->isApproved();
    }

    return false;
});
