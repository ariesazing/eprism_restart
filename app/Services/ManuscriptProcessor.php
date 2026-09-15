<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * The PHP bridge to scripts/manuscript.py — a private, argument-free (JSON on stdin, JSON on
 * stdout) worker that does everything this app needs a real docx/PDF library for that has no
 * good PHP equivalent: structural validation against a template, proposal-to-completed content
 * migration, PDF attachment merging, and PDF encryption (see that script's own module doc for
 * why each operation needs to run there rather than in PHP). Every operation shares the same
 * shape: a JSON object of arguments in, a JSON object of results out, one non-zero exit code
 * (with the real error on stderr) for any failure — mirrors OnlyOfficeService's own "every
 * failure mode normalized into one exception type" shape, just for a subprocess instead of an
 * HTTP call.
 */
class ManuscriptProcessor
{
    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function run(string $operation, array $args): array
    {
        $result = Process::path(base_path())
            ->timeout((int) config('manuscripts.process_timeout', 300))
            ->input(json_encode($args))
            ->run([config('manuscripts.python'), 'scripts/manuscript.py', $operation]);

        if (! $result->successful()) {
            throw new RuntimeException(
                "The manuscript worker failed running \"{$operation}\": ".trim($result->errorOutput() ?: $result->output())
            );
        }

        $decoded = json_decode($result->output(), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("The manuscript worker returned an invalid response running \"{$operation}\".");
        }

        return $decoded;
    }
}
