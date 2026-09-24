<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'languagetool' => [
        'url' => env('LANGUAGETOOL_URL'),
        'language' => env('LANGUAGETOOL_LANGUAGE', 'en-US'),
    ],

    // Self-hosted SearXNG, the similarity checker's web search (see config/similarity.php and
    // App\Similarity\Sources\WebSource). Base URL only, e.g. http://searxng:8080 — no API key.
    // Left unset, the web isn't searched and the report says so. Its settings.yml must list
    // `json` under search.formats (see docker/searxng/settings.yml).
    'searxng' => [
        'url' => env('SEARXNG_URL'),
        // 'all' searches in every language, right for manuscripts that mix English and Filipino.
        'language' => env('SEARXNG_LANGUAGE', 'all'),
    ],

    'onlyoffice' => [
        'enabled' => env('ONLYOFFICE_ENABLED', false),
        'url' => env('ONLYOFFICE_URL'),
        'jwt_secret' => env('ONLYOFFICE_JWT_SECRET'),
        // Base URL Document Server should use to call back into this app — only needed
        // when DS can't reach this app at its normal APP_URL (e.g. DS running in a Docker
        // container locally, where "localhost" means the container itself, not the host;
        // or DS and this app sitting in different internal networks in production).
        // Defaults to APP_URL when unset.
        'callback_base_url' => env('ONLYOFFICE_CALLBACK_BASE_URL'),
        // The qpdf executable used to normalize Document Server's own PDF output for FPDI (see
        // OnlyOfficeService::normalizePdfForFpdi()). Defaults to relying on PATH, which is what
        // the production Docker image's apt-installed qpdf lands on — but a Windows dev PATH
        // change only takes effect in processes started *after* it was made (this app's PHP
        // process, `php artisan serve` included, keeps whatever PATH it inherited at startup,
        // not a live view of the registry), which has bitten local dev more than once. Set
        // QPDF_BINARY to the full .exe path as a workaround that doesn't depend on restarting
        // the right terminal/IDE process.
        'qpdf_binary' => env('QPDF_BINARY', 'qpdf'),
    ],

];
