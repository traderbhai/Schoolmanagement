<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Fee Receipt</title></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:40px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;">
      <tr><td style="background:#059669;padding:30px 40px;">
        <h1 style="margin:0;color:#ffffff;font-size:24px;">Fee Payment Confirmation</h1>
      </td></tr>
      <tr><td style="padding:40px;">
        <p style="margin:0 0 16px;font-size:16px;color:#374151;">Dear <strong>{{ $payment->student?->user?->name ?? 'Student' }}</strong>,</p>
        <p style="margin:0 0 24px;font-size:15px;color:#6b7280;">Your fee payment has been recorded successfully. Please keep this receipt for your records.</p>
        <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;margin-bottom:24px;">
          <tr style="background:#f9fafb;">
            <td style="padding:12px 16px;font-size:14px;color:#6b7280;width:40%;">Receipt Number</td>
            <td style="padding:12px 16px;font-size:14px;color:#111827;font-weight:bold;">{{ $payment->receipt_number ?? 'Receipt number pending' }}</td>
          </tr>
          <tr>
            <td style="padding:12px 16px;font-size:14px;color:#6b7280;border-top:1px solid #e5e7eb;">Fee Type</td>
            <td style="padding:12px 16px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">{{ $payment->feeStructure?->fee_type ?? 'Fee type not linked' }}</td>
          </tr>
          <tr style="background:#f9fafb;">
            <td style="padding:12px 16px;font-size:14px;color:#6b7280;border-top:1px solid #e5e7eb;">Amount Paid</td>
            <td style="padding:12px 16px;font-size:14px;color:#111827;font-weight:bold;border-top:1px solid #e5e7eb;">Rs. {{ number_format((float) ($payment->amount_paid ?? $payment->amount ?? 0), 2) }}</td>
          </tr>
          <tr>
            <td style="padding:12px 16px;font-size:14px;color:#6b7280;border-top:1px solid #e5e7eb;">Payment Date</td>
            <td style="padding:12px 16px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') : 'Payment date pending' }}</td>
          </tr>
          <tr style="background:#f9fafb;">
            <td style="padding:12px 16px;font-size:14px;color:#6b7280;border-top:1px solid #e5e7eb;">Payment Method</td>
            <td style="padding:12px 16px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">{{ ucfirst($payment->payment_method ?? $payment->payment_mode ?? 'Payment method pending') }}</td>
          </tr>
        </table>
        <p style="margin:0;font-size:14px;color:#6b7280;">If you have any discrepancies, please contact the accounts office.</p>
      </td></tr>
      <tr><td style="background:#f9fafb;padding:20px 40px;text-align:center;">
        <p style="margin:0;font-size:13px;color:#9ca3af;">&copy; {{ date('Y') }} College Management System. All rights reserved.</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
