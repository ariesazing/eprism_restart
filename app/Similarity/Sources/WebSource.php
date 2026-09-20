<?php

namespace App\Similarity\Sources;

use App\Similarity\Candidate;
use App\Similarity\CheckContext;
use App\Similarity\PageFetcher;
use App\Similarity\SimilaritySource;
use App\Similarity\SourceUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The open web, through the organisation's own self-hosted SearXNG (a metasearch engine that
 * queries Google, Bing, DuckDuckGo and others on our behalf and returns JSON — no API key, no
 * per-query fee). Each planned phrase is searched as an exact phrase; the pages that come back
 * most often across different phrases (a page matching several unrelated passages is the likely
 * source) are then downloaded by PageFetcher and compared in full, up to a cap.
 *
 * What this can and can't see is exactly what those search engines index and a plain HTTP GET
 * can read: blogs, repositories, institutional pages. Pages behind a login (most of
 * ResearchGate), paywalled publishers, PDFs and Google Scholar are out of reach — see the class
 * docs on PageFetcher and SimilarityCheckRunner.
 *
 * SearXNG gets its results by scraping the upstream engines, which can CAPTCHA or rate-limit the
 * server's IP — and that failure is silent: an engine that's blocked just contributes nothing,
 * which would read as "no matches anywhere". One engine being blocked is normal (a fresh
 * install had DuckDuckGo CAPTCHA'd on its very first query, with dozens of results still coming
 * back from the rest), so per-engine reports aren't a usable signal. Instead a *canary* phrase
 * that is certainly all over the web is searched before and after the real queries: if even that
 * comes back empty, the engines are blocking us and the report must say so.
 */
final class WebSource implements SimilaritySource
{
    private const MAX_CONSECUTIVE_FAILURES = 3;

    private const MAX_RETRY_AFTER_SECONDS = 10;

    // A phrase that is certainly all over the web, so an empty result for it can only mean the
    // search itself isn't working (see the class doc).
    private const CANARY = 'the quick brown fox jumps over the lazy dog';

    private int $failures = 0;

    public function __construct(private readonly PageFetcher $fetcher) {}

    public function type(): string
    {
        return 'web';
    }

    public function label(): string
    {
        return 'The web';
    }

    public function isEnabled(): bool
    {
        return (bool) config('similarity.sources.web.enabled');
    }

    public function isConfigured(): bool
    {
        return filled(config('services.searxng.url'));
    }

    public function notConfiguredMessage(): string
    {
        return 'The web wasn\'t searched: no SearXNG server is configured (SEARXNG_URL).';
    }

    public function candidates(CheckContext $context): iterable
    {
        $this->failures = 0;

        foreach ($this->rankedPages($context) as $url => $page) {
            if ($context->expired()) {
                $context->warn('The time limit was reached before every web page could be checked, so these results are partial.');

                return;
            }

            $text = $this->fetcher->fetch($url);

            if ($text === null) {
                continue;
            }

            yield new Candidate(
                'web',
                $page['title'] !== '' ? $page['title'] : $this->host($url),
                $url,
                $text,
                ['host' => $this->host($url)],
            );
        }
    }

