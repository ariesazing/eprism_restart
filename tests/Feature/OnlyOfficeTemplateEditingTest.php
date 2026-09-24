<?php

namespace Tests\Feature;

use App\Models\SubmissionDocumentTemplate;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The admin-facing ONLYOFFICE editor for a document template's own front-matter .docx (see
 * OnlyOfficeTemplateController and resources/views/admin/document-templates/edit.blade.php) —
 * the template-authoring counterpart to OnlyOfficeChapterEditingTest's researcher-facing
 * per-chapter editor.
 */
class OnlyOfficeTemplateEditingTest extends TestCase
{
    use RefreshDatabase;

    private function fakeOnlyOfficeConfig(): void
    {
        config([
            'services.onlyoffice.enabled' => true,
            'services.onlyoffice.url' => 'https://office.test',
            'services.onlyoffice.jwt_secret' => str_repeat('s', 40),
        ]);
    }

    public function test_edit_page_renders_the_onlyoffice_mount_with_a_real_office_url(): void
    {
        $this->fakeOnlyOfficeConfig();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.document-templates.edit', 'action_proposal'))
            ->assertOk()
            ->assertSee('data-onlyoffice-editor', false)
            ->assertSee('data-office-url="https://office.test"', false)
            ->assertSee(route('admin.document-templates.onlyoffice-config', 'action_proposal'), false);
    }

    public function test_opening_an_unauthored_template_seeds_a_blank_docx_and_returns_a_signed_config(): void
    {
        $this->fakeOnlyOfficeConfig();
        Storage::fake('local');
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->getJson(route('admin.document-templates.onlyoffice-config', 'action_proposal'))
            ->assertOk();

        $template = SubmissionDocumentTemplate::query()->where('template_key', 'action_proposal')->firstOrFail();
        $this->assertNotNull($template->docx_path);
        Storage::disk('local')->assertExists($template->docx_path);
        $response->assertJsonPath('document.fileType', 'docx');
        $response->assertJsonPath('document.key', $template->docx_key);
    }

    public function test_opening_template_preserves_file_when_database_reference_is_missing(): void
    {
        $this->fakeOnlyOfficeConfig();
        Storage::fake('local');
        $path = 'onlyoffice-documents/templates/basic_proposal.docx';
        $bytes = file_get_contents(resource_path('onlyoffice/blank-chapter.docx'));
        Storage::disk('local')->put($path, $bytes.'saved-template-marker');
        SubmissionDocumentTemplate::create(['template_key' => 'basic_proposal']);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('admin.document-templates.onlyoffice-config', 'basic_proposal'))
            ->assertOk();

        $this->assertSame($bytes.'saved-template-marker', Storage::disk('local')->get($path));
        $this->assertSame($path, SubmissionDocumentTemplate::active('basic_proposal')->docx_path);
    }

    public function test_config_rejects_an_unknown_template_key(): void
    {
        $this->fakeOnlyOfficeConfig();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson(route('admin.document-templates.onlyoffice-config', 'not_a_real_template'))
            ->assertNotFound();
    }

    public function test_a_non_admin_cannot_open_the_template_editor(): void
    {
        $this->fakeOnlyOfficeConfig();
        $researcher = User::factory()->create();

        $this->actingAs($researcher)
            ->getJson(route('admin.document-templates.onlyoffice-config', 'action_proposal'))
            ->assertForbidden();
    }

    public function test_saving_a_template_stores_the_docx_and_rotates_its_key(): void
    {
        $this->fakeOnlyOfficeConfig();
        Storage::fake('local');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson(route('admin.document-templates.onlyoffice-config', 'action_proposal'))->assertOk();
        $template = SubmissionDocumentTemplate::query()->where('template_key', 'action_proposal')->firstOrFail();
        $before = $template->docx_key;

        Http::fake(['*/saved-template.docx' => Http::response('fake-docx-bytes-are-enough-here')]);

        $callbackUrl = URL::temporarySignedRoute('onlyoffice.templates.callback', now()->addHour(), ['template' => $template->id, 'key' => $template->docx_key], absolute: false);
        $token = JWT::encode(['payload' => ['key' => $template->docx_key, 'status' => 2, 'url' => 'https://office.test/saved-template.docx']], config('services.onlyoffice.jwt_secret'), 'HS256');

        $this->postJson($callbackUrl, ['status' => 2, 'url' => 'https://office.test/saved-template.docx'], ['Authorization' => 'Bearer '.$token])
            ->assertJson(['error' => 0]);

        $template->refresh();
        $this->assertNotSame($before, $template->docx_key);
        $this->assertSame('fake-docx-bytes-are-enough-here', Storage::disk('local')->get($template->docx_path));
    }
}
