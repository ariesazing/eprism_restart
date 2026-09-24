<?php

namespace Tests\Feature;

use App\Exceptions\OnlyOfficeUnavailableException;
use App\Services\OnlyOfficeService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OnlyOfficeConversionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.onlyoffice.url' => 'https://office.test',
            'services.onlyoffice.jwt_secret' => str_repeat('s', 40),
        ]);
        Http::preventStrayRequests();
    }

    public function test_conversion_downloads_the_result(): void
    {
        Http::fake([
            'https://office.test/ConvertService.ashx' => Http::response([
                'fileUrl' => 'https://office.test/result.pdf',
            ]),
            'https://office.test/result.pdf' => Http::response('%PDF-test'),
        ]);

        $this->assertSame('%PDF-test', app(OnlyOfficeService::class)->convertToPdf('https://app.test/source.docx', false));
        Http::assertSentCount(2);
    }

    public function test_conversion_error_preserves_code_and_job_key_without_exposing_signed_urls(): void
    {
        $key = null;
        Http::fake(function ($request) use (&$key) {
            $key = $request['key'];

            return Http::response(['error' => -4]);
        });

        try {
            app(OnlyOfficeService::class)->convertToPdf('https://app.test/source.docx?signature=private', false);
            $this->fail('Expected conversion failure.');
        } catch (OnlyOfficeUnavailableException $e) {
            $this->assertStringContainsString('HTTP 200, error -4, format pdf', $e->getMessage());
            $this->assertStringContainsString($key, $e->getMessage());
            $this->assertStringNotContainsString('private', $e->getMessage());
        }

        Http::assertSentCount(1);
    }
}
