<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; margin: 20px; color: #222; }
h1 { text-align:center; font-size:16px; margin-bottom:2px; }
h2 { text-align:center; font-size:12px; color:#555; margin-top:0; }
.info { margin-bottom:12px; }
.info table { width:100%; }
.info td { padding:2px 6px; }
table.results { width:100%; border-collapse:collapse; margin-bottom:10px; }
table.results th { background:#1e40af; color:#fff; padding:4px 6px; text-align:left; font-size:10px; }
table.results td { padding:3px 6px; border-bottom:1px solid #e5e7eb; }
.term-header { background:#f3f4f6; font-weight:bold; padding:4px 6px; margin-top:8px; }
.cgpa-box { border:2px solid #1e40af; padding:8px 16px; text-align:center; margin-top:16px; display:inline-block; }
.pass { color:#16a34a; } .fail { color:#dc2626; }
</style>
</head>
<body>
<h1>ACADEMIC TRANSCRIPT</h1>
<h2>{{ $student->program->name ?? 'Program not linked' }}</h2>
<hr>
<div class="info">
<table>
<tr>
  <td><strong>Student Name:</strong> {{ $student->user?->name ?? 'Student name missing' }}</td>
  <td><strong>Enrollment No:</strong> {{ $student->enrollment_number ?? 'Enrollment number pending' }}</td>
</tr>
<tr>
  <td><strong>Program:</strong> {{ $student->program?->name ?? 'Program not linked' }}</td>
  <td><strong>Batch:</strong> {{ $student->batch?->name ?? 'Batch not linked' }}</td>
</tr>
<tr>
  <td><strong>Issued On:</strong> {{ ($issuedAt ?? now())->format('d M Y') }}</td>
  <td><strong>Status:</strong> {{ ucfirst($student->status) }}</td>
</tr>
</table>
</div>

@foreach($semesterReports as $report)
<div class="term-header">{{ data_get($report, 'term.name', 'Term not linked') }} - SGPA: {{ $report['sgpa'] }} | Credits: {{ $report['earned_credits'] }}/{{ $report['total_credits'] }}</div>
<table class="results">
<thead><tr><th scope="col">Subject</th><th scope="col">Credits</th><th scope="col">Marks</th><th scope="col">%</th><th scope="col">Grade</th><th scope="col">Result</th></tr></thead>
<tbody>
@foreach($report['subjects'] as $s)
<tr>
  <td>{{ data_get($s, 'subject.name', 'Subject not linked') }}</td>
  <td>{{ $s['credits'] }}</td>
  <td>{{ $s['obtained'] !== null ? $s['obtained'].'/'.$s['total'] : 'Pending' }}</td>
  <td>{{ $s['pct'] !== null ? $s['pct'].'%' : 'Result pending' }}</td>
  <td><strong>{{ $s['grade']['letter'] ?? 'Grade pending' }}</strong></td>
  <td class="{{ ($s['status'] ?? null) === 'pass' ? 'pass' : 'fail' }}">{{ ucfirst($s['status'] ?? 'pending') }}</td>
</tr>
@endforeach
</tbody>
</table>
@endforeach

<div style="text-align:center;margin-top:20px;">
<div class="cgpa-box">
  <div style="font-size:14px;color:#1e40af;font-weight:bold;">Cumulative GPA (CGPA)</div>
  <div style="font-size:28px;font-weight:bold;">{{ $cgpa }}</div>
  <div style="font-size:11px;color:#555;">Total Credits Earned: {{ $totalCredits }}</div>
</div>
</div>

<p style="text-align:center;margin-top:30px;font-size:9px;color:#999;">
  This is a computer-generated transcript. For official use, obtain a signed copy from the Exam Cell.
</p>
</body>
</html>
