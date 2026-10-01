<?php

/*
|--------------------------------------------------------------------------
| Similarity checker
|--------------------------------------------------------------------------
|
| Checks matching wording against approved documents in the system repository.
| SourceRegistry registers RepositorySource alone; no web search is performed.
|
*/

return [

    'pdftotext_binary' => env('PDFTOTEXT_BINARY', 'pdftotext'),

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

    // Minimum amount of research text needed for a useful comparison.
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

];
