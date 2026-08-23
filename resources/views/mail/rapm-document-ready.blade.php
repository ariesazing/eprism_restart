<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: sans-serif; color: #1e293b; line-height: 1.6;">
    <p>Hello,</p>

    <p>The <strong>{{ $documentLabel }}</strong> for <strong>"{{ $submission->title }}"</strong> ({{ $submission->reference_code }}) is now ready.</p>

    <p>
        <a href="{{ $downloadUrl }}">Download the {{ $documentLabel }}</a>
    </p>

    <p style="color: #64748b; font-size: 12px;">This is an automated notice from E-PRISM. Please do not reply to this email.</p>
</body>
</html>
