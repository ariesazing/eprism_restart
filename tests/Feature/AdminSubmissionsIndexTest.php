<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSubmissionsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_paginates_at_fifteen_and_second_page_is_reachable(): void
    {
        $admin = User::factory()->admin()->create();
        $researcher = User::factory()->create();

        collect(range(1, 17))->each(fn (int $i) => $researcher->submissions()->create([
            'title' => "Submission {$i}",
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::SUBMITTED,
            'submitted_at' => now()->subDays($i),
        ]));

        $page1 = $this->actingAs($admin)->get(route('admin.submissions.index'));
        $page1->assertOk();
        $page1->assertViewHas('submissions', fn ($submissions) => $submissions->count() === 15 && $submissions->total() === 17);

        $page2 = $this->actingAs($admin)->get(route('admin.submissions.index', ['page' => 2]));
        $page2->assertOk();
        $page2->assertViewHas('submissions', fn ($submissions) => $submissions->count() === 2);
    }

    public function test_details_and_assign_reviewer_are_separate_modals(): void
    {
        $admin = User::factory()->admin()->create();
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();

        $submission = $researcher->submissions()->create([
            'title' => 'Split Modal Test',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::SUBMITTED,
        ]);
        $submission->reviewers()->attach($reviewer->id);

        $response = $this->actingAs($admin)->get(route('admin.submissions.index'));
        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('submission-'.$submission->id.'-details', $html);
        $this->assertStringContainsString('submission-'.$submission->id.'-assign', $html);

        // The Assign Reviewers form must live in exactly one place (the new "-assign"
        // modal) — not duplicated into both modals the way the old single-modal layout
        // would have required.
        $this->assertSame(1, substr_count($html, route('admin.submissions.assign-reviewer', $submission)));
        $this->assertSame(1, substr_count($html, 'Assign Reviewers'));
    }
}
