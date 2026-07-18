<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; padding: 24px 36px; }
        .header { text-align: center; border-bottom: 2px solid #1d4ed8; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { font-size: 18px; color: #1d4ed8; }
        .header p { font-size: 11px; color: #666; margin-top: 3px; }
        .meta { display: flex; justify-content: space-between; font-size: 11px; color: #666; margin-bottom: 20px; }
        h2 { font-size: 13px; color: #1e3a8a; border-bottom: 1px solid #bfdbfe; padding-bottom: 4px; margin: 18px 0 10px; }
        .stats-row { display: flex; gap: 12px; margin-bottom: 18px; }
        .stat-box { flex: 1; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; text-align: center; }
        .stat-box .num { font-size: 22px; font-weight: bold; color: #1d4ed8; }
        .stat-box .lbl { font-size: 10px; color: #666; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 11px; }
        th { background: #1d4ed8; color: #fff; padding: 6px 8px; text-align: left; }
        td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; }
        tr:nth-child(even) td { background: #f8fafc; }
        .badge-ok   { background: #d1fae5; color: #065f46; padding: 2px 6px; border-radius: 3px; font-size: 10px; }
        .badge-warn { background: #fef3c7; color: #92400e; padding: 2px 6px; border-radius: 3px; font-size: 10px; }
        .footer { margin-top: 30px; font-size: 10px; color: #aaa; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name', 'Institute') }}</h1>
        <p>Admission Analytics Report &mdash; Generated {{ now()->format('d M Y, h:i A') }}</p>
    </div>

    <div class="meta">
        <span>Academic Year: {{ now()->year }}&ndash;{{ now()->addYear()->year }}</span>
        <span>Printed by: {{ auth()->user()->name ?? 'System' }}</span>
    </div>

    {{-- Summary Stats --}}
    <h2>Summary</h2>
    <div class="stats-row">
        <div class="stat-box">
            <div class="num">{{ number_format($totalLeads) }}</div>
            <div class="lbl">Total Leads</div>
        </div>
        <div class="stat-box">
            <div class="num">{{ number_format($totalApplicants) }}</div>
            <div class="lbl">Applications</div>
        </div>
        <div class="stat-box">
            <div class="num">{{ number_format($selected) }}</div>
            <div class="lbl">Selected</div>
        </div>
        <div class="stat-box">
            <div class="num">{{ number_format($enrolled) }}</div>
            <div class="lbl">Enrolled</div>
        </div>
    </div>

    {{-- Program-wise Breakdown --}}
    <h2>Program-wise Breakdown</h2>
    <table>
        <thead>
            <tr>
                <th scope="col">Program</th>
                <th scope="col">Code</th>
                <th scope="col" style="text-align:right">Applied</th>
                <th scope="col" style="text-align:right">Shortlisted</th>
                <th scope="col" style="text-align:right">Selected</th>
                <th scope="col" style="text-align:right">Rejected</th>
            </tr>
        </thead>
        <tbody>
            @foreach($programStats as $p)
            <tr>
                <td>{{ $p['name'] }}</td>
                <td>{{ $p['code'] }}</td>
                <td style="text-align:right">{{ $p['total'] }}</td>
                <td style="text-align:right">{{ $p['shortlisted'] }}</td>
                <td style="text-align:right">{{ $p['selected'] }}</td>
                <td style="text-align:right">{{ $p['rejected'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Lead Source Performance --}}
    <h2>Lead Source Performance</h2>
    <table>
        <thead>
            <tr>
                <th scope="col">Source</th>
                <th scope="col" style="text-align:right">Total Leads</th>
                <th scope="col" style="text-align:right">Converted</th>
                <th scope="col" style="text-align:right">Conversion %</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sourceStats as $s)
            <tr>
                <td>{{ $s['source'] }}</td>
                <td style="text-align:right">{{ $s['total'] }}</td>
                <td style="text-align:right">{{ $s['converted'] }}</td>
                <td style="text-align:right">{{ $s['conversion_pct'] }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- AICTE Category Compliance --}}
    <h2>AICTE Category Compliance</h2>
    <table>
        <thead>
            <tr>
                <th scope="col">Category</th>
                <th scope="col" style="text-align:right">Mandate %</th>
                <th scope="col" style="text-align:right">Mandate Seats</th>
                <th scope="col" style="text-align:right">Filled</th>
                <th scope="col" style="text-align:right">Fill %</th>
                <th scope="col">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($categoryCompliance as $row)
            <tr>
                <td>{{ $row['category'] }}</td>
                <td style="text-align:right">{{ $row['mandate_pct'] }}%</td>
                <td style="text-align:right">{{ $row['mandate_seats'] }}</td>
                <td style="text-align:right">{{ $row['filled'] }}</td>
                <td style="text-align:right">{{ $row['fill_pct'] }}%</td>
                <td>
                    @if($row['compliant'])
                        <span class="badge-ok">Compliant</span>
                    @else
                        <span class="badge-warn">Below Target</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        This is a system-generated report. &mdash; {{ config('app.name') }}
        &mdash; For AICTE compliance reference only.
    </div>
</body>
</html>
