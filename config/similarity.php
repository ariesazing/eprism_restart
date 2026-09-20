<?php

/*
|--------------------------------------------------------------------------
| Similarity checker
|--------------------------------------------------------------------------
|
| Tuning for App\Similarity — the in-house similarity screening that searches the
| open web (through self-hosted SearXNG) for passages from a researcher's chapters.
| The SearXNG address lives in config/services.php ('searxng'); everything else
| about how a check behaves lives here.
|
*/

return [

    // Words per fingerprint window. A passage counts as "copied" once at least this many
    // words in a row appear, in order, in both texts — lower catches more paraphrase but
    // drowns in common phrases, higher misses lightly edited copying.
    'shingle_size' => 6,

    // A matched run shorter than this is coincidence (a stock phrase), not copying.
    'min_span_words' => 8,

    // Two matched runs in the same paragraph separated by at most this many unmatched
    // words are merged into one highlight, so a single swapped word doesn't split a copied
    // sentence into two fragments.
    'merge_gap' => 2,

    // Not worth checking (or spending external API calls on) anything shorter than this.
    'min_document_words' => 50,

    // A source that matches fewer words than this is noise and isn't listed.
    'min_source_words' => 10,

    // How many sources a report lists at most, best match first.
    'max_sources' => 40,

    // A check that hasn't finished after this many seconds of source lookups stops asking
    // for more and reports what it has (flagged as partial). Keep below RunSimilarityCheck's
    // timeout, which itself must stay under the queue's retry_after (DB_QUEUE_RETRY_AFTER, 900).
    'time_budget_seconds' => 480,

    // Chapters whose key matches any of these substrings are left out of the check — a
    // reference list matching other papers' reference lists isn't similarity worth flagging.
    'excluded_section_keys' => ['reference', 'bibliograph'],

    // How external lookups pick what to search for: evenly spaced distinctive sentences,
    // each trimmed to a `words`-word exact phrase.
    'queries' => [
        'max' => 24,
        'words' => 10,
        'min_sentence_words' => 14,
    ],

    'sources' => [
        // The open web, through self-hosted SearXNG (services.searxng.url) — see WebSource.
        'web' => [
            'enabled' => env('SIMILARITY_WEB_ENABLED', true),
            'results_per_query' => 10,
            // Candidate pages actually downloaded and compared, most-often-returned first.
            'max_pages' => 15,
            // Pause between searches, ms. SearXNG itself is unmetered, but it scrapes Google,
            // Bing, DuckDuckGo and the like from this network's IP — hammering them is what gets
            // that IP CAPTCHA'd. Keep this politely slow.
            'delay_ms' => 1000,
        ],
    ],

    // Fetching a candidate web page (see PageFetcher / UrlGuard).
    'fetch' => [
        'timeout' => 10,
        'max_bytes' => 2_000_000,
        'max_redirects' => 3,
        'user_agent' => 'ePrismSimilarityChecker/1.0',
    ],

];
