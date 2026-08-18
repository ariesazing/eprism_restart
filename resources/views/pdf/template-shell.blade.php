<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 100px 60px 70px 60px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; }
        header { position: fixed; top: -80px; left: 0px; right: 0px; height: 70px; text-align: center; border-bottom: 2px solid #b91c1c; padding-bottom: 6px; }
        header p { margin: 0; font-size: 10px; color: #334155; }
        header strong { font-size: 12px; color: #b91c1c; letter-spacing: 0.5px; }
        footer { position: fixed; bottom: -50px; left: 0px; right: 0px; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #cbd5e1; padding-top: 6px; }
        footer p { margin: 0; }
        p { margin: 0 0 8px 0; }
        strong { color: #0f172a; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0; }
        table td, table th { border: 1px solid #cbd5e1; padding: 5px 6px; font-size: 9px; text-align: left; vertical-align: top; }
        ul, ol { margin: 0 0 8px 0; padding-left: 20px; }
        img { max-width: 100%; }
    </style>
</head>
<body>
    <header>
        {!! $headerHtml !!}
    </header>
    <footer>
        {!! $footerHtml !!}
    </footer>

    {!! $bodyHtml !!}
</body>
</html>
