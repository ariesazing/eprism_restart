<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: sans-serif; color: #1e293b; line-height: 1.6;">
    <p>Dear Proponent,</p>

    @if ($isFinal)
        <p>Congratulations! Your research <strong>"{{ $submission->title }}"</strong> ({{ $submission->reference_code }}) has been reviewed and <strong>approved</strong>, completing the E-PRISM review process.</p>
    @else
        <p>Congratulations! Your research proposal <strong>"{{ $submission->title }}"</strong> ({{ $submission->reference_code }}) has been <strong>approved</strong> and promoted to the completed-research phase. Please prepare and submit your completed research documentation.</p>
    @endif

    <p>
        <a href="{{ route('submissions.show', $submission) }}">View this submission in E-PRISM</a>
    </p>

    <p style="color: #64748b; font-size: 12px;">This is an automated notice from E-PRISM. Please do not reply to this email.</p>
</body>
</html>
