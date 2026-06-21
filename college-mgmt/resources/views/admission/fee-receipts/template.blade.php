<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Fee Receipt - {{ $receiptNo }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #222;
            background: #fff;
            padding: 18px 20px;
        }
        .header {
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 12px;
            text-align: center;
        }
        .college-name {
            font-size: 20px;
            font-weight: bold;
            color: #1a237e;
            letter-spacing: 0.5px;
        }
        .receipt-subtitle {
            font-size: 13px;
            font-weight: bold;
            color: #333;
            letter-spacing: 2px;
            margin-top: 4px;
            text-transform: uppercase;
        }
        .thin-line {
            border: none;
            border-top: 1px solid #ccc;
            margin-top: 8px;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 12px;
        }
        .meta-table td {
            vertical-align: top;
            padding: 2px 0;
        }
        .meta-left {
            width: 55%;
        }
        .meta-right {
            width: 45%;
            text-align: right;
        }
        .meta-label {
            color: #555;
            font-weight: normal;
        }
        .meta-value {
            font-weight: bold;
            color: #111;
        }
        .section-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .section-table th {
            background-color: #e8e8e8;
            color: #333;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 5px 7px;
            text-align: left;
            border: 1px solid #ccc;
        }
        .section-table td {
            padding: 5px 7px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        .section-table td.label-col {
            width: 40%;
            color: #555;
            background: #fafafa;
        }
        .section-table td.value-col {
            color: #111;
            font-weight: 500;
        }
        .payment-box {
            border: 1px solid #007bff;
            background: #f0f7ff;
            padding: 12px;
            margin-bottom: 12px;
            position: relative;
        }
        .payment-box-title {
            font-size: 11px;
            font-weight: bold;
            color: #007bff;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            border-bottom: 1px solid #bee3f8;
            padding-bottom: 5px;
        }
        .amount-large {
            font-size: 22px;
            font-weight: bold;
            color: #1a237e;
        }
        .amount-label {
            font-size: 10px;
            color: #555;
            margin-bottom: 2px;
        }
        .amount-words {
            font-style: italic;
            color: #444;
            margin-bottom: 8px;
            font-size: 10.5px;
        }
        .payment-detail-table {
            width: 100%;
            border-collapse: collapse;
        }
        .payment-detail-table td {
            padding: 3px 0;
            vertical-align: top;
        }
        .payment-detail-table td.pd-label {
            width: 38%;
            color: #555;
        }
        .payment-detail-table td.pd-colon {
            width: 4%;
            color: #555;
        }
        .payment-detail-table td.pd-value {
            color: #111;
            font-weight: 500;
        }
        .paid-stamp {
            position: absolute;
            top: 10px;
            right: 14px;
            font-size: 28px;
            font-weight: bold;
            color: #28a745;
            opacity: 0.55;
            transform: rotate(15deg);
            letter-spacing: 3px;
            border: 3px solid #28a745;
            padding: 2px 8px;
            line-height: 1.1;
        }
        .footer {
            border-top: 1px solid #ccc;
            margin-top: 14px;
            padding-top: 8px;
            font-size: 9.5px;
            color: #666;
            text-align: center;
        }
        .footer-note {
            font-style: italic;
            margin-bottom: 3px;
        }
        .footer-address {
            color: #888;
        }
        .signature-area {
            margin-top: 16px;
            text-align: right;
            font-size: 10.5px;
        }
        .signature-line {
            margin-top: 24px;
            border-top: 1px solid #555;
            width: 140px;
            display: inline-block;
        }
        .signature-label {
            color: #444;
            font-size: 10px;
        }
    </style>
</head>
<body>

@php
if (! function_exists('amountInWords')) {
function amountInWords(float $amount): string {
    $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
             'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen',
             'Seventeen','Eighteen','Nineteen'];
    $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    $rupees = (int) $amount;
    if ($rupees === 0) return 'Zero Rupees Only';
    $words = '';
    if ($rupees >= 100000) { $words .= $ones[(int)($rupees/100000)] . ' Lakh '; $rupees %= 100000; }
    if ($rupees >= 1000)   { $lthous = (int)($rupees/1000); $words .= ($lthous >= 20 ? $tens[(int)($lthous/10)] . ($lthous%10 ? ' '.$ones[$lthous%10] : '') : $ones[$lthous]) . ' Thousand '; $rupees %= 1000; }
    if ($rupees >= 100)    { $words .= $ones[(int)($rupees/100)] . ' Hundred '; $rupees %= 100; }
    if ($rupees >= 20)     { $words .= $tens[(int)($rupees/10)] . ($rupees%10 ? ' '.$ones[$rupees%10] : '') . ' '; $rupees = 0; }
    elseif ($rupees > 0)   { $words .= $ones[$rupees] . ' '; }
    return trim($words) . ' Rupees Only';
}
}
@endphp

