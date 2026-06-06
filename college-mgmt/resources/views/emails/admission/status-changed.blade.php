<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><style>
body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:0}
.container{max-width:600px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1)}
.header{background:{{ in_array($newStatus,['shortlisted','selected']) ? '#198754' : (in_array($newStatus,['rejected']) ? '#dc3545' : '#0d6efd') }};color:#fff;padding:28px 32px}
.header h1{margin:0;font-size:22px}
.body{padding:28px 32px}
.footer{background:#f8f9fa;padding:16px 32px;font-size:12px;color:#6c757d;text-align:center}
.btn{display:inline-block;background:#0d6efd;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;margin-top:16px}
</style></head>
<body>
<div class="container">
    <div class="header">
        <h1>{{ $title }}</h1>
    </div>
    <div class="body">
        <p>Dear {{ $applicant->user->name }},</p>
        <p>{{ $message }}</p>
        <p><strong>Application Number:</strong> {{ $applicant->application_number }}<br>
        <strong>Program:</strong> {{ $applicant->program?->name ?? 'N/A' }}<br>
        <strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $newStatus)) }}</p>
        @if(in_array($newStatus, ['shortlisted','selected']))
            <a href="{{ config('app.url') }}/applicant/status" class="btn">View Application Status</a>
        @endif
        <p style="margin-top:24px;color:#6c757d;font-size:13px">If you have any questions, please contact the admission office.</p>
    </div>
    <div class="footer">This is an automated message from the Admission System. Please do not reply to this email.</div>
</div>
</body>
</html>
