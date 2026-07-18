<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 10px; margin: 15px; }
h1 { text-align:center; font-size:14px; }
h2 { text-align:center; font-size:11px; color:#555; }
table { width:100%; border-collapse:collapse; margin-top:10px; }
th { background:#1e40af; color:#fff; padding:5px; text-align:left; font-size:9px; }
td { padding:4px 5px; border-bottom:1px solid #e5e7eb; font-size:9px; }
.pass { color:#16a34a; } .warn { color:#d97706; } .fail { color:#dc2626; }
.totals td { background:#f3f4f6; font-weight:bold; }
</style>
</head>
<body>
<h1>AICTE COMPLIANCE REPORT — {{ $reportYear }}</h1>
<h2>Academic Performance &amp; Institutional Data</h2>
<p style="text-align:center;font-size:9px;">Generated: {{ now()->format('d M Y H:i') }} | Total Students: {{ $totalStudents }} | Total Faculty: {{ $totalFaculty }}</p>
<table>
<thead><tr>
<th scope="col">Program</th><th scope="col">Department</th><th scope="col">Students</th><th scope="col">Faculty</th><th scope="col">Subjects</th><th scope="col">Exams ({{ $reportYear }})</th><th scope="col">Pass Rate %</th><th scope="col">Attendance %</th>
</tr></thead>
<tbody>
@foreach($programs as $prog)
@php
  $passClass = $prog->pass_rate === null ? '' : ($prog->pass_rate < 60 ? 'fail' : ($prog->pass_rate < 75 ? 'warn' : 'pass'));
  $attClass  = $prog->attendance_pct === null ? '' : ($prog->attendance_pct < 60 ? 'fail' : ($prog->attendance_pct < 75 ? 'warn' : 'pass'));
@endphp
<tr>
<td>{{ $prog->name }}</td>
<td>{{ $prog->department->name ?? '—' }}</td>
<td>{{ $prog->active_students }}</td>
<td>{{ $prog->faculty_count }}</td>
<td>{{ $prog->subject_count }}</td>
<td>{{ $prog->exam_count }}</td>
<td class="{{ $passClass }}">{{ $prog->pass_rate !== null ? $prog->pass_rate.'%' : 'N/A' }}</td>
<td class="{{ $attClass }}">{{ $prog->attendance_pct !== null ? $prog->attendance_pct.'%' : 'N/A' }}</td>
</tr>
@endforeach
<tr class="totals">
<td colspan="2">INSTITUTION TOTAL</td>
<td>{{ $totalStudents }}</td>
<td>{{ $totalFaculty }}</td>
<td colspan="4"></td>
</tr>
</tbody>
</table>
</body>
</html>
