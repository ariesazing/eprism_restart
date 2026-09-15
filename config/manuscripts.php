<?php

return [

    // The interpreter running scripts/manuscript.py (structural validation, proposal-to-
    // completed migration, PDF merging, PDF encryption). MANUSCRIPT_PYTHON wins if set —
    // otherwise fall back to the project's own .venv on Windows if it exists, or whatever
    // "python3" resolves to on PATH. See README's "Setup" section for the reasoning (a PATH
    // change only takes effect in processes started after it was made).
    'python' => env('MANUSCRIPT_PYTHON') ?: (
        PHP_OS_FAMILY === 'Windows' && file_exists(base_path('.venv/Scripts/python.exe'))
            ? base_path('.venv/Scripts/python.exe')
            : 'python3'
    ),

    // Wall-clock limit for one scripts/manuscript.py invocation (see ManuscriptProcessor).
    'process_timeout' => 300,

    // How long a stuck editing session (ManuscriptController::config() optimistically set
    // session_open=true, but the editor never actually finished loading) is retried before
    // giving up — see ManuscriptService::recoverStuckSessions().
    'save_timeout_minutes' => 10,

    // Caps a manuscript .docx downloaded from Document Server's own save callback
    // (ManuscriptController::callback()) — same 50MB ceiling as an uploaded PDF attachment
    // (see ResearchSubmissionController's own 'max:51200' validation rule).
    'max_docx_bytes' => 50 * 1024 * 1024,

];
