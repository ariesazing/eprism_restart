<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 90px 60px 70px 60px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; }
        header { position: fixed; top: -70px; left: 0px; right: 0px; height: 60px; text-align: center; border-bottom: 2px solid #b91c1c; padding-bottom: 8px; }
        header .agency { font-size: 13px; font-weight: bold; color: #b91c1c; letter-spacing: 1px; }
        header .subtitle { font-size: 9px; color: #64748b; }
        footer { position: fixed; bottom: -50px; left: 0px; right: 0px; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #cbd5e1; padding-top: 6px; }
        .cover { text-align: center; margin-top: 40px; }
        .cover .badge { display: inline-block; padding: 4px 14px; border: 1px solid #b91c1c; color: #b91c1c; font-size: 9px; letter-spacing: 2px; text-transform: uppercase; }
        .cover h1 { font-size: 20px; margin: 24px 0 8px 0; color: #0f172a; }
        .cover .meta { font-size: 10px; color: #64748b; margin-bottom: 24px; }
        .proponents-table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        .proponents-table th, .proponents-table td { border: 1px solid #cbd5e1; padding: 6px 8px; font-size: 9.5px; text-align: left; vertical-align: top; }
        .proponents-table th { background: #f1f5f9; color: #334155; }
        .photo-cell img { width: 60px; height: 60px; object-fit: cover; }
        .section { margin-top: 22px; page-break-inside: avoid; }
        .section h2 { font-size: 13px; color: #b91c1c; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; margin-bottom: 8px; }
        .section .body { font-size: 10.5px; line-height: 1.6; text-align: justify; }
        .section .body p { margin: 0 0 8px 0; }
        .section .empty { color: #94a3b8; font-style: italic; }
        table.data-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.data-table th, table.data-table td { border: 1px solid #cbd5e1; padding: 5px 6px; font-size: 9px; text-align: left; vertical-align: top; }
        table.data-table th { background: #f1f5f9; color: #334155; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <header>
        <div class="agency">Department of Education &middot; Research Management Portal</div>
        <div class="subtitle">Standardized Research Submission Document</div>
    </header>
    <footer>
        Generated {{ now()->format('F j, Y g:i A') }} &middot; {{ $template->label }} &middot; Confidential — for review purposes only
    </footer>

    <div class="cover">
        <span class="badge">{{ $template->label }}</span>
        <h1>{{ $submission->title }}</h1>
        <div class="meta">
            {{ ucfirst($submission->research_type) }} Research &middot; {{ ucfirst($submission->classification) }}
            &middot; {{ $submission->organizational_unit }}
            @if ($submission->school_id)
                &middot; School ID: {{ $submission->school_id }}
            @endif
        </div>
    </div>

    <table class="proponents-table">
        <thead>
            <tr>
                <th style="width: 70px;">Photo</th>
                <th>Proponent</th>
                <th>Position</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($proponents as $proponent)
                <tr>
                    <td class="photo-cell">
                        @if ($photo = $photoDataUri($proponent))
                            <img src="{{ $photo }}" alt="">
                        @endif
                    </td>
                    <td>{{ trim($proponent->last_name.', '.$proponent->first_name.' '.($proponent->middle_initial ? $proponent->middle_initial.'.' : '')) }}{{ $proponent->is_lead ? ' (Lead)' : '' }}</td>
                    <td>{{ $proponent->position }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="empty">No proponents on record.</td></tr>
            @endforelse
        </tbody>
    </table>

    @foreach ($template->sections as $definition)
        @php $section = $sections->get($definition->key); @endphp
        <div class="section">
            <h2>{{ $definition->label }}</h2>

            @if ($definition->type === 'table')
                @php $rows = $section?->tableRows() ?? []; @endphp
                @if (count($rows))
                    <table class="data-table">
                        <thead>
                            <tr>
                                @foreach ($definition->columns as $column)
                                    <th>{{ $column['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    @foreach ($definition->columns as $column)
                                        <td>{{ $row[$column['key']] ?? '' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="empty">No entries provided.</div>
                @endif
            @else
                <div class="body">
                    @if ($section && $section->content)
                        {!! $section->content !!}
                    @else
                        <span class="empty">No content provided.</span>
                    @endif
                </div>
            @endif
        </div>
    @endforeach
</body>
</html>
