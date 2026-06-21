<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><style>
body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:0}
.container{max-width:600px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1)}
.header{background:#198754;color:#fff;padding:28px 32px}
.header h1{margin:0;font-size:22px}
.body{padding:28px 32px}
.footer{background:#f8f9fa;padding:16px 32px;font-size:12px;color:#6c757d;text-align:center}
.btn{display:inline-block;background:#198754;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;margin-top:16px}
.info-box{background:#f8f9fa;border-radius:6px;padding:16px;margin:16px 0}
</style></head>
<body>
<div class="container">
    <div class="header">
        <h1>Offer Letter Issued</h1>
    </div>
    <div class="body">
        <p>Dear {{ $applicant->user->name }},</p>
        <p>We are pleased to inform you that an offer letter has been issued for your admission to <strong>{{ $offerLetter->program?->name }}</strong>.</p>
        <div class="info-box">
            <strong>Offer Number:</strong> {{ $offerLetter->offer_number ?? 'Offer number pending' }}<br>
            <strong>Program:</strong> {{ $offerLetter->program?->name ?? 'Program to be confirmed' }}<br>
            <strong>Batch:</strong> {{ $offerLetter->batch?->name ?? 'Batch to be confirmed' }}<br>
            @if($offerLetter->acceptance_deadline)
            <strong>Accept by:</strong> {{ $offerLetter->acceptance_deadline->format('d M Y') }}<br>
            @endif
        </div>
        <p>Please log in to the applicant portal to view and accept/decline your offer letter.</p>
        <a href="{{ config('app.url') }}/applicant/offer-letters" class="btn">View Offer Letter</a>
        <p style="margin-top:24px;color:#6c757d;font-size:13px">If you have any questions, please contact the admission office.</p>
    </div>
    <div class="footer">This is an automated message from the Admission System.</div>
</div>
</body>
</html>
