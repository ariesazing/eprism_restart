<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: sans-serif; color: #1e293b; line-height: 1.6;">
    <p>Dear Proponent,</p>

    <p>Your research <strong>"{{ $submission->title }}"</strong> ({{ $submission->reference_code }}) has been reviewed and requires revisions before it can proceed.</p>

    @if ($submission->admin_notes)
        <p><strong>Reviewer feedback:</strong></p>
        <blockquote style="border-left: 3px solid #cbd5e1; margin: 0; padding-left: 12px; color: #334155; white-space: pre-line;">{{ $submission->admin_notes }}</blockquote>
    @endif

    <p>Please log in to E-PRISM to review the feedback in detail and resubmit your revised research.</p>

    <p>
        <a href="{{ route('submissions.show', $submission) }}">View this submission in E-PRISM</a>
    </p>

    <p style="color: #64748b; font-size: 12px;">This is an automated notice from E-PRISM. Please do not reply to this email.</p>
</body>
</html>
