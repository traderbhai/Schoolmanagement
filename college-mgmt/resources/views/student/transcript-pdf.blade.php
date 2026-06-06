<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Official Transcript — {{ $student->user->name ?? 'Student' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #1a1a1a; }
        .header { text-align: center; padding: 20px 0 10px; border-bottom: 2px solid #1e40af; margin-bottom: 16px; }
        .header .institution { font-size: 18px; font-weight: 700; color: #1e40af; letter-spacing: 0.5px; }
        .header .subtitle { font-size: 11px; color: #64748b; margin-top: 2px; }
        .header .doc-title { font-size: 13px; font-weight: 700; margin-top: 8px; text-transform: uppercase; letter-spacing: 1px; color: #1e40af; }

        .info-grid { display: table; width: 100%; margin-bottom: 16px; border: 1px solid #e2e8f0; }
        .info-row { display: table-row; }
        .info-cell { display: table-cell; padding: 6px 10px; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; width: 50%; }
        .info-label { font-size: 8px; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; }
        .info-value { font-weight: 700; font-size: 10px; margin-top: 1px; }

        .semester-block { margin-bottom: 16px; page-break-inside: avoid; }
        .semester-title { background: #1e40af; color: white; padding: 5px 10px; font-weight: 700; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; padding: 5px 8px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.3px; color: #475569; border: 1px solid #e2e8f0; }
        td { padding: 5px 8px; border: 1px solid #e2e8f0; font-size: 9px; vertical-align: middle; }
        tr:nth-child(even) td { background: #f8fafc; }
        .grade-o  { color: #16a34a; font-weight: 700; }
        .grade-a  { color: #2563eb; font-weight: 700; }
        .grade-b  { color: #d97706; font-weight: 700; }
        .grade-f  { color: #dc2626; font-weight: 700; }

        .sgpa-row td { background: #f0f9ff; font-weight: 700; }
        .summary-box { border: 2px solid #1e40af; border-radius: 4px; padding: 12px 16px; margin-top: 16px; display: inline-block; }
        .summary-label { font-size: 9px; text-transform: uppercase; color: #64748b; }
        .summary-value { font-size: 22px; font-weight: 900; color: #1e40af; }
        .footer { margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 12px; display: table; width: 100%; }
        .footer-cell { display: table-cell; width: 33%; text-align: center; font-size: 8px; color: #94a3b8; }
        .sig-line { border-top: 1px solid #64748b; width: 120px; margin: 30px auto 4px; }
        .watermark { color: #e2e8f0; font-size: 60px; font-weight: 900; position: fixed; top: 40%; left: 15%; transform: rotate(-35deg); opacity: 0.15; z-index: -1; letter-spacing: 4px; }
    </style>
</head>
<body>

<div class="watermark">OFFICIAL</div>

{{-- Header --}}
<div class="header">
    <div class="institution">EduManage Institute</div>
    <div class="subtitle">Autonomous Institute | Affiliated to State University</div>
    <div class="doc-title">Official Academic Transcript</div>
</div>

{{-- Student Info --}}
<div class="info-grid">
    <div class="info-row">
        <div class="info-cell">
            <div class="info-label">Student Name</div>
            <div class="info-value">{{ $student->user->name ?? '—' }}</div>
        </div>
        <div class="info-cell">
            <div class="info-label">Enrollment Number</div>
            <div class="info-value">{{ $student->enrollment_number ?? '—' }}</div>
        </div>
    </div>
    <div class="info-row">
        <div class="info-cell">
            <div class="info-label">Program</div>
            <div class="info-value">{{ $student->program->name ?? '—' }}</div>
        </div>
        <div class="info-cell">
            <div class="info-label">Batch</div>
            <div class="info-value">{{ $student->batch->name ?? '—' }}</div>
        </div>
    </div>
    <div class="info-row">
        <div class="info-cell">
            <div class="info-label">Date of Issue</div>
            <div class="info-value">{{ now()->format('d F Y') }}</div>
        </div>
        <div class="info-cell">
            <div class="info-label">Cumulative GPA</div>
            <div class="info-value" style="color:#1e40af;font-size:14px">{{ number_format($cgpa, 2) }} / 10.00</div>
        </div>
    </div>
</div>

{{-- Semester Wise Results --}}
@foreach($semesterReports as $semReport)
@php
    $report   = $semReport['report'];
    $semester = $semReport['semester'];
    $subjects = $report['subjects'] ?? $report;
    $sgpa     = $report['sgpa'] ?? 0;
    $totalCr  = $report['total_credits'] ?? 0;
    $earnedCr = $report['earned_credits'] ?? 0;
@endphp
<div class="semester-block">
    <div class="semester-title">
        Semester {{ $semester->number }} &mdash; {{ $semester->academicYear->name ?? '' }}
    </div>
    <table>
        <thead>
            <tr>
                <th>Subject</th>
                <th>Code</th>
                <th style="width:40px">Cr</th>
                <th style="width:40px">IA1</th>
                <th style="width:40px">IA2</th>
                <th style="width:40px">End Sem</th>
                <th style="width:50px">Total</th>
                <th style="width:30px">Grade</th>
                <th style="width:40px">Points</th>
            </tr>
        </thead>
        <tbody>
            @foreach($subjects as $row)
            @php
                $grade  = $row['grade'] ?? null;
                $letter = $grade['letter'] ?? '—';
                $gc     = str_starts_with($letter, 'O') ? 'grade-o' :
                          (str_starts_with($letter, 'A') ? 'grade-a' :
                          (str_starts_with($letter, 'B') ? 'grade-b' :
                          ($letter === 'F' ? 'grade-f' : '')));
            @endphp
            <tr>
                <td>{{ $row['subject']->name ?? '—' }}</td>
                <td>{{ $row['subject']->code ?? '—' }}</td>
                <td>{{ $row['subject']->credits ?? '—' }}</td>
                <td>{{ isset($row['results'][0]) ? ($row['results'][0]->ia1_marks ?? '—') : '—' }}</td>
                <td>{{ isset($row['results'][0]) ? ($row['results'][0]->ia2_marks ?? '—') : '—' }}</td>
                <td>{{ isset($row['results'][0]) ? ($row['results'][0]->end_sem_marks ?? '—') : '—' }}</td>
                <td>{{ $row['total'] !== null ? number_format($row['total'], 1) . ' / ' . $row['max'] : 'N/A' }}</td>
                <td class="{{ $gc }}">{{ $letter }}</td>
                <td>{{ $grade ? number_format($grade['points'], 1) : '—' }}</td>
            </tr>
            @endforeach
            <tr class="sgpa-row">
                <td colspan="2" style="text-align:right">Semester GPA (SGPA)</td>
                <td>{{ $totalCr }}</td>
                <td colspan="5"></td>
                <td>{{ number_format($sgpa, 2) }}</td>
            </tr>
        </tbody>
    </table>
</div>
@endforeach

{{-- Summary --}}
<div style="text-align:center;margin-top:20px">
    <div class="summary-box">
        <div class="summary-label">Cumulative Grade Point Average</div>
        <div class="summary-value">{{ number_format($cgpa, 2) }}</div>
        <div class="summary-label">out of 10.00</div>
    </div>
</div>

{{-- Footer Signatures --}}
<div class="footer">
    <div class="footer-cell">
        <div class="sig-line"></div>
        Examination Controller
    </div>
    <div class="footer-cell">
        <div class="sig-line"></div>
        Dean of Academics
    </div>
    <div class="footer-cell">
        <div class="sig-line"></div>
        Registrar
    </div>
</div>

<div style="text-align:center;font-size:8px;color:#94a3b8;margin-top:16px">
    Generated on {{ now()->format('d M Y, H:i') }} — This is a computer-generated transcript.
</div>

</body>
</html>
