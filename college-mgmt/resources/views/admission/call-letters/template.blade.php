<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Call Letter - {{ $applicant->application_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 13px;
            line-height: 1.6;
            color: #222;
            background: #fff;
        }

        .page {
            width: 100%;
            padding: 36px 48px;
        }

        /* ── Header ── */
        .header {
            text-align: center;
            margin-bottom: 6px !important;
        }

        .header h1 {
            font-size: 22px;
            font-weight: bold;
            color: #1a1a1a;
            letter-spacing: 1px;
            margin-bottom: 4px !important;
        }

        .header h2 {
            font-size: 14px;
            font-weight: bold;
            color: #444;
            letter-spacing: 2px;
            margin-bottom: 10px !important;
            text-transform: uppercase;
        }

        hr.thick {
            border: none;
            border-top: 2px solid #333;
            margin: 10px 0 14px 0 !important;
        }

        hr.thin {
            border: none;
            border-top: 1px solid #aaa;
            margin: 20px 0 10px 0 !important;
        }

        /* ── Reference line ── */
        .ref-line {
            width: 100%;
            margin-bottom: 16px !important;
        }

        .ref-left {
            display: inline-block;
            width: 50%;
        }

        .ref-right {
            display: inline-block;
            width: 49%;
            text-align: right;
        }

        /* ── Body text ── */
        .salutation {
            margin-bottom: 10px !important;
        }

        .body-para {
            margin-bottom: 14px !important;
        }

        /* ── Schedule box ── */
        .schedule-box {
            border: 1px solid #ccc;
            padding: 12px !important;
            margin: 16px 0 !important;
            background-color: #f5f5f5;
        }

        .schedule-box h3 {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            color: #333;
            margin-bottom: 10px !important;
            letter-spacing: 1px;
        }

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
        }

        .schedule-table td {
            padding: 5px 4px !important;
            vertical-align: top;
            font-size: 13px;
        }

        .schedule-table td.label {
            font-weight: bold;
            width: 35%;
            color: #333;
        }

        .schedule-table td.colon {
            width: 3%;
            color: #333;
        }

        .schedule-table td.value {
            width: 62%;
            color: #111;
        }

        .schedule-table tr + tr td {
            border-top: 1px solid #e0e0e0;
        }

        /* ── Document list ── */
        .section-title {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            color: #333;
            margin-bottom: 6px !important;
            letter-spacing: 1px;
        }

        ul.doc-list {
            margin-left: 20px !important;
            margin-bottom: 14px !important;
        }

        ul.doc-list li {
            margin-bottom: 4px !important;
        }

        ol.instr-list {
            margin-left: 20px !important;
            margin-bottom: 20px !important;
        }

        ol.instr-list li {
            margin-bottom: 4px !important;
        }

        /* ── Signature block ── */
        .signature-block {
            text-align: right;
            margin-top: 30px !important;
            margin-bottom: 10px !important;
        }

        .signature-block p {
            margin-bottom: 2px !important;
        }

        .sig-gap {
            margin-top: 36px !important;
            margin-bottom: 2px !important;
        }

        /* ── Footer ── */
        .footer {
            text-align: center;
            font-size: 11px;
            color: #666;
            font-style: italic;
            margin-top: 4px !important;
        }
    </style>
</head>
<body>
<div class="page">

    {{-- 1. Header --}}
    <div class="header">
        <h1>{{ strtoupper($collegeName) }}</h1>
        <h2>Call Letter / Interview Notice</h2>
    </div>
    <hr class="thick">

    {{-- 2. Reference line --}}
    <div class="ref-line">
        <span class="ref-left">
            <strong>Application No:</strong> {{ $applicant->application_number }}
        </span>
        <span class="ref-right">
            <strong>Date:</strong> {{ now()->format('d M Y') }}
        </span>
    </div>

    {{-- 3. Salutation --}}
    <p class="salutation">Dear <strong>{{ $applicant->user->name ?? 'Applicant' }}</strong>,</p>

    {{-- 4. Body paragraph --}}
    <p class="body-para">
        You are hereby invited to appear for the admission process for
        <strong>{{ $applicant->program->name ?? 'the program' }}</strong> program.
        Please report as per the schedule below:
    </p>

    {{-- 5. Schedule box --}}
    <div class="schedule-box">
        <h3>Interview / Admission Schedule</h3>
        <table class="schedule-table">
            <tr>
                <td class="label">Program</td>
                <td class="colon">:</td>
                <td class="value">{{ $applicant->program->name ?? '&mdash;' }}</td>
            </tr>
            <tr>
                <td class="label">Batch</td>
                <td class="colon">:</td>
                <td class="value">{{ $applicant->batch->name ?? '&mdash;' }}</td>
            </tr>
            <tr>
                <td class="label">Date</td>
                <td class="colon">:</td>
                <td class="value">
                    @if($session && $session->scheduled_date)
                        {{ \Carbon\Carbon::parse($session->scheduled_date)->format('d M Y') }}
                    @else
                        To be announced
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">Time</td>
                <td class="colon">:</td>
                <td class="value">
                    @if($session && $session->start_time && $session->end_time)
                        {{ \Carbon\Carbon::parse($session->start_time)->format('h:i A') }}
                        &ndash;
                        {{ \Carbon\Carbon::parse($session->end_time)->format('h:i A') }}
                    @else
                        &mdash;
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">Venue</td>
                <td class="colon">:</td>
                <td class="value">
                    @if($session && $session->venue)
                        {{ $session->venue }}
                    @else
                        &mdash;
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">Reporting Time</td>
                <td class="colon">:</td>
                <td class="value">
                    @if($session && $session->start_time)
                        {{ \Carbon\Carbon::parse($session->start_time)->subMinutes(30)->format('h:i A') }}
                        (30 minutes before start)
                    @else
                        &mdash;
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- 6. Documents to carry --}}
    <p class="section-title">Documents to Carry</p>
    <ul class="doc-list">
        <li>Original &amp; photocopy of all academic certificates</li>
        <li>Category / Caste certificate (if applicable)</li>
        <li>Entrance exam scorecard (if applicable)</li>
        <li>Government-issued photo ID proof</li>
        <li>2 passport-size photographs</li>
        <li>This call letter (printout)</li>
    </ul>

    {{-- 7. Important instructions --}}
    <p class="section-title">Important Instructions</p>
    <ol class="instr-list">
        <li>Mobile phones and electronic devices are <strong>not allowed</strong> inside the interview hall.</li>
        <li>Late arrivals will <strong>not be entertained</strong> under any circumstances.</li>
        <li>Dress code: <strong>Formal attire</strong> is mandatory for all candidates.</li>
        <li>Bring all original documents for verification; failure to do so may result in disqualification.</li>
    </ol>

    {{-- 8. Signature block --}}
    <div class="signature-block">
        <p>For <strong>{{ $collegeName }}</strong></p>
        <p class="sig-gap">&nbsp;</p>
        <p><strong>Admission Office</strong></p>
    </div>

    {{-- 9. Footer --}}
    <hr class="thin">
    <div class="footer">
        <p>This is a computer-generated call letter. No signature required.</p>
    </div>

</div>
</body>
</html>
