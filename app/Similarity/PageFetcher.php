<?php

namespace App\Similarity;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Downloads a candidate web page and reduces it to plain text, defensively — the URL came
 * from a search engine, and the page from whoever runs that site.
 *
 *  - every URL, including each redirect hop, goes through UrlGuard, and the connection is
 *    pinned to the address the guard approved;
 *  - redirects are followed by hand (a redirect to an internal address is the classic way
 *    around a check that only looks at the first URL);
 *  - only HTML/plain text is read, with a hard size cap and a short timeout;
 *  - any failure at all is simply "no text" — one bad page must never fail a check.
 *
 * PDFs and other binary formats are skipped (no PDF text extractor is installed).
 */
final class PageFetcher
{
    public function __construct(private readonly UrlGuard $guard) {}

    public function fetch(string $url): ?string
    {
        try {
            return $this->download($url);
        } catch (Throwable) {
            return null;
        }
    }

    private function download(string $url): ?string
    {
        $maxBytes = (int) config('similarity.fetch.max_bytes');
        $maxRedirects = (int) config('similarity.fetch.max_redirects');

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            $target = $this->guard->inspect($url);

            $options = [
                'allow_redirects' => false,
                'on_headers' => function (ResponseInterface $response) use ($maxBytes) {
                    if ((int) $response->getHeaderLine('Content-Length') > $maxBytes) {
                        throw new \RuntimeException('Response too large.');
                    }
                },
            ];

            if ($target['ip'] !== null && defined('CURLOPT_RESOLVE')) {
                $options['curl'] = [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"]];
            }

            $response = Http::withOptions($options)
                ->withHeaders(['User-Agent' => (string) config('similarity.fetch.user_agent'), 'Accept' => 'text/html,text/plain;q=0.9'])
                ->connectTimeout(5)
                ->timeout((int) config('similarity.fetch.timeout'))
                ->get($url);

            if ($response->redirect()) {
                $location = $response->header('Location');

                if ($location === '') {
                    return null;
                }

                $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));

                continue;
            }

            return $response->successful() ? $this->text($response->header('Content-Type'), $response->body(), $maxBytes) : null;
        }

        return null;
    }

    private function text(string $contentType, string $body, int $maxBytes): ?string
    {
        $type = strtolower(trim(explode(';', $contentType)[0]));

        if (! in_array($type, ['text/html', 'application/xhtml+xml', 'text/plain'], true)) {
            return null;
        }

        $body = substr($body, 0, $maxBytes);

        if (! mb_check_encoding($body, 'UTF-8')) {
            $body = mb_convert_encoding($body, 'UTF-8', 'Windows-1252');
        }

        $paragraphs = $type === 'text/plain' ? HtmlText::split($body) : HtmlText::paragraphs($body);

        return $paragraphs === [] ? null : implode("\n", $paragraphs);
    }
}
