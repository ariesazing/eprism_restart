<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChapterSaveButtonPlacementTest extends TestCase
{
    use RefreshDatabase;

    private function submissionFor(User $user, SubmissionStatus $status = SubmissionStatus::DRAFT)
    {
        return $user->submissions()->create([
            'title' => 'Study', 'research_type' => 'basic', 'classification' => 'proposal', 'status' => $status,
        ]);
    }

    public function test_save_now_sits_above_chapter_one_in_the_left_panel_and_is_emphasised(): void
    {
        $user = User::factory()->create();
        $submission = $this->submissionFor($user);

        $this->actingAs($user)->get(route('submissions.chapters', $submission))->assertOk()
            // Before the first chapter tab, with its autosave status line, all inside the same
            // sticky group the tabs are in.
            ->assertSeeInOrder(['data-wizard-controls', 'data-manual-save-button', 'Save now', 'data-autosave-status', 'data-wizard-chapter="0"'], false)
            // Emphasised: solid gold, full width, shadowed, bold — distinct from the cherry active tab.
            ->assertSee('bg-gold-400', false)
            ->assertSee('shadow-md', false)
            ->assertSee('font-bold', false);
    }

    public function test_there_is_exactly_one_save_button_and_status_line(): void
    {
        $user = User::factory()->create();
        $html = $this->actingAs($user)->get(route('submissions.chapters', $this->submissionFor($user)))->assertOk()->getContent();

        // The editor script binds to these by attribute; a second copy would split the status text.
        $this->assertSame(1, substr_count($html, 'data-manual-save-button'));
        $this->assertSame(1, substr_count($html, 'data-autosave-status'));
    }

    public function test_a_locked_submission_has_no_save_button(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('submissions.chapters', $this->submissionFor($user, SubmissionStatus::UNDER_REVIEW)))->assertOk()
            ->assertDontSee('data-manual-save-button', false);
    }
}
