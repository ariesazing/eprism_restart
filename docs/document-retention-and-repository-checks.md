# Document retention, window emails, and repository similarity

Apply the migrations before starting the new workers (`php artisan migrate --force` in production), rebuild the application image, and restart the queue workers. The production image includes Poppler's `pdftotext`. On a local installation, install Poppler and set `PDFTOTEXT_BINARY` to the executable's full path if it is not on PATH. This is needed for existing legacy PDF snapshots without captured text; new snapshots store encrypted comparison text.

## Automatic retention

After final research approval, cleanup runs through the queue. For complete manuscripts it waits until the approved final PDF exists. It preserves the approved proposal, final research documents, current attachments, and every version/review/comment record. Superseded snapshot PDFs and frozen manuscript source/template/attachment copies are deleted; the corresponding history rows are marked with `files_pruned_at`. Removed versions have no download links, and direct downloads return HTTP 410.

Cleanup is idempotent and also scheduled daily for existing approved research. It does not prune drafts, research under review, processing versions, or manuscripts whose final PDF is still missing. Failed cleanup jobs use the normal queue retries. Files are deleted individually within the submission's own storage paths; active and retained-version file references are protected.

## Submission-window notifications

Manual changes and scheduled openings/closings email registered users and all recorded proponent email addresses, including proponents without accounts. Addresses are deduplicated without regard to case for each transition, and each recipient gets a separate email. Initial setup establishes the current state without sending an old announcement.

The mail queue and scheduler must be running. Production supervisord already starts both; locally run `php artisan schedule:work` alongside the queue worker. Configure a real mail transport to deliver notifications; a log mailer only writes them to a log.

## Repository-only similarity

New checks compare against saved approved proposals and approved completed research across the system repository. The checked research's own versions and all unapproved drafts are excluded. Manuscripts use the approved frozen DOCX; legacy documents use the approved snapshot's captured text or local PDF extraction. They never use a promoted proposal's current working draft.

No new check contacts SearXNG or public search engines, regardless of old web-search environment settings. Historical web reports remain readable and are labelled as historical. Source titles identify matches without granting access to other researchers' documents. Missing/unreadable sources and time-limited scans produce coverage warnings; an empty repository does not establish originality. Scanned PDFs without a text layer need OCR outside this checker.

Queue reservation time must exceed job timeouts. Queue defaults are now 900 seconds; an explicit `DB_QUEUE_RETRY_AFTER` or `REDIS_QUEUE_RETRY_AFTER` override must also exceed the longest job (720 seconds).
