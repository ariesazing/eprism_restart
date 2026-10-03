<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: sans-serif; color: #1e293b; line-height: 1.6;">
    <p>Dear Proponent,</p>
    <p>Your research <strong>{{ $submission->title }}</strong> ({{ $submission->reference_code }}) has been rejected.</p>
    <p><strong>Reason for rejection:</strong></p>
    <blockquote style="white-space: pre-line;">{{ $submission->admin_notes }}</blockquote>
    <p>The submission remains available as a read-only record. It cannot be edited or resubmitted.</p>
    <p><a href="{{ route('submissions.show', $submission) }}">View your submission and feedback</a></p>
    <p>This is an automated notice from e-PRISM.</p>
</body>
</html>
