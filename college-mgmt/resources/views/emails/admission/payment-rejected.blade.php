<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><style>
body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:0}
.container{max-width:600px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1)}
.header{background:#dc3545;color:#fff;padding:28px 32px}
.body{padding:28px 32px}
.footer{background:#f8f9fa;padding:16px 32px;font-size:12px;color:#6c757d;text-align:center}
.reason-box{background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:12px 16px;margin:16px 0}
.btn{display:inline-block;background:#0d6efd;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;margin-top:8px}
</style></head>
<body>
<div class="container">
    <div class="header"><h1 style="margin:0;font-size:22px">Payment Not Verified</h1></div>
    <div class="body">
        <p>Dear {{ $applicant->user->name }},</p>
        <p>We were unable to verify your payment of <strong>₹{{ number_format($payment->amount_paid, 2) }}</strong> for <strong>{{ $installmentName }}</strong>.</p>
        <div class="reason-box"><strong>Reason:</strong> {{ $reason }}</div>
        <p>Please re-upload your payment proof in the applicant portal.</p>
        <a href="{{ config('app.url') }}/applicant/fees" class="btn">Upload Payment Proof</a>
        <p style="margin-top:16px;color:#6c757d;font-size:13px">Contact the admission office if you need assistance.</p>
    </div>
    <div class="footer">This is an automated message from the Admission System.</div>
</div>
</body>
</html>
