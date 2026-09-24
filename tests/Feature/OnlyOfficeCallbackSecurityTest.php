<?php

namespace Tests\Feature;

use App\Models\SubmissionDocumentTemplate;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OnlyOfficeCallbackSecurityTest extends TestCase
{
    use RefreshDatabase;

    public static function targets(): array
    {
        return [['sections'], ['templates']];
    }

    private function callbackUrlFor(string $type): string
    {
        config(['services.onlyoffice.url' => 'https://office.test', 'services.onlyoffice.jwt_secret' => str_repeat('s', 40)]);
        Storage::fake('local');
        Queue::fake();
        Storage::disk('local')->put('test.docx', 'original');
        if ($type === 'sections') {
            $submission = User::factory()->create()->submissions()->create([
                'title' => 'Callback test', 'research_type' => 'basic', 'classification' => 'proposal', 'status' => 'draft',
            ]);
            $model = $submission->sections()->create(['section_key' => 'rationale', 'label' => 'Rationale', 'type' => 'rich_text']);
            $model->forceFill(['onlyoffice_key' => 'document-key', 'onlyoffice_path' => 'test.docx'])->save();
            $parameter = 'section';
        } else {
            $model = SubmissionDocumentTemplate::create(['template_key' => 'action_proposal', 'body_html' => '<p>Test</p>']);
            $model->forceFill(['docx_key' => 'document-key', 'docx_path' => 'test.docx'])->save();
            $parameter = 'template';
        }

        return URL::temporarySignedRoute("onlyoffice.{$type}.callback", now()->addHour(), [$parameter => $model->id, 'key' => 'document-key'], absolute: false);
    }

    private function headers(array $payload): array
    {
        return ['Authorization' => 'Bearer '.JWT::encode($payload, config('services.onlyoffice.jwt_secret'), 'HS256')];
    }

    #[DataProvider('targets')]
    public function test_editor_tokens_cannot_authorize_forged_save_payloads(string $type): void
    {
        $url = $this->callbackUrlFor($type);
        Http::fake();
        $this->postJson($url, ['status' => 2, 'url' => 'http://169.254.169.254/latest/meta-data/'], $this->headers([
            'document' => ['key' => 'document-key'], 'editorConfig' => ['callbackUrl' => $url],
        ]))->assertForbidden();
        Http::assertNothingSent();
        $this->assertSame('original', Storage::disk('local')->get('test.docx'));
    }

    #[DataProvider('targets')]
    public function test_signed_payload_is_bound_to_document_and_download_origin(string $type): void
    {
        $url = $this->callbackUrlFor($type);
        Http::fake();
        $this->postJson($url, [], $this->headers(['payload' => [
            'key' => 'another-document', 'status' => 2, 'url' => 'https://office.test/saved.docx',
        ]]))->assertForbidden();
        $this->postJson($url, [], $this->headers(['payload' => [
            'key' => 'document-key', 'status' => 2, 'url' => 'http://169.254.169.254/latest/meta-data/',
        ]]))->assertJson(['error' => 1]);
        Http::assertNothingSent();
    }

    #[DataProvider('targets')]
    public function test_unsigned_body_cannot_change_the_signed_download_url(string $type): void
    {
        $url = $this->callbackUrlFor($type);
        Http::fake(['https://office.test/saved.docx' => Http::response('signed-document')]);
        $this->postJson($url, ['status' => 2, 'url' => 'http://169.254.169.254/latest/meta-data/'], $this->headers(['payload' => [
            'key' => 'document-key', 'status' => 2, 'url' => 'https://office.test/saved.docx',
        ]]))->assertJson(['error' => 0]);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://office.test/saved.docx');
        $this->assertSame('signed-document', Storage::disk('local')->get('test.docx'));
    }

    #[DataProvider('targets')]
    public function test_redirects_are_not_saved_as_documents(string $type): void
    {
        $url = $this->callbackUrlFor($type);
        Http::fake(['https://office.test/saved.docx' => Http::response('', 302, ['Location' => 'http://169.254.169.254/'])]);
        $this->postJson($url, [], $this->headers(['payload' => [
            'key' => 'document-key', 'status' => 2, 'url' => 'https://office.test/saved.docx',
        ]]))->assertJson(['error' => 1]);
        Http::assertSentCount(1);
        $this->assertSame('original', Storage::disk('local')->get('test.docx'));
    }
}
