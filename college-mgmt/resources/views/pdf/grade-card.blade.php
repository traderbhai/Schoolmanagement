<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; margin: 0; }
  .header { text-align: center; border-bottom: 2px solid #1a56db; padding-bottom: 12px; margin-bottom: 18px; }
  .header h1 { margin: 0 0 4px; font-size: 20px; color: #1a56db; }
  .header p  { margin: 2px 0; color: #555; font-size: 11px; }
  .info-grid { display: flex; gap: 24px; margin-bottom: 18px; }
  .info-box  { flex: 1; background: #f8faff; border: 1px solid #dde4f0; border-radius: 6px; padding: 10px 14px; }
  .info-box .label { font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: .05em; }
  .info-box .value { font-size: 13px; font-weight: bold; color: #1a56db; margin-top: 2px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
  th { background: #1a56db; color: #fff; padding: 8px 10px; text-align: left; font-size: 11px; }
  td { padding: 7px 10px; border-bottom: 1px solid #e9edf5; font-size: 11px; }
  tr:nth-child(even) td { background: #f5f7ff; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: bold; }
  .badge-pass { background: #d1fae5; color: #065f46; }
  .badge-fail { background: #fee2e2; color: #991b1b; }
  .badge-pending { background: #fef9c3; color: #854d0e; }
  .gpa-row { display: flex; gap: 16px; margin-bottom: 18px; }
  .gpa-box { flex: 1; background: #1a56db; color: #fff; border-radius: 8px; padding: 14px; text-align: center; }
  .gpa-box .gpa-val { font-size: 28px; font-weight: bold; }
  .gpa-box .gpa-lbl { font-size: 11px; opacity: .85; margin-top: 2px; }
  .footer { text-align: center; font-size: 10px; color: #888; border-top: 1px solid #dde4f0; padding-top: 10px; margin-top: 10px; }
</style>
</head>
<body>

<div class="header">
  <h1>College Management System</h1>
  <p>Grade Card / Mark Sheet</p>
  <p>Academic Record — Official Document</p>
</div>

<div class="info-grid">
  <div class="info-box">
    <div class="label">Student Name</div>
    <div class="value">{{ $student->user->name }}</div>
  </div>
  <div class="info-box">
    <div class="label">Enrollment No.</div>
    <div class="value">{{ $student->enrollment_number }}</div>
  </div>
  <div class="info-box">
    <div class="label">Course</div>
    <div class="value">{{ $student->course->name ?? '—' }}</div>
  </div>
  <div class="info-box">
    <div class="label">Semester</div>
    <div class="value">{{ $semester->name }}</div>
  </div>
</div>

<div class="gpa-row">
  <div class="gpa-box">
    <div class="gpa-val">{{ $sgpa }}</div>
    <div class="gpa-lbl">Semester GPA (SGPA)</div>
  </div>
  <div class="gpa-box" style="background:#0e9f6e">
    <div class="gpa-val">{{ $cgpa }}</div>
    <div class="gpa-lbl">Cumulative GPA (CGPA)</div>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>Subject</th>
      <th>Credits</th>
      <th>Marks Obtained</th>
      <th>Total Marks</th>
      <th>%</th>
      <th>Grade</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    @foreach($results as $row)
    <tr>
      <td>{{ $row['subject']->name }}</td>
      <td>{{ $row['credits'] }}</td>
      <td>{{ $row['obtained'] ?? '—' }}</td>
      <td>{{ $row['max'] ?? '—' }}</td>
      <td>{{ $row['pct'] !== null ? $row['pct'].'%' : '—' }}</td>
      <td><strong>{{ $row['grade']['letter'] ?? '—' }}</strong></td>
      <td>
        @if($row['status'] === 'pass') <span class="badge badge-pass">PASS</span>
        @elseif($row['status'] === 'fail') <span class="badge badge-fail">FAIL</span>
        @else <span class="badge badge-pending">PENDING</span>
        @endif
      </td>
    </tr>
    @endforeach
  </tbody>
</table>

<div class="footer">
  Generated on {{ now()->format('d M Y, h:i A') }} &bull; This is a computer-generated document. No signature required.
</div>

</body>
</html>
