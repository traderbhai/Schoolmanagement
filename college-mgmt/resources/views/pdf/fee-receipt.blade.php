<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; margin: 0; }
  .header { text-align: center; border-bottom: 2px solid #1a56db; padding-bottom: 12px; margin-bottom: 20px; }
  .header h1 { margin: 0 0 4px; font-size: 20px; color: #1a56db; }
  .receipt-no { font-size: 14px; font-weight: bold; color: #374151; margin-top: 6px; }
  .grid { display: flex; gap: 20px; margin-bottom: 20px; }
  .box { flex: 1; background: #f8faff; border: 1px solid #dde4f0; border-radius: 6px; padding: 12px 14px; }
  .box .label { font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4px; }
  .box .value { font-size: 13px; font-weight: bold; color: #1a56db; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  th { background: #1a56db; color: #fff; padding: 9px 12px; font-size: 11px; text-align: left; }
  td { padding: 8px 12px; border-bottom: 1px solid #e9edf5; font-size: 11px; }
  .amount-row td { font-size: 15px; font-weight: bold; color: #1a56db; background: #f0f5ff; }
  .badge-paid { background: #d1fae5; color: #065f46; padding: 3px 10px; border-radius: 10px; font-size: 11px; font-weight: bold; }
  .footer { text-align: center; font-size: 10px; color: #888; border-top: 1px solid #dde4f0; padding-top: 10px; margin-top: 16px; }
</style>
</head>
<body>

<div class="header">
  <h1>College Management System</h1>
  <p>Fee Payment Receipt</p>
  <div class="receipt-no">Receipt No: {{ $payment->receipt_number }}</div>
</div>

<div class="grid">
  <div class="box">
    <div class="label">Student Name</div>
    <div class="value">{{ $payment->student->user->name }}</div>
  </div>
  <div class="box">
    <div class="label">Enrollment No.</div>
    <div class="value">{{ $payment->student->enrollment_number }}</div>
  </div>
  <div class="box">
    <div class="label">Course</div>
    <div class="value">{{ $payment->student->course->name ?? '-' }}</div>
  </div>
  <div class="box">
    <div class="label">Payment Date</div>
    <div class="value">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') }}</div>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>Description</th>
      <th>Fee Type</th>
      <th>Payment Method</th>
      <th>Status</th>
      <th style="text-align:right">Amount</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>{{ $payment->feeStructure->name ?? 'Fee Payment' }}</td>
      <td>{{ ucfirst($payment->feeStructure->fee_type ?? '-') }}</td>
      <td>{{ ucfirst($payment->payment_method) }}</td>
      <td><span class="badge-paid">{{ strtoupper($payment->status) }}</span></td>
      <td style="text-align:right">INR {{ number_format($payment->amount_paid, 2) }}</td>
    </tr>
    <tr class="amount-row">
      <td colspan="4" style="text-align:right">Total Amount Paid</td>
      <td style="text-align:right">INR {{ number_format($payment->amount_paid, 2) }}</td>
    </tr>
  </tbody>
</table>

@if($payment->transaction_id)
<p style="font-size:11px;color:#555;">Transaction ID: <strong>{{ $payment->transaction_id }}</strong></p>
@endif
@if($payment->remarks)
<p style="font-size:11px;color:#555;">Remarks: {{ $payment->remarks }}</p>
@endif

<div class="footer">
  Generated on {{ now()->format('d M Y, h:i A') }} | This is a computer-generated receipt. No signature required.
</div>

</body>
</html>
