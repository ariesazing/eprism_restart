<?php

namespace App\Services;

use App\Exceptions\GrammarCheckUnavailableException;
use Illuminate\Support\Facades\Http;
use Throwable;

class GrammarCheckService
{
    /**
     * Run a self-hosted LanguageTool grammar check against the given plain text.
     *
     * @return array<int, array<string, mixed>> the LanguageTool "matches" array (one entry per issue found)
     *
     * @throws GrammarCheckUnavailableException when LanguageTool isn't configured or the request fails
     */
    public function check(string $text): array
    {
        $url = config('services.languagetool.url');

        if (blank($url)) {
            throw new GrammarCheckUnavailableException('LANGUAGETOOL_URL is not configured.');
        }

        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post(rtrim($url, '/').'/v2/check', [
                    'text' => $text,
                    'language' => config('services.languagetool.language', 'en-US'),
                ]);
        } catch (Throwable $e) {
            throw new GrammarCheckUnavailableException('Unable to reach the LanguageTool server.', previous: $e);
        }

        if ($response->failed()) {
            throw new GrammarCheckUnavailableException('LanguageTool server returned an error response.');
        }

        return $response->json('matches') ?? [];
    }
}
