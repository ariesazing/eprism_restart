<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitPosition;
use App\Models\User;
use App\SubmissionTemplates\SubmissionTemplateRegistry;
use Database\Seeders\OrganizationalUnitPositionSeeder;
use Database\Seeders\OrganizationalUnitSeeder;
use Database\Seeders\SubmissionDocumentTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

/**
 * A researcher pasting an image into a chapter's rich-text content (canvas-editor exports
 * it as an inline base64 <img> — see resources/js/document-editor/index.js's
 * capPastedImageSize()) used to crash on submit: SubmissionSnapshotService::generate()
 * synchronously renders the whole manuscript through dompdf (SubmissionPdfComposer::compose)
 * to produce the submitted snapshot, and dompdf decodes every embedded image into memory
 * for layout on every render. This exercises that same end-to-end path with a chapter that
 * actually contains an embedded image, so a regression here fails loudly instead of only
 * showing up as a fatal error in production.
 */
class SubmissionEmbeddedImageTest extends TestCase
{
    use RefreshDatabase;

    // A real, minimal 1x1 red PNG — has to be genuinely decodable (by GD, and by a browser
    // loading the resulting PDF), not just an arbitrary byte string, or this would prove
    // nothing about dompdf's actual image-handling path.
    private const ONE_PIXEL_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAX2n0hgAAAABJRU5ErkJggg==';

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

    public function test_submitting_a_chapter_with_a_pasted_image_does_not_crash(): void
    {
        Storage::fake('local');

        [$unit, $position] = $this->seedLookups();
        $researcher = User::factory()->create();

        $proponents = [[
            'last_name' => 'Delacruz',
            'first_name' => 'Ana',
            'email' => $researcher->email,
            'contact_number' => '09171234567',
            'position' => $position->label,
        ]];

        $this->actingAs($researcher)->post(route('submissions.store'), [
            'title' => 'A Study With An Embedded Image',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $unit->name,
            'school_id' => 'SCH-002',
            'proponents' => $proponents,
        ])->assertRedirect();

        $submission = $researcher->submissions()->firstOrFail();
        $template = SubmissionTemplateRegistry::for('basic', 'proposal');

        $sections = [];
        foreach ($template->sections as $definition) {
            if ($definition->type === 'table') {
                $row = [];
                foreach ($definition->columns as $column) {
                    $row[$column['key']] = 'Sample '.$column['label'];
                }
                $sections[$definition->key] = [$row];

                continue;
            }

            $sections[$definition->key] = [
                'content' => '{}',
                'html' => '<p>Sample content for '.$definition->label.'.</p>',
            ];
        }

        // Plant the pasted image in the first rich-text section only — enough to prove the
        // pipeline survives an embedded image without needing every chapter to carry one.
        $firstRichTextKey = collect($template->sections)->first(fn ($d) => $d->type !== 'table')->key;
        $sections[$firstRichTextKey] = [
            'content' => '{}',
            'html' => '<p>Before the image.</p>'
                .'<p><img src="data:image/png;base64,'.self::ONE_PIXEL_PNG_BASE64.'" width="1" height="1"></p>'
                .'<p>After the image.</p>',
        ];

        $this->actingAs($researcher)->put(route('submissions.update', $submission), [
            'title' => $submission->title,
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $unit->name,
            'school_id' => 'SCH-002',
            'proponents' => $proponents,
            'sections' => $sections,
            'attachments' => [
                'research_instrument' => [$this->makeSamplePdfUpload('instrument.pdf')],
            ],
        ])->assertRedirect();

        $response = $this->actingAs($researcher)->post(route('submissions.submit', $submission));

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect();

        $submission->refresh();
        $this->assertSame(SubmissionStatus::SUBMITTED, $submission->status);
        $this->assertNotNull($submission->latestSnapshot());
    }
}
