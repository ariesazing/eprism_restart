<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Database\Seeders\OrganizationalUnitPositionSeeder;
use Database\Seeders\OrganizationalUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DraftAttachmentEditingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OrganizationalUnitSeeder::class);
        $this->seed(OrganizationalUnitPositionSeeder::class);
    }

    private function makeDraft(User $researcher): \App\Models\ResearchSubmission
    {
        return $researcher->submissions()->create([
            'title' => 'Attachment Test',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT,
        ]);
    }

    public function test_researcher_can_add_and_then_remove_an_attachment_while_still_a_draft(): void
    {
        Storage::fake('local');

        $researcher = User::factory()->create();
        $school = OrganizationalUnit::query()->where('organizational_unit_type', 'school')->firstOrFail();
        $draft = $this->makeDraft($researcher);
        $draft->proponents()->create(['last_name' => 'Cruz', 'first_name' => 'Ana', 'position' => 'Teacher I', 'is_lead' => true, 'sort_order' => 10]);

        $upload = $this->actingAs($researcher)->put(route('submissions.update', $draft), [
            'title' => $draft->title,
            'research_type' => $draft->research_type,
            'classification' => $draft->classification,
            'organizational_unit' => $school->name,
            'school_id' => $school->school_id,
            'proponents' => [
                ['id' => $draft->proponents()->first()->id, 'last_name' => 'Cruz', 'first_name' => 'Ana', 'position' => 'Teacher I'],
            ],
            'sections' => [],
            'attachments' => [
                'research_instrument' => [UploadedFile::fake()->create('instrument.pdf', 100, 'application/pdf')],
            ],
        ]);

        $upload->assertSessionDoesntHaveErrors();
        $document = $draft->documents()->firstOrFail();
        $this->assertSame(1, $draft->documents()->count());
        Storage::disk('local')->assertExists($document->path);

        $delete = $this->actingAs($researcher)->delete(route('submissions.attachments.destroy', [$draft, $document]));

        $delete->assertRedirect();
        $this->assertSame(0, $draft->documents()->count());
        Storage::disk('local')->assertMissing($document->path);
    }

    /**
     * The draft editor removes attachments via a plain axios AJAX call now — a <form>
     * nested inside the page's single outer draft form was invalid HTML and silently
     * broke the whole form (see attachments-editor.blade.php). Axios always sends
     * X-Requested-With: XMLHttpRequest (bootstrap.js), which the controller now uses
     * to return JSON instead of a redirect an XHR call would just discard.
     */
    public function test_removing_an_attachment_via_ajax_returns_json_instead_of_a_redirect(): void
    {
        Storage::fake('local');

        $researcher = User::factory()->create();
        $draft = $this->makeDraft($researcher);

        $document = $draft->documents()->create([
            'uploaded_by' => $researcher->id,
            'document_type' => 'research_instrument',
            'original_name' => 'instrument.pdf',
            'path' => 'research-documents/instrument.pdf',
            'mime_type' => 'application/pdf',
        ]);
        Storage::disk('local')->put($document->path, 'fake pdf bytes');

        $this->actingAs($researcher)
            ->delete(route('submissions.attachments.destroy', [$draft, $document]), [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJson(['deleted' => true]);

        $this->assertSame(0, $draft->documents()->count());
        Storage::disk('local')->assertMissing($document->path);
    }

    public function test_attachments_cannot_be_removed_once_the_submission_is_no_longer_a_draft(): void
    {
        Storage::fake('local');

        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'Locked Submission',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::SUBMITTED,
        ]);

        $document = $submission->documents()->create([
            'uploaded_by' => $researcher->id,
            'document_type' => 'research_instrument',
            'original_name' => 'instrument.pdf',
            'path' => 'research-documents/instrument.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $this->actingAs($researcher)
            ->delete(route('submissions.attachments.destroy', [$submission, $document]))
            ->assertForbidden();

        $this->assertSame(1, $submission->documents()->count());
    }

    public function test_another_researcher_cannot_remove_someone_elses_attachment(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $submission = $this->makeDraft($owner);

        $document = $submission->documents()->create([
            'uploaded_by' => $owner->id,
            'document_type' => 'research_instrument',
            'original_name' => 'instrument.pdf',
            'path' => 'research-documents/instrument.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $this->actingAs($intruder)
            ->delete(route('submissions.attachments.destroy', [$submission, $document]))
            ->assertForbidden();
    }

    public function test_owner_can_view_a_proponents_uploaded_photo(): void
    {
        Storage::fake('local');

        $researcher = User::factory()->create();
        $draft = $this->makeDraft($researcher);
        $photoPath = 'research-photos/proponent.jpg';
        Storage::disk('local')->put($photoPath, 'fake image bytes');

        $proponent = $draft->proponents()->create([
            'last_name' => 'Cruz',
            'first_name' => 'Ana',
            'position' => 'Teacher I',
            'is_lead' => true,
            'sort_order' => 10,
            'photo_path' => $photoPath,
        ]);

        $this->actingAs($researcher)
            ->get(route('submissions.proponents.photo', [$draft, $proponent]))
            ->assertOk();
    }

    public function test_another_researcher_cannot_view_someone_elses_proponent_photo(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $draft = $this->makeDraft($owner);
        $photoPath = 'research-photos/proponent.jpg';
        Storage::disk('local')->put($photoPath, 'fake image bytes');

        $proponent = $draft->proponents()->create([
            'last_name' => 'Cruz',
            'first_name' => 'Ana',
            'position' => 'Teacher I',
            'is_lead' => true,
            'sort_order' => 10,
            'photo_path' => $photoPath,
        ]);

        $this->actingAs($intruder)
            ->get(route('submissions.proponents.photo', [$draft, $proponent]))
            ->assertForbidden();
    }

    public function test_proponent_photo_route_404s_when_no_photo_was_uploaded(): void
    {
        $researcher = User::factory()->create();
        $draft = $this->makeDraft($researcher);

        $proponent = $draft->proponents()->create([
            'last_name' => 'Cruz',
            'first_name' => 'Ana',
            'position' => 'Teacher I',
            'is_lead' => true,
            'sort_order' => 10,
        ]);

        $this->actingAs($researcher)
            ->get(route('submissions.proponents.photo', [$draft, $proponent]))
            ->assertNotFound();
    }
}
