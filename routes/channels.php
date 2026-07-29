<?php

use App\Models\ResearchDocument;
use App\Models\ResearchSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('submission.{submission}.document.{document}', function (User $user, ResearchSubmission $submission, ResearchDocument $document) {
    if ($document->research_submission_id !== $submission->id) {
        return false;
    }

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
