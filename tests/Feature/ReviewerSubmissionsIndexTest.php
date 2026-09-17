<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewerSubmissionsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviewer_queue_paginates_at_fifteen(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $researcher = User::factory()->create();

        $submissions = collect(range(1, 17))->map(fn (int $i) => $researcher->submissions()->create([
            'title' => "Submission {$i}",
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::SUBMITTED,
        ]));
        $submissions->each(fn ($submission) => $submission->reviewers()->attach($reviewer->id));

        $page1 = $this->actingAs($reviewer)->get(route('reviewer.submissions.index'));
        $page1->assertOk();
        $page1->assertViewHas('submissions', fn ($paginated) => $paginated->count() === 15 && $paginated->total() === 17);

        $page2 = $this->actingAs($reviewer)->get(route('reviewer.submissions.index', ['page' => 2]));
        $page2->assertOk();
        $page2->assertViewHas('submissions', fn ($paginated) => $paginated->count() === 2);
    }
}
