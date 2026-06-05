<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enrollment Confirmation Letter — {{ $confirmation->enrollment_number }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; margin: 40px; }
        .letterhead { text-align: center; border-bottom: 3px double #2c4a8f; padding-bottom: 15px; margin-bottom: 25px; }
        .letterhead h1 { font-size: 22px; color: #2c4a8f; margin: 0 0 4px; }
        .letterhead p  { margin: 0; font-size: 11px; color: #555; }
        .title-row     { text-align: center; margin: 20px 0; }
        .title-row h2  { font-size: 16px; letter-spacing: 1px; text-transform: uppercase; color: #2c4a8f; border: 2px solid #2c4a8f; display: inline-block; padding: 6px 20px; }
        table.details  { width: 100%; border-collapse: collapse; margin: 18px 0; }
        table.details th, table.details td { padding: 7px 10px; border: 1px solid #ccc; font-size: 11px; }
        table.details th { background: #eef2ff; width: 35%; text-align: left; }
        .footer { margin-top: 40px; display: flex; justify-content: space-between; }
        .sig-block { width: 45%; }
        .sig-line { border-top: 1px solid #333; margin-top: 50px; padding-top: 4px; font-size: 11px; }
        .ref-box { background: #f0f4ff; border: 1px solid #c0ccee; padding: 10px 15px; margin: 20px 0; font-size: 11px; }
    </style>
</head>
<body>
    <div class="letterhead">
        <h1>College Management System</h1>
        <p>Admissions Office &bull; Accredited Institute of Management &bull; enrollment@college.edu</p>
    </div>

    <div class="title-row">
        <h2>Enrollment Confirmation Letter</h2>
    </div>

    <div class="ref-box">
        <strong>Reference:</strong> {{ $confirmation->enrollment_number }} &nbsp;&nbsp;&nbsp;
        <strong>Date:</strong> {{ $confirmation->confirmed_at?->format('d F Y') ?? now()->format('d F Y') }}
    </div>

    <p>Dear <strong>{{ $confirmation->student->user->name ?? ($confirmation->applicant->user->name ?? 'Student') }}</strong>,</p>
    <p>
        We are pleased to confirm your enrollment at our institution for the following program.
        Please retain this letter as proof of your enrollment.
    </p>

    <table class="details">
        <tr><th>Enrollment Number</th><td><strong>{{ $confirmation->enrollment_number }}</strong></td></tr>
        <tr><th>Roll Number</th><td>{{ $confirmation->roll_number }}</td></tr>
        <tr><th>Student Name</th><td>{{ $confirmation->student->user->name ?? ($confirmation->applicant->user->name ?? '—') }}</td></tr>
        <tr><th>Email</th><td>{{ $confirmation->student->user->email ?? '—' }}</td></tr>
        <tr><th>Program</th><td>{{ $confirmation->applicant->program->name ?? '—' }}</td></tr>
        <tr><th>Batch</th><td>{{ $confirmation->batch->name ?? '—' }}</td></tr>
        <tr><th>Term / Semester</th><td>{{ $confirmation->term->name ?? 'Semester I' }}</td></tr>
        <tr><th>Admission Date</th><td>{{ $confirmation->confirmed_at?->format('d F Y') ?? '—' }}</td></tr>
        <tr><th>Application Number</th><td>{{ $confirmation->applicant->application_number }}</td></tr>
    </table>

    <p><strong>Fee Payment Summary</strong></p>
    <table class="details">
        <tr><th>Total Verified Payments</th><td>₹ {{ number_format($confirmation->applicant->payments->where('status','verified')->sum('amount_paid'), 2) }}</td></tr>
    </table>

    @if($confirmation->notes)
    <p><em>Note: {{ $confirmation->notes }}</em></p>
    @endif

    <p>
        You are required to report to the admissions office on the first day of classes with this letter
        and your original documents.
    </p>

    <div class="footer">
        <div class="sig-block">
            <div class="sig-line">Signature of Student</div>
        </div>
        <div class="sig-block" style="text-align:right;">
            <div class="sig-line">{{ $confirmation->confirmedBy->name ?? 'Admission Head' }}<br>Admissions Office</div>
        </div>
    </div>
</body>
</html>
