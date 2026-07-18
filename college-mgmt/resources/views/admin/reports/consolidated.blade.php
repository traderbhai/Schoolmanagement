<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; margin: 0; padding: 20px; }
.header { text-align: center; border-bottom: 2px solid #4f46e5; padding-bottom: 12px; margin-bottom: 20px; }
.header h1 { font-size: 18px; margin: 0 0 4px; color: #4f46e5; }
.header p { margin: 0; color: #64748b; font-size: 10px; }
.student-info { display: flex; gap: 20px; margin-bottom: 20px; background: #f8fafc; padding: 12px; border-radius: 6px; }
.info-group { flex: 1; }
.info-group .label { color: #64748b; font-size: 9px; text-transform: uppercase; letter-spacing: .05em; }
.info-group .value { font-weight: 700; font-size: 12px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
th { background: #4f46e5; color: white; padding: 6px 8px; text-align: left; font-size: 10px; }
td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
tr:nth-child(even) td { background: #f8fafc; }
.sem-header { background: #1e293b; color: white; padding: 6px 10px; font-weight: 700; margin: 12px 0 4px; border-radius: 4px; font-size: 11px; }
.cgpa-banner { background: linear-gradient(135deg, #4f46e5, #7c3aed); color: white; padding: 12px 20px; border-radius: 6px; text-align: center; margin-top: 20px; }
.cgpa-banner .label { font-size: 10px; opacity: .8; }
.cgpa-banner .value { font-size: 28px; font-weight: 900; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: 700; }
.badge-pass { background: #dcfce7; color: #166534; }
.badge-fail { background: #fee2e2; color: #991b1b; }
</style>
</head>
<body>
<div class="header">
    <h1>EduManage Institute</h1>
    <p>Consolidated Academic Report</p>
</div>

<div class="student-info">
    <div class="info-group"><div class="label">Student Name</div><div class="value">{{ $student->user?->name ?? 'Student name missing' }}</div></div>
    <div class="info-group"><div class="label">Enrollment No</div><div class="value">{{ $student->enrollment_number ?? 'Enrollment number pending' }}</div></div>
    <div class="info-group"><div class="label">Course</div><div class="value">{{ $student->course?->name ?? $student->program?->name ?? 'Program not linked' }}</div></div>
    <div class="info-group"><div class="label">Department</div><div class="value">{{ $student->department?->name ?? 'Department not linked' }}</div></div>
    <div class="info-group"><div class="label">Report Date</div><div class="value">{{ now()->format('d M Y') }}</div></div>
</div>

@foreach($semesterReports as $sr)
<div class="sem-header">{{ $sr['semester']?->name ?? 'Semester not linked' }} - SGPA: {{ number_format($sr['report']['sgpa'],2) }} | {{ $sr['report']['result'] ?? 'Result pending' }}</div>
<table>
    <thead><tr><th scope="col">Subject</th><th scope="col">Credits</th><th scope="col">Marks</th><th scope="col">%</th><th scope="col">Grade</th><th scope="col">Points</th><th scope="col">Status</th></tr></thead>
    <tbody>
    @foreach($sr['report']['subjects'] as $row)
    <tr>
        <td>{{ $row['subject']?->name ?? 'Subject not linked' }}</td>
        <td>{{ $row['credits'] }}</td>
        <td>{{ isset($row['obtained']) ? $row['obtained'].'/'.($row['max'] ?? 'Max marks pending') : 'Marks pending' }}</td>
        <td>{{ $row['pct'] !== null ? $row['pct'].'%' : 'Result pending' }}</td>
        <td>{{ $row['grade']['letter'] ?? 'Grade pending' }}</td>
        <td>{{ $row['grade']['points'] ?? 'Points pending' }}</td>
        <td><span class="badge {{ ($row['status']??'') === 'pass' ? 'badge-pass' : 'badge-fail' }}">{{ ucfirst($row['status'] ?? 'pending') }}</span></td>
    </tr>
    @endforeach
    </tbody>
</table>
@endforeach

@if($cgpa !== null)
<div class="cgpa-banner">
    <div class="label">Cumulative GPA (CGPA)</div>
    <div class="value">{{ number_format($cgpa, 2) }} / 10.0</div>
</div>
@endif
</body>
</html>