    /**
     * Searches every planned phrase and returns the pages to download, most-frequently
     * returned first.
     *
     * @return array<string, array{title: string, hits: int, order: int}>
     */
    private function rankedPages(CheckContext $context): array
    {
        $pages = [];
        $ownHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $perQuery = (int) config('similarity.sources.web.results_per_query');

        try {
            if (! $this->searchWorks()) {
                $context->warn('The web search returned nothing even for a phrase that is certainly online, so its search engines are probably blocking this server. The web was not checked.');

                return [];
            }
        } catch (SourceUnavailableException $e) {
            $context->warn($e->getMessage());

            return [];
        }

        $searched = 0;

        foreach ($context->queries as $phrase) {
            if ($context->expired()) {
                $context->warn('The time limit was reached before every phrase could be searched on the web, so these results are partial.');
                break;
            }

            $this->pause();

            try {
                $payload = $this->search($phrase);
            } catch (SourceUnavailableException $e) {
                $context->warn($e->getMessage());
                break;
            }

            $searched++;

            foreach (array_slice(array_values(array_filter($payload['results'] ?? [], 'is_array')), 0, $perQuery) as $result) {
                $url = $result['url'] ?? null;

                if (! is_string($url) || $this->host($url) === '' || $this->host($url) === $ownHost) {
                    continue;
                }

                $pages[$url] ??= ['title' => trim(strip_tags((string) ($result['title'] ?? ''))), 'hits' => 0, 'order' => count($pages)];
                $pages[$url]['hits']++;
            }
        }

        // Engines can start blocking partway through a long run (Google CAPTCHAs a busy IP after
        // a few dozen searches), leaving the later queries silently empty.
        if ($searched > 0 && ! $context->expired()) {
            $this->pause();

            try {
                if (! $this->searchWorks()) {
                    $context->warn('The web search stopped returning results partway through the check, so its search engines are probably rate-limiting this server. The results are incomplete.');
                }
            } catch (SourceUnavailableException $e) {
                $context->warn($e->getMessage());
            }
        }

        uasort($pages, fn (array $a, array $b) => [$b['hits'], $a['order']] <=> [$a['hits'], $b['order']]);

        return array_slice($pages, 0, (int) config('similarity.sources.web.max_pages'), true);
    }

    /**
     * Whether the search is actually returning results: the canary phrase must find something.
     * Strict, so SearXNG being down is reported as *that* — not misread as "the engines are
     * blocking us", which is what an empty answer from a canary would otherwise imply.
     *
     * @throws SourceUnavailableException
     */
    private function searchWorks(): bool
    {
        return array_filter($this->search(self::CANARY, strict: true)['results'] ?? [], 'is_array') !== [];
    }

    /**
     * @param  bool  $strict  a transport failure or server error throws immediately instead of
     *                        counting as one more blip to tolerate (a search for a real phrase can
     *                        shrug off a lone hiccup; the canary can't — it's the health check)
     * @return array<string, mixed>
     *
     * @throws SourceUnavailableException
     */
    private function search(string $phrase, bool $strict = false): array
    {
        return $this->getJson(fn () => Http::acceptJson()
            ->timeout(20)
            ->get(rtrim((string) config('services.searxng.url'), '/').'/search', [
                'q' => '"'.$phrase.'"',
                'format' => 'json',
                'language' => (string) config('services.searxng.language', 'all'),
                'safesearch' => 0,
            ]), $strict);
    }

    /**
     * @param  \Closure(): Response  $send
     * @return array<string, mixed>
     *
     * @throws SourceUnavailableException
     */
    private function getJson(\Closure $send, bool $strict = false): array
    {
        $response = null;

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $response = $send();
            } catch (ConnectionException) {
                return $this->recordFailure($strict);
            }

            if ($response->status() === 429 && $attempt === 0) {
                $this->sleepMs(max(min((int) $response->header('Retry-After'), self::MAX_RETRY_AFTER_SECONDS) * 1000, $this->delayMs() * 2));

                continue;
            }

            break;
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new SourceUnavailableException('SearXNG refused the search request. Check that `json` is listed under `search.formats` in its settings.yml, and that its limiter isn\'t blocking this server.');
        }

        if ($response->status() === 429) {
            throw new SourceUnavailableException('SearXNG\'s rate limit was reached, so web results are partial.');
        }

        if (! $response->successful()) {
            return $this->recordFailure($strict);
        }

        $this->failures = 0;

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SourceUnavailableException
     */
    private function recordFailure(bool $strict): array
    {
        if ($strict || ++$this->failures >= self::MAX_CONSECUTIVE_FAILURES) {
            throw new SourceUnavailableException('SearXNG isn\'t responding, so web results are partial.');
        }

        return [];
    }

    /** Sleeps between searches so a normal run doesn't trip the upstream engines' rate limits. */
    private function pause(): void
    {
        $this->sleepMs($this->delayMs());
    }

    private function delayMs(): int
    {
        return (int) config('similarity.sources.web.delay_ms', 0);
    }

    private function sleepMs(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }

    private function host(string $url): string
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST));
    }
}
