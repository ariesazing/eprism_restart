<?php

namespace Tests\Feature;

use App\Exceptions\OnlyOfficeUnavailableException;
use App\Services\OnlyOfficeService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
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
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'ConvertService')) {
                return false;
            }
            $key = new Key(config('services.onlyoffice.jwt_secret'), 'HS256');
            $body = $request->data();
            $token = $body['token'];
            unset($body['token']);
            $this->assertEquals($body, (array) JWT::decode($token, $key));
            $header = substr($request->header('Authorization')[0], 7);
            $this->assertEquals($body, (array) JWT::decode($header, $key)->payload);

            return true;
        });
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
