<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Term-End Summary Report — {{ $selectedTerm->name ?? 'All Terms' }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #111;
            background: #fff;
            padding: 30px;
        }
        h1 {
            font-size: 20px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 4px;
        }
        .report-meta {
            text-align: center;
            font-size: 11px;
            color: #555;
            margin-bottom: 24px;
        }
        h2 {
            font-size: 14px;
            font-weight: bold;
            background: #2c3e50;
            color: #fff;
            padding: 6px 10px;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 11px;
        }
        th {
            background: #ecf0f1;
            border: 1px solid #bbb;
            padding: 5px 8px;
            text-align: left;
            font-weight: bold;
        }
        td {
            border: 1px solid #ccc;
            padding: 5px 8px;
            vertical-align: top;
        }
        .stats-table td { text-align: center; }
        .stats-table th { text-align: center; }
        .section-label {
            font-weight: bold;
            font-size: 11px;
            margin: 10px 0 4px 0;
            color: #333;
        }
        .kpi-row {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        .kpi-cell {
            display: table-cell;
            width: 25%;
            border: 1px solid #ccc;
            padding: 8px;
            text-align: center;
        }
        .kpi-value {
            font-size: 16px;
            font-weight: bold;
        }
        .kpi-label {
            font-size: 10px;
            color: #666;
        }
        .top-students-header {
            font-weight: bold;
            margin-top: 10px;
            margin-bottom: 4px;
        }
        .footer {
            margin-top: 40px;
            border-top: 1px solid #ccc;
            padding-top: 10px;
            font-size: 10px;
            color: #888;
            text-align: center;
        }
        .page-break { page-break-after: always; }
        .empty-note { color: #888; font-style: italic; }
    </style>
</head>
<body>

    <h1>Term-End Summary Report</h1>
    <div class="report-meta">
        Term: <strong>{{ $selectedTerm->name ?? 'All Terms' }}</strong> &nbsp;|&nbsp;
        Generated: {{ now()->format('d M Y, h:i A') }}
    </div>

    @forelse($stats as $programStat)
        <h2>{{ $programStat->program->name ?? '—' }}</h2>

        {{-- KPI table --}}
        <table class="stats-table">
            <thead>
                <tr>
                    <th>Enrollment</th>
                    <th>Avg Marks</th>
                    <th>Pass Rate %</th>
                    <th>Attendance %</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $programStat->enrollment ?? 0 }}</td>
                    <td>{{ isset($programStat->avg_marks) ? number_format($programStat->avg_marks, 1) : '—' }}</td>
                    <td>{{ isset($programStat->pass_rate) ? number_format($programStat->pass_rate, 1) . '%' : '—' }}</td>
                    <td>{{ isset($programStat->att_pct) ? number_format($programStat->att_pct, 1) . '%' : '—' }}</td>
                </tr>
            </tbody>
        </table>

        {{-- Top students --}}
        <div class="top-students-header">Top 5 Students</div>
        @if($programStat->top_students && $programStat->top_students->isNotEmpty())
        <table>
            <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th>Student Name</th>
                    <th>Enrollment Number</th>
                </tr>
            </thead>
            <tbody>
                @foreach($programStat->top_students->take(5) as $rank => $topStudent)
                <tr>
                    <td>{{ $rank + 1 }}</td>
                    <td>{{ $topStudent->user->name ?? $topStudent->name ?? '—' }}</td>
                    <td>{{ $topStudent->enrollment_number ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
            <p class="empty-note">No top student data available.</p>
        @endif

    @empty
        <p class="empty-note" style="text-align:center;margin-top:40px">
            No program summary data available for this term.
        </p>
    @endforelse

    <div class="footer">
        This report is generated automatically by the College Management System.
        It is confidential and intended for internal academic records only.
        &nbsp;|&nbsp; Printed on {{ now()->format('d M Y') }}
    </div>

</body>
</html>
