<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\RapmDocument;
use App\Models\SubmissionDocumentTemplate;
use App\Models\User;
use Database\Seeders\RapmTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RAPM's two templates are managed on the same admin.document-templates.* page/routes as
 * submission chapter templates (see DocumentTemplateController) — this only covers the
 * RAPM-specific branch of that shared controller.
 */
class RapmTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RapmTemplateSeeder::class);
    }

    private function templateUpdatePayload(string $bodyHtml, string $headerHtml = '', string $footerHtml = ''): array
    {
        return [
            'content' => '{}',
            'body_html' => $bodyHtml,
            'header_html' => $headerHtml,
            'footer_html' => $footerHtml,
        ];
    }

    public function test_both_rapm_templates_are_seeded(): void
    {
        $this->assertNotNull(SubmissionDocumentTemplate::active(RapmDocument::KIND_REVIEW_SUMMARY));
        $this->assertNotNull(SubmissionDocumentTemplate::active(RapmDocument::KIND_ROUTING_SLIP));
    }

    public function test_admin_can_view_and_edit_a_rapm_template_from_the_shared_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.document-templates.index'))
            ->assertOk()
            ->assertSee('Review Summary')
            ->assertSee('Routing Slip');

        $this->actingAs($admin)->get(route('admin.document-templates.edit', RapmDocument::KIND_REVIEW_SUMMARY))
            ->assertOk()
            ->assertSee('${title}', false)
            ->assertSee('reviewers');

        $this->actingAs($admin)->post(
            route('admin.document-templates.update', RapmDocument::KIND_REVIEW_SUMMARY),
            $this->templateUpdatePayload('<p>Custom heading</p><p>${title}</p>'),
        )->assertRedirect();

        $template = SubmissionDocumentTemplate::active(RapmDocument::KIND_REVIEW_SUMMARY);
        $this->assertSame('<p>Custom heading</p><p>${title}</p>', $template->body_html);
        $this->assertSame($admin->id, $template->updated_by);
    }

    public function test_non_admin_cannot_access_document_templates(): void
    {
        $researcher = User::factory()->create();

        $this->actingAs($researcher)->get(route('admin.document-templates.index'))->assertForbidden();
    }

    public function test_admin_can_preview_review_summary_against_a_reviewed_submission(): void
    {
        $admin = User::factory()->admin()->create();
        $reviewers = User::factory()->reviewer()->count(3)->create();
        $researcher = User::factory()->create();

        $submission = $researcher->submissions()->create([
            'title' => 'Preview Study',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::UNDER_REVIEW,
        ]);
        $submission->reviewers()->attach($reviewers->pluck('id'));
        $submission->reviews()->create([
            'reviewer_id' => $reviewers->first()->id,
            'criteria_scores' => ['originality' => 4, 'methodology' => 4, 'clarity' => 4, 'compliance' => 4],
            'comments' => 'Good.',
            'recommendation' => 'approve',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(
            route('admin.document-templates.preview', RapmDocument::KIND_REVIEW_SUMMARY),
            $this->templateUpdatePayload('<p>${title}</p>{{#each reviewers}}<p>${reviewer_name}</p>{{/each}}'),
        );

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }
}
