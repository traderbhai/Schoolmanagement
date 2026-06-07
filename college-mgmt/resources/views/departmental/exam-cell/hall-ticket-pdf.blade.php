<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; margin: 0; padding: 20px; color: #1a1a2e; }
  .card { border: 2px solid #2c3e50; border-radius: 6px; padding: 0; max-width: 580px; margin: 0 auto; }
  .header { background: #2c3e50; color: #fff; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; }
  .header-title { font-size: 16px; font-weight: bold; }
  .header-sub { font-size: 11px; opacity: .8; margin-top: 2px; }
  .badge-admit { background: #e74c3c; color: #fff; font-size: 11px; font-weight: bold; padding: 4px 10px; border-radius: 3px; }
  .body { padding: 16px 20px; }
  .student-name { font-size: 18px; font-weight: bold; margin-bottom: 4px; }
  .enroll { font-size: 11px; color: #555; margin-bottom: 14px; }
  table.details { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  table.details td { padding: 5px 8px; font-size: 11px; border: 1px solid #ddd; }
  table.details td:first-child { font-weight: bold; background: #f8f9fa; width: 35%; }
  .seat { display: inline-block; background: #2c3e50; color: #fff; font-size: 20px; font-weight: bold; padding: 6px 18px; border-radius: 4px; margin-bottom: 14px; }
  .instructions { background: #fff8e1; border: 1px solid #f39c12; border-radius: 4px; padding: 10px 14px; }
  .instructions h4 { margin: 0 0 6px; font-size: 11px; color: #d35400; }
  .instructions ol { margin: 0; padding-left: 18px; }
  .instructions li { font-size: 10px; margin-bottom: 3px; }
  .footer { background: #f8f9fa; border-top: 1px solid #ddd; padding: 8px 20px; display: flex; justify-content: space-between; font-size: 10px; color: #888; }
  .sig-line { border-top: 1px solid #2c3e50; margin-top: 30px; padding-top: 4px; font-size: 10px; text-align: center; }
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <div>
      <div class="header-title">EduManage College</div>
      <div class="header-sub">Admit / Hall Ticket</div>
    </div>
    <div class="badge-admit">ADMIT CARD</div>
  </div>
  <div class="body">
    <div class="student-name">{{ $student->user?->name ?? '—' }}</div>
    <div class="enroll">Enrollment No: {{ $student->enrollment_number ?? 'N/A' }} &nbsp;|&nbsp; Program: {{ $exam->program?->name ?? 'N/A' }}</div>

    <div class="seat">Seat No: {{ str_pad($student->id, 4, '0', STR_PAD_LEFT) }}</div>

    <table class="details">
      <tr><td>Exam Name</td><td>{{ $exam->name }}</td></tr>
      <tr><td>Subject</td><td>{{ $exam->subject?->name ?? '—' }}</td></tr>
      <tr><td>Date</td><td>{{ $exam->exam_date->format('d F Y') }}</td></tr>
      <tr><td>Time</td><td>{{ $exam->start_time ? $exam->start_time . ' – ' . ($exam->end_time ?? '') : 'As scheduled' }}</td></tr>
      <tr><td>Venue / Room</td><td>{{ $exam->classroom?->name ?? 'To be announced' }}</td></tr>
      <tr><td>Total Marks</td><td>{{ $exam->total_marks }}</td></tr>
      <tr><td>Term</td><td>{{ $exam->term?->name ?? '—' }}</td></tr>
    </table>

    <div class="instructions">
      <h4>Instructions to Candidates</h4>
      <ol>
        <li>Bring this admit card to the examination hall. Entry is not permitted without it.</li>
        <li>Carry a valid photo identity proof (college ID / Aadhaar).</li>
        <li>Report to the examination hall at least 30 minutes before the scheduled start time.</li>
        <li>Electronic devices, mobile phones, and programmable calculators are strictly prohibited.</li>
        <li>Communicate only with the invigilator during the examination.</li>
        <li>Any malpractice will result in disqualification and disciplinary action.</li>
      </ol>
    </div>

    <div style="display:flex; justify-content:space-between; margin-top:20px;">
      <div class="sig-line" style="width:38%;">Controller of Examinations</div>
      <div class="sig-line" style="width:38%;">Candidate's Signature</div>
    </div>
  </div>
  <div class="footer">
    <span>Generated: {{ now()->format('d M Y H:i') }}</span>
    <span>EduManage College — Examination Cell</span>
  </div>
</div>
</body>
</html>
