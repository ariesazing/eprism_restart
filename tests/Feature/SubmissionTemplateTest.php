<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitPosition;
use App\Models\User;
use App\SubmissionTemplates\SubmissionTemplateRegistry;
use Database\Seeders\OrganizationalUnitPositionSeeder;
use Database\Seeders\OrganizationalUnitSeeder;
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
        $pdf = new Fpdi();
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

        return [
            OrganizationalUnit::query()->where('organizational_unit_type', 'school')->firstOrFail(),
            OrganizationalUnitPosition::query()->where('organizational_unit_type', 'school')->firstOrFail(),
        ];
    }

    private function proponentPayload(User $researcher, $unit, $position): array
    {
        return [
            [
                'last_name' => 'Delacruz',
                'first_name' => 'Ana',
                'email' => $researcher->email,
                'contact_number' => '09171234567',
                'organizational_unit' => $unit->name,
                'position' => $position->label,
                'school_id' => 'SCH-001',
            ],
        ];
    }

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

            $payload[$definition->key] = '<p>Sample content for '.$definition->label.'.</p>';
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
            'proponents' => $this->proponentPayload($researcher, $unit, $position),
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
            'proponents' => $this->proponentPayload($researcher, $unit, $position),
        ])->assertRedirect();

        $submission = $researcher->submissions()->firstOrFail();

        $this->actingAs($researcher)->put(route('submissions.update', $submission), [
            'title' => $submission->title,
            'research_type' => 'basic',
            'classification' => 'proposal',
            'proponents' => $this->proponentPayload($researcher, $unit, $position),
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

        $decrypted = app(\App\Services\SubmissionSnapshotService::class)->decryptedBytes($snapshot);
        $this->assertStringStartsWith('%PDF', $decrypted);

        $this->actingAs($researcher)->put(route('submissions.update', $submission), [
            'title' => $submission->title,
            'research_type' => 'basic',
            'classification' => 'proposal',
            'proponents' => $this->proponentPayload($researcher, $unit, $position),
        ])->assertForbidden();
    }
}
