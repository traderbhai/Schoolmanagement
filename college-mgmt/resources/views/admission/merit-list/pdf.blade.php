<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Merit List - {{ $program->name }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
        h1 { font-size: 18px; text-align: center; margin-bottom: 4px; }
        .subtitle { text-align: center; color: #555; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; }
        th { background: #f5f5f5; font-weight: bold; }
        tr:nth-child(even) { background: #fafafa; }
        .badge-selected { color: #155724; background: #d4edda; padding: 2px 6px; border-radius: 4px; }
        .badge-waitlisted { color: #856404; background: #fff3cd; padding: 2px 6px; border-radius: 4px; }
        .badge-rejected { color: #721c24; background: #f8d7da; padding: 2px 6px; border-radius: 4px; }
        .badge-pending { color: #555; background: #e2e3e5; padding: 2px 6px; border-radius: 4px; }
        .footer { text-align: center; color: #aaa; font-size: 9px; margin-top: 16px; }
    </style>
</head>
<body>
    <h1>{{ config('app.name', 'College Management System') }}</h1>
    <div class="subtitle">
        <strong>Merit List - {{ $program->name }}</strong>
        @if($batch) | Batch: {{ $batch->name }} @endif<br>
        Generated: {{ now()->format('d M Y, H:i') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Rank</th>
                <th>Name</th>
                <th>Application #</th>
                @foreach($steps as $step)
                    <th>{{ $step->name ?? $step->typeLabel }} (%)</th>
                @endforeach
                <th>Academic</th>
                <th>Composite</th>
                <th>Decision</th>
            </tr>
        </thead>
        <tbody>
            @foreach($entries as $entry)
            <tr>
                <td>#{{ $entry->rank }}</td>
                <td>{{ $entry->applicant->user->name ?? 'Applicant name not recorded' }}</td>
                <td>{{ $entry->applicant->application_number ?? 'Application number pending' }}</td>
                @foreach($steps as $stepId => $step)
                @php $ss = ($entry->step_scores ?? [])[$stepId] ?? null; @endphp
                    <td>{{ $ss ? number_format($ss['percentage'] ?? 0, 1).'%' : 'Step score not recorded' }}</td>
                @endforeach
                <td>{{ $entry->academic_score !== null ? number_format($entry->academic_score, 1).'%' : 'Academic score not recorded' }}</td>
                <td>{{ number_format($entry->composite_score, 2) }}</td>
                <td><span class="badge-{{ $entry->decision }}">{{ $entry->decisionLabel }}</span></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Total: {{ $entries->count() }} applicants &nbsp;|&nbsp;
        Selected: {{ $entries->where('decision','selected')->count() }} &nbsp;|&nbsp;
        Waitlisted: {{ $entries->where('decision','waitlisted')->count() }} &nbsp;|&nbsp;
        Rejected: {{ $entries->where('decision','rejected')->count() }}
    </div>
</body>
</html>
