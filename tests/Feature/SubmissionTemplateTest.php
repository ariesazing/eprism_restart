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

        $this->actingAs($researcher)->post(route('submissions.submit', $submission))
            ->assertSessionHasErrors('submission');

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
}
