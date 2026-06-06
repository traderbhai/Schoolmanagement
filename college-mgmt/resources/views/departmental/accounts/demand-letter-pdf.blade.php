<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #222; padding: 30px 40px; }
        .header { text-align: center; border-bottom: 2px solid #1d4ed8; padding-bottom: 12px; margin-bottom: 24px; }
        .header h1 { font-size: 20px; color: #1d4ed8; margin-bottom: 4px; }
        .header p { font-size: 12px; color: #555; }
        .doc-title { text-align: center; font-size: 16px; font-weight: bold; text-transform: uppercase;
                     letter-spacing: 2px; border: 1px solid #1d4ed8; padding: 8px; margin-bottom: 24px; color: #1d4ed8; }
        .ref-row { display: flex; justify-content: space-between; margin-bottom: 18px; font-size: 12px; color: #555; }
        table.info { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.info td { padding: 6px 10px; border: 1px solid #e5e7eb; font-size: 13px; }
        table.info td:first-child { background: #f3f4f6; font-weight: bold; width: 35%; }
        table.fee { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        table.fee th { background: #1d4ed8; color: #fff; padding: 8px 12px; text-align: left; font-size: 13px; }
        table.fee td { padding: 8px 12px; border-bottom: 1px solid #e5e7eb; font-size: 13px; }
        table.fee tr.total td { font-weight: bold; background: #eff6ff; border-top: 2px solid #1d4ed8; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .status-pending   { background: #fef3c7; color: #92400e; }
        .status-overdue   { background: #fee2e2; color: #991b1b; }
        .status-paid      { background: #d1fae5; color: #065f46; }
        .note { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 12px 16px; font-size: 12px; margin-bottom: 24px; }
        .footer { margin-top: 40px; font-size: 11px; color: #888; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 12px; }
        .signature-row { display: flex; justify-content: space-between; margin-top: 50px; }
        .sig-box { text-align: center; width: 200px; border-top: 1px solid #555; padding-top: 6px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name', 'Institute') }}</h1>
        <p>Fee Demand Notice</p>
    </div>

    <div class="doc-title">Fee Demand Notice</div>

    <div class="ref-row">
        <span>Demand No: FD-{{ str_pad($feeDemand->id, 6, '0', STR_PAD_LEFT) }}</span>
        <span>Date: {{ now()->format('d M Y') }}</span>
    </div>

    <table class="info">
        <tr><td>Student Name</td><td>{{ $feeDemand->student->user->name ?? '—' }}</td></tr>
        <tr><td>Program</td><td>{{ $feeDemand->student->program->name ?? '—' }}</td></tr>
        <tr><td>Term / Semester</td><td>{{ $feeDemand->term->name ?? '—' }}</td></tr>
        <tr><td>Due Date</td><td>{{ $feeDemand->due_date?->format('d M Y') ?? '—' }}</td></tr>
        <tr>
            <td>Status</td>
            <td>
                @php
                    $statusClass = match($feeDemand->status) {
                        'pending'         => 'status-pending',
                        'overdue'         => 'status-overdue',
                        'fully_paid'      => 'status-paid',
                        default           => 'status-pending',
                    };
                @endphp
                <span class="status-badge {{ $statusClass }}">{{ ucwords(str_replace('_', ' ', $feeDemand->status)) }}</span>
            </td>
        </tr>
    </table>

    <table class="fee">
        <thead>
            <tr>
                <th>Description</th>
                <th style="text-align:right">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Gross Fee for {{ $feeDemand->term->name ?? 'Term' }}</td>
                <td style="text-align:right">₹{{ number_format($feeDemand->total_amount, 2) }}</td>
            </tr>
            @if($feeDemand->scholarship_deduction > 0)
            <tr>
                <td>Scholarship Deduction</td>
                <td style="text-align:right; color: #059669;">— ₹{{ number_format($feeDemand->scholarship_deduction, 2) }}</td>
            </tr>
            @endif
            <tr class="total">
                <td>Net Amount Payable</td>
                <td style="text-align:right">₹{{ number_format($feeDemand->final_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if(!$feeDemand->isPaid())
    <div class="note">
        <strong>Note:</strong> Please pay the above amount on or before <strong>{{ $feeDemand->due_date?->format('d M Y') }}</strong>.
        Late payment may attract a penalty. Contact the accounts office for any queries.
    </div>
    @endif

    <div class="signature-row">
        <div class="sig-box">Student Signature</div>
        <div class="sig-box">Accounts Officer</div>
    </div>

    <div class="footer">
        This is a system-generated document. &mdash; {{ config('app.name') }} &mdash; Printed on {{ now()->format('d M Y, h:i A') }}
    </div>
</body>
</html>
