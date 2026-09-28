<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\ResearchSubmission;
use App\Models\SubmissionSection;
use App\Models\User;
use App\Services\SubmissionAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionSramTest extends TestCase
{
    use RefreshDatabase;

    private User $researcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->researcher = User::factory()->create([
            'role' => UserRole::RESEARCHER,
            'status' => AccountStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    private function createSubmission(array $attributes = []): ResearchSubmission
    {
        return ResearchSubmission::create(array_merge([
            'researcher_id' => $this->researcher->id,
            'title' => 'Sample Research Study',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT,
            'organizational_unit' => 'SDO Test',
        ], $attributes));
    }

    public function test_sram_flags_chapter_with_fewer_than_ten_words(): void
    {
        $submission = $this->createSubmission();

        // Section with fewer than 10 words
        SubmissionSection::create([
            'research_submission_id' => $submission->id,
            'section_key' => 'introduction',
            'label' => 'Introduction',
            'type' => 'text',
            'content' => 'This is a very short introduction text.', // 7 words
            'sort_order' => 1,
        ]);

        $service = app(SubmissionAssessmentService::class);
        $result = $service->assess($submission);

        $this->assertNotEmpty($result['flagged_chapters']);
        $this->assertEquals(1, $result['metrics']['flagged_count']);
        $this->assertEquals('introduction', $result['flagged_chapters'][0]['key']);
        $this->assertStringContainsString('less than 10 words', $result['flagged_chapters'][0]['reason']);
    }

    public function test_sram_does_not_flag_chapter_with_ten_or_more_words(): void
    {
        $submission = $this->createSubmission();

        // Section with 12 words
        SubmissionSection::create([
            'research_submission_id' => $submission->id,
            'section_key' => 'introduction',
            'label' => 'Introduction',
            'type' => 'text',
            'content' => 'One two three four five six seven eight nine ten eleven twelve.',
            'sort_order' => 1,
        ]);

        $service = app(SubmissionAssessmentService::class);
        $result = $service->assess($submission);

        $this->assertEmpty($result['flagged_chapters']);
        $this->assertEquals(0, $result['metrics']['flagged_count']);
    }

    public function test_sram_ignores_table_chapters_when_evaluating_word_count(): void
    {
        $submission = $this->createSubmission();

        // Table section has type 'table'
        SubmissionSection::create([
            'research_submission_id' => $submission->id,
            'section_key' => 'work_plan',
            'label' => 'Work Plan',
            'type' => 'table',
            'content' => 'Short', // 1 word, but isTable() is true
            'sort_order' => 1,
        ]);

        $service = app(SubmissionAssessmentService::class);
        $result = $service->assess($submission);

        $this->assertEmpty($result['flagged_chapters']);
    }

    public function test_sram_generates_and_stores_metrics_in_database(): void
    {
        $submission = $this->createSubmission();

        SubmissionSection::create([
            'research_submission_id' => $submission->id,
            'section_key' => 'introduction',
            'label' => 'Introduction',
            'type' => 'text',
            'content' => 'Short sentence here.',
            'sort_order' => 1,
        ]);

        $service = app(SubmissionAssessmentService::class);
        $result = $service->assess($submission);

        $submission->refresh();
        $this->assertNotNull($submission->readinessAssessment);
        $this->assertIsArray($submission->readinessAssessment->metrics);
        $this->assertArrayHasKey('completeness_percent', $submission->readinessAssessment->metrics);
        $this->assertArrayHasKey('chapter_metrics', $submission->readinessAssessment->metrics);
    }

    public function test_sram_endpoint_returns_json_assessment(): void
    {
        $submission = $this->createSubmission();

        SubmissionSection::create([
            'research_submission_id' => $submission->id,
            'section_key' => 'introduction',
            'label' => 'Introduction',
            'type' => 'text',
            'content' => 'This is a short chapter.',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->researcher)
            ->getJson(route('submissions.sram', $submission));

        $response->assertOk()
            ->assertJsonStructure([
                'completeness_percent',
                'sections',
                'attachments',
                'grammar_available',
                'grammar_percent',
                'word_count',
                'issue_count',
                'flagged_chapters',
                'metrics',
            ]);
    }

    public function test_submission_show_page_renders_sram_panel(): void
    {
        $submission = $this->createSubmission();

        $response = $this->actingAs($this->researcher)
            ->get(route('submissions.show', $submission));

        $response->assertOk()
            ->assertSee('Submission Readiness Assessment (SRAM)')
            ->assertSee('Run Quick SRA');
    }
}
