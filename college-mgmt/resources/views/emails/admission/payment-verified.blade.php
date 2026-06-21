<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><style>
body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:0}
.container{max-width:600px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1)}
.header{background:#198754;color:#fff;padding:28px 32px}
.body{padding:28px 32px}
.footer{background:#f8f9fa;padding:16px 32px;font-size:12px;color:#6c757d;text-align:center}
.info-box{background:#f8f9fa;border-radius:6px;padding:16px;margin:16px 0}
</style></head>
<body>
<div class="container">
    <div class="header"><h1 style="margin:0;font-size:22px">Payment Confirmed</h1></div>
    <div class="body">
        <p>Dear {{ $applicant->user?->name ?? ($applicant->personal_data['name'] ?? 'Applicant') }},</p>
        <p>Your payment has been verified successfully.</p>
        <div class="info-box">
            <strong>Installment:</strong> {{ $installmentName ?? 'Admission fee installment' }}<br>
            <strong>Amount:</strong> Rs. {{ number_format((float) $payment->amount_paid, 2) }}<br>
            <strong>Mode:</strong> {{ $payment->payment_mode ? ucfirst($payment->payment_mode) : 'Payment mode not recorded' }}<br>
            @if($payment->transaction_reference)
            <strong>Reference:</strong> {{ $payment->transaction_reference }}<br>
            @endif
            <strong>Verified on:</strong> {{ $payment->verified_at?->format('d M Y H:i') ?? 'Verification time not recorded' }}
        </div>
        <p>Please keep this email as your payment confirmation record.</p>
    </div>
    <div class="footer">This is an automated message from the Admission System.</div>
</div>
</body>
</html>
