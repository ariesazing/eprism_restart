<?php

namespace Tests\Feature;

use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitPosition;
use App\Models\ResearchSubmission;
use App\Models\User;
use App\SubmissionTemplates\SubmissionTemplateRegistry;
use Database\Seeders\OrganizationalUnitPositionSeeder;
use Database\Seeders\OrganizationalUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class SubmissionReadinessAssessmentTest extends TestCase
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

    private function completeSubmission(User $researcher, $unit, $position): ResearchSubmission
    {
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

        return $submission->refresh();
    }

    public function test_sram_combines_completeness_and_grammar_and_caches_the_grammar_check(): void
    {
        Storage::fake('local');
        config(['services.languagetool.url' => 'http://fake-languagetool.test']);

        Http::fake([
            'fake-languagetool.test/*' => Http::response(['matches' => array_fill(0, 3, ['message' => 'issue'])], 200),
        ]);

        [$unit, $position] = $this->seedLookups();
        $researcher = User::factory()->create();
        $submission = $this->completeSubmission($researcher, $unit, $position);

        $response = $this->actingAs($researcher)->getJson(route('submissions.sram', $submission));

        $response->assertOk();
        $response->assertJson([
            'completeness_percent' => 100,
            'grammar_available' => true,
            'issue_count' => 3,
        ]);
        $this->assertGreaterThan(0, $response->json('word_count'));
        $this->assertIsFloat($response->json('grammar_percent'));

        Http::assertSentCount(1);

        // Re-checking without content changes must reuse the cached grammar result.
        $this->actingAs($researcher)->getJson(route('submissions.sram', $submission))->assertOk();
        Http::assertSentCount(1);
    }

    public function test_sram_rejects_a_researcher_who_does_not_own_the_submission(): void
    {
        Storage::fake('local');

        [$unit, $position] = $this->seedLookups();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $submission = $this->completeSubmission($owner, $unit, $position);

        $this->actingAs($other)->getJson(route('submissions.sram', $submission))->assertForbidden();
    }
}