{{-- ===== HEADER ===== --}}
<div class="header">
    <div class="college-name">{{ $collegeName }}</div>
    <div class="receipt-subtitle">Admission Fee Receipt</div>
    <hr class="thin-line">
</div>

{{-- ===== RECEIPT META (two columns) ===== --}}
<table class="meta-table">
    <tr>
        <td class="meta-left">
            <div><span class="meta-label">Receipt No: </span><span class="meta-value">{{ $receiptNo }}</span></div>
            <div style="margin-top:3px;">
                <span class="meta-label">Date: </span>
                <span class="meta-value">
                    {{ $payment->verified_at ? $payment->verified_at->format('d M Y') : now()->format('d M Y') }}
                </span>
            </div>
        </td>
        <td class="meta-right">
            <div><span class="meta-label">Application No: </span></div>
            <div><span class="meta-value">{{ $applicant->application_number ?? 'Application number pending' }}</span></div>
        </td>
    </tr>
</table>

{{-- ===== APPLICANT DETAILS TABLE ===== --}}
<table class="section-table">
    <tr>
        <th colspan="2">Applicant Details</th>
    </tr>
    <tr>
        <td class="label-col">Name</td>
        <td class="value-col">{{ $applicant->user->name ?? 'Applicant name not recorded' }}</td>
    </tr>
    <tr>
        <td class="label-col">Program</td>
        <td class="value-col">{{ $applicant->program->name ?? 'Program not selected' }}</td>
    </tr>
    <tr>
        <td class="label-col">Batch</td>
        <td class="value-col">{{ $applicant->batch->name ?? 'Batch not selected' }}</td>
    </tr>
    <tr>
        <td class="label-col">Contact (Email)</td>
        <td class="value-col">{{ $applicant->user->email ?? 'Email not recorded' }}</td>
    </tr>
</table>

{{-- ===== PAYMENT DETAILS BOX ===== --}}
<div class="payment-box">
    <div class="paid-stamp">PAID</div>
    <div class="payment-box-title">Payment Details</div>

    <div class="amount-label">Amount Paid</div>
    <div class="amount-large">&#8377;{{ number_format($payment->amount_paid, 2) }}</div>
    <div class="amount-words" style="margin-top:3px;">
        {{ amountInWords((float) $payment->amount_paid) }}
    </div>

    <table class="payment-detail-table">
        <tr>
            <td class="pd-label">Payment Method</td>
            <td class="pd-colon">:</td>
            <td class="pd-value">{{ $payment->payment_mode ? $payment->payment_mode_label : 'Payment method not recorded' }}</td>
        </tr>
        <tr>
            <td class="pd-label">Reference / UTR No.</td>
            <td class="pd-colon">:</td>
            <td class="pd-value">{{ $payment->transaction_reference ?? 'Reference not recorded' }}</td>
        </tr>
        <tr>
            <td class="pd-label">Payment Type</td>
            <td class="pd-colon">:</td>
            <td class="pd-value">{{ ucwords(str_replace('_', ' ', $payment->payment_type ?? 'Fee')) }}</td>
        </tr>
        <tr>
            <td class="pd-label">Verified On</td>
            <td class="pd-colon">:</td>
            <td class="pd-value">
                {{ $payment->verified_at ? $payment->verified_at->format('d M Y H:i') : 'Verification time not recorded' }}
            </td>
        </tr>
    </table>
</div>

{{-- ===== SIGNATURE AREA ===== --}}
<div class="signature-area">
    <div>For <strong>{{ $collegeName }}</strong></div>
    <div class="signature-line">&nbsp;</div>
    <br>
    <div class="signature-label">Accounts Department</div>
</div>

{{-- ===== FOOTER ===== --}}
<div class="footer">
    <div class="footer-note">This is a computer-generated receipt. No signature required.</div>
    <div class="footer-address">{{ $collegeFooterLine ?? $collegeName }}</div>
</div>

</body>
</html>
