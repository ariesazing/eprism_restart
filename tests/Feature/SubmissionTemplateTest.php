<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitPosition;
use App\Models\User;
use App\Services\SubmissionSectionService;
use App\Services\SubmissionSnapshotService;
use App\SubmissionTemplates\SubmissionTemplateRegistry;
use Database\Seeders\OrganizationalUnitPositionSeeder;
use Database\Seeders\OrganizationalUnitSeeder;
use Database\Seeders\SubmissionDocumentTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class SubmissionTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function makeSamplePdfUpload(string $name): UploadedFile
    {
        $pdf = new Fpdi;
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Sample attachment content for testing.');

        return UploadedFile::fake()->createWithContent($name, $pdf->Output('', 'S'));
    }

    private function seedLookups(): array
    {
        $this->seed(OrganizationalUnitSeeder::class);
        $this->seed(OrganizationalUnitPositionSeeder::class);
        $this->seed(SubmissionDocumentTemplateSeeder::class);

        return [
            OrganizationalUnit::query()->where('organizational_unit_type', 'school')->firstOrFail(),
            OrganizationalUnitPosition::query()->where('organizational_unit_type', 'school')->firstOrFail(),
        ];
    }

    private function proponentPayload(User $researcher, $position): array
    {
        return [
            [
                'last_name' => 'Delacruz',
                'first_name' => 'Ana',
                'email' => $researcher->email,
                'contact_number' => '09171234567',
                'position' => $position->label,
            ],
        ];
    }

    /**
     * Every section goes through the update form now — canvas-editor has no server
     * callback, so a chapter's content (JSON + its HTML mirror) is submitted as part
     * of the normal update PUT, same as table rows.
     */
    private function fullSectionsPayload(string $researchType, string $classification): array
    {
        $template = SubmissionTemplateRegistry::for($researchType, $classification);
        $payload = [];

        foreach ($template->sections as $definition) {
            if ($definition->type === 'table') {
                $row = [];
                foreach ($definition->columns as $column) {
                    $row[$column['key']] = 'Sample '.$column['label'];
                }
                $payload[$definition->key] = [$row];

                continue;
            }

            $payload[$definition->key] = [
                'content' => '{}',
                'html' => '<p>Sample content for '.$definition->label.'.</p>',
            ];
        }

        return $payload;
    }

    public function test_submit_is_blocked_until_all_required_sections_and_attachments_are_filled(): void
    {
        [$unit, $position] = $this->seedLookups();
        $researcher = User::factory()->create();

        $createResponse = $this->actingAs($researcher)->post(route('submissions.store'), [
            'title' => 'Community Learning Interventions',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $unit->name,
            'school_id' => 'SCH-001',
            'proponents' => $this->proponentPayload($researcher, $position),
        ]);
        $createResponse->assertRedirect();

        $submission = $researcher->submissions()->firstOrFail();
        $this->assertSame(SubmissionStatus::DRAFT, $submission->status);
        $this->assertSame(12, $submission->sections()->count());

        // No error-bag flash anymore — the missing sections/attachments are shown via the
        // always-visible readiness summary banner and inline indicators on the page this
        // redirects back to (see ResearchSubmissionController::submit(),
        // researcher/submissions/show.blade.php), not a one-off session error.
        $this->actingAs($researcher)->post(route('submissions.submit', $submission))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $submission->refresh();
        $this->assertSame(SubmissionStatus::DRAFT, $submission->status);

        $this->actingAs($researcher)->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertSee("This submission isn't ready to send for review yet", false)
            ->assertSee('still needs content.', false);
    }

    /**
     * The submit-with-feedback modal (resources/js/app.js's submitWithFeedback(),
     * wired into researcher/submissions/show.blade.php) sends this as a fetch() with
     * Accept: application/json instead of a plain form POST — submit() needs a real
     * JSON body (not just a redirect the browser would silently follow) so the modal
     * knows where to send the researcher once the checkmark animation finishes.
     */
    public function test_an_ajax_submit_receives_a_json_redirect_once_ready(): void
    {
        Storage::fake('local');

        [$unit, $position] = $this->seedLookups();
        $researcher = User::factory()->create();

        $this->actingAs($researcher)->post(route('submissions.store'), [
            'title' => 'Community Learning Interventions',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $unit->name,
            'school_id' => 'SCH-001',
            'proponents' => $this->proponentPayload($researcher, $position),
        ])->assertRedirect();

        $submission = $researcher->submissions()->firstOrFail();

        $this->actingAs($researcher)->put(route('submissions.update', $submission), [
            'title' => $submission->title,
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $unit->name,
            'school_id' => 'SCH-001',
            'proponents' => $this->proponentPayload($researcher, $position),
            'sections' => $this->fullSectionsPayload('basic', 'proposal'),
            'attachments' => [
                'research_instrument' => [$this->makeSamplePdfUpload('instrument.pdf')],
            ],
        ])->assertRedirect();

        $this->actingAs($researcher)
            ->postJson(route('submissions.submit', $submission))
            ->assertOk()
            ->assertJson(['redirect' => route('submissions.show', $submission)]);

        $submission->refresh();
        $this->assertSame(SubmissionStatus::SUBMITTED, $submission->status);
    }

    /**
     * Same AJAX path but still incomplete — has to come back as a real non-2xx JSON
     * error (not a 302 to a normal HTML page) or the fetch() call in
     * submitWithFeedback() would treat it as success.
     */
    public function test_an_ajax_submit_receives_a_json_422_while_incomplete(): void
    {
        [$unit, $position] = $this->seedLookups();
        $researcher = User::factory()->create();

        $this->actingAs($researcher)->post(route('submissions.store'), [
            'title' => 'Community Learning Interventions',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $unit->name,
            'school_id' => 'SCH-001',
            'proponents' => $this->proponentPayload($researcher, $position),
        ])->assertRedirect();

        $submission = $researcher->submissions()->firstOrFail();

        $this->actingAs($researcher)
            ->postJson(route('submissions.submit', $submission))
            ->assertStatus(422);

        $submission->refresh();
        $this->assertSame(SubmissionStatus::DRAFT, $submission->status);
    }

    public function test_submitting_a_complete_submission_generates_an_encrypted_readonly_snapshot(): void
    {
        Storage::fake('local');

        [$unit, $position] = $this->seedLookups();
        $researcher = User::factory()->create();

        $this->actingAs($researcher)->post(route('submissions.store'), [
            'title' => 'Community Learning Interventions',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $unit->name,
            'school_id' => 'SCH-001',
            'proponents' => $this->proponentPayload($researcher, $position),
        ])->assertRedirect();

        $submission = $researcher->submissions()->firstOrFail();

        $this->actingAs($researcher)->put(route('submissions.update', $submission), [
            'title' => $submission->title,
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $unit->name,
            'school_id' => 'SCH-001',
            'proponents' => $this->proponentPayload($researcher, $position),
            'sections' => $this->fullSectionsPayload('basic', 'proposal'),
            'attachments' => [
                'research_instrument' => [$this->makeSamplePdfUpload('instrument.pdf')],
            ],
        ])->assertRedirect();

        $this->actingAs($researcher)->post(route('submissions.submit', $submission))
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $submission->refresh();
        $this->assertSame(SubmissionStatus::SUBMITTED, $submission->status);

        $snapshot = $submission->latestSnapshot();
        $this->assertNotNull($snapshot);

        $rawBytes = Storage::disk('local')->get($snapshot->path);
        $this->assertStringStartsNotWith('%PDF', $rawBytes);

        $decrypted = app(SubmissionSnapshotService::class)->decryptedBytes($snapshot);
        $this->assertStringStartsWith('%PDF', $decrypted);

        $this->actingAs($researcher)->put(route('submissions.update', $submission), [
            'title' => $submission->title,
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $unit->name,
            'school_id' => 'SCH-001',
            'proponents' => $this->proponentPayload($researcher, $position),
        ])->assertForbidden();
    }

    /**
     * The canvas editor lives on its own dedicated /chapters page now (no sidebar — see
     * ResearchSubmissionController::chapters() and researcher/submissions/chapters.blade.php),
     * not inline on the main show() page — this confirms both halves of that split.
     */
    public function test_submission_show_page_renders_a_canvas_editor_for_each_richtext_chapter(): void
    {
        [$unit, $position] = $this->seedLookups();
        $researcher = User::factory()->create();

        $this->actingAs($researcher)->post(route('submissions.store'), [
            'title' => 'Community Learning Interventions',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $unit->name,
            'school_id' => 'SCH-001',
            'proponents' => $this->proponentPayload($researcher, $position),
        ])->assertRedirect();

        $submission = $researcher->submissions()->firstOrFail();

        $this->actingAs($researcher)->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertDontSee('data-canvas-editor="toolbar-inline"', false);

        $this->actingAs($researcher)->get(route('submissions.chapters', $submission))
            ->assertOk()
            ->assertSee('data-canvas-editor="toolbar-inline"', false)
            ->assertSee('data-canvas-editor-data', false);
    }

    public function test_saving_a_chapter_sanitizes_its_html_mirror(): void
    {
        [$unit, $position] = $this->seedLookups();
        $researcher = User::factory()->create();

        $this->actingAs($researcher)->post(route('submissions.store'), [
            'title' => 'Community Learning Interventions',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $unit->name,
            'school_id' => 'SCH-001',
            'proponents' => $this->proponentPayload($researcher, $position),
        ])->assertRedirect();

        $submission = $researcher->submissions()->firstOrFail();
        $template = $submission->template();
        $section = $submission->sections()->where('type', '!=', 'table')->firstOrFail();

        app(SubmissionSectionService::class)->save($submission, $template, [
            $section->section_key => [
                'content' => '{}',
                'html' => '<p><span style="font-weight:bold">Bold text</span></p>'
                    .'<script>alert(1)</script><img src="javascript:alert(1)">',
            ],
        ]);

        $saved = $section->fresh()->content_html;

        $this->assertStringContainsString('font-weight:bold', $saved);
        $this->assertStringContainsString('Bold text', $saved);
        $this->assertStringNotContainsString('<script', $saved);
        $this->assertStringNotContainsString('javascript:', $saved);
    }

    /**
     * Regression test: HTMLPurifier's stock data: URI scheme only allows image/jpeg,
     * /gif, and /png — without WebpAllowedDataUriScheme registered (see
     * SubmissionSectionService::sanitizeRichText()), a pasted chapter image (WebP, see
     * resources/js/document-editor/index.js's capPastedImageSize()) would be silently
     * stripped out of the chapter's content the moment it's saved.
     */
    public function test_a_pasted_webp_image_survives_sanitization(): void
    {
        [$unit, $position] = $this->seedLookups();
        $researcher = User::factory()->create();

        $this->actingAs($researcher)->post(route('submissions.store'), [
            'title' => 'Community Learning Interventions',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $unit->name,
            'school_id' => 'SCH-001',
            'proponents' => $this->proponentPayload($researcher, $position),
        ])->assertRedirect();

        $submission = $researcher->submissions()->firstOrFail();
        $template = $submission->template();
        $section = $submission->sections()->where('type', '!=', 'table')->firstOrFail();

        // A minimal, genuinely valid 1x1 WebP image (generated via GD's imagewebp()) — not
        // just an arbitrary byte string. HTMLPurifier's data: validator base64-decodes and
        // sniffs the actual payload with exif_imagetype()/getimagesize(), so a fake/corrupt
        // one would fail its check regardless of the declared content type.
        $webpBase64 = 'UklGRiQAAABXRUJQVlA4IBgAAAAwAQCdASoBAAEAAUAmJaQAA3AA/v02aAA=';

        app(SubmissionSectionService::class)->save($submission, $template, [
            $section->section_key => [
                'content' => '{}',
                'html' => '<p><img src="data:image/webp;base64,'.$webpBase64.'" width="1" height="1"></p>',
            ],
        ]);

        $saved = $section->fresh()->content_html;

        $this->assertStringContainsString('<img', $saved);
        $this->assertStringContainsString('data:image/webp;base64,', $saved);
    }

    public function test_only_the_owning_researcher_can_view_the_dedicated_chapters_page(): void
    {
        [$unit, $position] = $this->seedLookups();
        $researcher = User::factory()->create();
        $someoneElse = User::factory()->create();

        $this->actingAs($researcher)->post(route('submissions.store'), [
            'title' => 'Community Learning Interventions',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $unit->name,
            'school_id' => 'SCH-001',
            'proponents' => $this->proponentPayload($researcher, $position),
        ])->assertRedirect();

        $submission = $researcher->submissions()->firstOrFail();

        $this->actingAs($researcher)->get(route('submissions.chapters', $submission))
            ->assertOk()
            ->assertSee($submission->title);

        $this->actingAs($someoneElse)->get(route('submissions.chapters', $submission))
            ->assertForbidden();
    }

    /**
     * A locked (no longer editable) submission's chapters page must render read-only —
     * no wizard tabs, no canvas editor for the researcher to keep typing into — matching
     * the same disabled behavior show() already gives every other field on a locked
     * submission.
     */
    public function test_a_locked_submissions_chapters_page_is_read_only(): void
    {
        [$unit, $position] = $this->seedLookups();
        $researcher = User::factory()->create();

        $this->actingAs($researcher)->post(route('submissions.store'), [
            'title' => 'Community Learning Interventions',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $unit->name,
            'school_id' => 'SCH-001',
            'proponents' => $this->proponentPayload($researcher, $position),
        ])->assertRedirect();

        $submission = $researcher->submissions()->firstOrFail();
        $submission->update(['status' => SubmissionStatus::UNDER_REVIEW]);

        $this->actingAs($researcher)->get(route('submissions.chapters', $submission))
            ->assertOk()
            ->assertDontSee('data-canvas-editor="toolbar-inline"', false)
            ->assertDontSee('data-wizard-controls', false);
    }
}
