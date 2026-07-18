<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Application Form - {{ $applicant->application_number }}</title>
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
            padding: 22px 24px;
        }
        .header {
            text-align: center;
            padding-bottom: 10px;
            border-bottom: 2px solid #1a237e;
            margin-bottom: 14px;
        }
        .college-name {
            font-size: 22px;
            font-weight: bold;
            color: #1a237e;
            letter-spacing: 0.5px;
        }
        .form-subtitle {
            font-size: 13px;
            font-weight: bold;
            color: #444;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 4px;
        }
        .header-meta {
            font-size: 10px;
            color: #666;
            margin-top: 5px;
        }
        /* Top info layout: photo on right, basic info on left */
        .top-layout-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .top-layout-table td {
            vertical-align: top;
        }
        .photo-box {
            width: 85px;
            height: 100px;
            border: 2px dashed #999;
            text-align: center;
            vertical-align: middle;
            color: #999;
            font-size: 11px;
            padding-top: 38px;
        }
        /* Basic info row */
        .basic-info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .basic-info-table td {
            padding: 5px 7px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        .basic-info-table td.bi-label {
            background: #f0f0f0;
            color: #555;
            font-weight: bold;
            font-size: 9.5px;
            text-transform: uppercase;
            width: 14%;
        }
        .basic-info-table td.bi-value {
            color: #111;
            width: 19%;
        }
        /* Status text colors */
        .status-draft       { color: #888; }
        .status-submitted   { color: #007bff; font-weight: bold; }
        .status-under_review { color: #fd7e14; font-weight: bold; }
        .status-selected    { color: #28a745; font-weight: bold; }
        .status-rejected    { color: #dc3545; font-weight: bold; }
        .status-waitlisted  { color: #6f42c1; font-weight: bold; }
        .status-enrolled    { color: #198754; font-weight: bold; }
        /* Category / entrance block */
        .info-block {
            margin-bottom: 12px;
        }
        .section-header {
            background: #e8eaf6;
            color: #1a237e;
            font-weight: bold;
            font-size: 11px;
            padding: 5px 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0;
            border-left: 4px solid #1a237e;
        }
        .section-data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .section-data-table td {
            padding: 4px 7px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        .section-data-table td.sd-label {
            background: #fafafa;
            color: #555;
            width: 22%;
        }
        .section-data-table td.sd-value {
            color: #111;
            width: 28%;
        }
        /* Data sections (personal, academic, etc.) – 2-col grid of key→value */
        .grid-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .grid-table td {
            padding: 4px 7px;
            border: 1px solid #e0e0e0;
            vertical-align: top;
        }
        .grid-table td.g-label {
            background: #fafafa;
            color: #555;
            width: 18%;
            font-size: 10px;
        }
        .grid-table td.g-value {
            color: #111;
            width: 32%;
            word-break: break-word;
        }
        /* Documents table */
        .docs-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .docs-table th {
            background: #e8eaf6;
            color: #1a237e;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            padding: 5px 7px;
            border: 1px solid #ccc;
            text-align: left;
        }
        .docs-table td {
            padding: 4px 7px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        .docs-table td.doc-status-verified   { color: #28a745; font-weight: bold; }
        .docs-table td.doc-status-rejected   { color: #dc3545; font-weight: bold; }
        .docs-table td.doc-status-pending    { color: #fd7e14; font-weight: bold; }
        /* Declaration */
        .declaration-box {
            border: 1px solid #aaa;
            padding: 10px 12px;
            margin-bottom: 14px;
            background: #fffde7;
        }
        .declaration-title {
            font-weight: bold;
            margin-bottom: 6px;
            color: #555;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .declaration-text {
            font-size: 10.5px;
            color: #333;
            font-style: italic;
        }
        .signature-blank {
            margin-top: 20px;
            border-top: 1px solid #555;
            width: 160px;
            display: inline-block;
        }
        .signature-label {
            font-size: 10px;
            color: #666;
            margin-top: 3px;
        }
        /* Footer */
        .page-footer {
            border-top: 1px solid #ccc;
            padding-top: 6px;
            margin-top: 10px;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }
        .footer-table td {
            font-size: 9.5px;
            color: #888;
            vertical-align: top;
        }
        .footer-right {
            text-align: right;
        }
        .empty-val {
            color: #bbb;
            font-style: italic;
        }
    </style>
</head>
<body>

{{-- ===== HEADER ===== --}}
<div class="header">
    <div class="college-name">{{ $collegeName }}</div>
    <div class="form-subtitle">Application Form</div>
    <div class="header-meta">
        Application No: <strong>{{ $applicant->application_number }}</strong>
        &nbsp;|&nbsp;
        Status:
        @php
            $statusClass = 'status-' . str_replace(' ', '_', strtolower($applicant->status ?? 'draft'));
        @endphp
        <span class="{{ $statusClass }}">{{ ucwords(str_replace('_', ' ', $applicant->status ?? 'Draft')) }}</span>
        &nbsp;|&nbsp;
        Printed On: {{ now()->format('d M Y H:i') }}
    </div>
</div>

{{-- ===== PHOTO PLACEHOLDER + BASIC INFO ===== --}}
<table class="top-layout-table">
    <tr>
        <td style="width: 100%; vertical-align: top;">
            <table class="basic-info-table">
                <tr>
                    <td class="bi-label">App. No.</td>
                    <td class="bi-value">{{ $applicant->application_number ?? 'Application number pending' }}</td>
                    <td class="bi-label">Program</td>
                    <td class="bi-value">{{ $applicant->program->name ?? 'Program not selected' }}</td>
                    <td class="bi-label">Batch</td>
                    <td class="bi-value">{{ $applicant->batch->name ?? 'Batch not selected' }}</td>
                </tr>
                <tr>
                    <td class="bi-label">Status</td>
                    <td class="bi-value">
                        <span class="{{ $statusClass }}">{{ ucwords(str_replace('_', ' ', $applicant->status ?? 'Draft')) }}</span>
                    </td>
                    <td class="bi-label">Submitted At</td>
                    <td class="bi-value" colspan="3">
                        {{ $applicant->registration_fee_paid_at ? $applicant->registration_fee_paid_at->format('d M Y H:i') : 'Not Submitted' }}
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: 95px; padding-left: 10px; vertical-align: top;">
            <div class="photo-box">Photo</div>
        </td>
    </tr>
</table>

{{-- ===== CATEGORY SECTION ===== --}}
@if (!empty($applicant->category))
<div class="section-header">Category Information</div>
<table class="section-data-table">
    <tr>
        <td class="sd-label">Category</td>
        <td class="sd-value">{{ strtoupper($applicant->category) }}</td>
        <td class="sd-label">Sub-Category</td>
        <td class="sd-value">{{ $applicant->sub_category ? strtoupper($applicant->sub_category) : 'Sub-category not selected' }}</td>
    </tr>
    <tr>
        <td class="sd-label">PwD Status</td>
        <td class="sd-value" colspan="3">
            @if ($applicant->is_pwd)
                Yes
                @if ($applicant->pwd_certificate_number)
                    - Cert. No: {{ $applicant->pwd_certificate_number }}
                @endif
            @else
                No
            @endif
        </td>
    </tr>
</table>
@endif

{{-- ===== ENTRANCE EXAM SECTION ===== --}}
@if (!empty($applicant->entrance_exam_name))
<div class="section-header">Entrance Exam Details</div>
<table class="section-data-table">
    <tr>
        <td class="sd-label">Exam Name</td>
        <td class="sd-value">{{ $applicant->entrance_exam_name }}</td>
        <td class="sd-label">Roll No.</td>
        <td class="sd-value">{{ $applicant->entrance_exam_roll_number ?? 'Roll number not recorded' }}</td>
    </tr>
    <tr>
        <td class="sd-label">Score</td>
        <td class="sd-value">{{ $applicant->entrance_exam_score ?? 'Score not recorded' }}</td>
        <td class="sd-label">Rank</td>
        <td class="sd-value">{{ $applicant->entrance_exam_rank ?? 'Rank not recorded' }}</td>
    </tr>
    <tr>
        <td class="sd-label">Exam Date</td>
        <td class="sd-value" colspan="3">
            {{ $applicant->entrance_exam_date ? $applicant->entrance_exam_date->format('d M Y') : 'Exam date not recorded' }}
        </td>
    </tr>
</table>
@endif

{{-- ===== DATA SECTIONS LOOP (Personal, Academic, Family, Additional) ===== --}}
@foreach ($sections as $sectionTitle => $sectionData)
    @if (!empty($sectionData))
    <div class="section-header">{{ $sectionTitle }}</div>
    <table class="grid-table">
        @php
            $items = array_map(null, array_keys($sectionData), array_values($sectionData));
            $pairs = array_chunk($items, 2);
        @endphp
        @foreach ($pairs as $pair)
        <tr>
            @foreach ($pair as $item)
                @if ($item)
                    @php
                        [$key, $val] = $item;
                        $humanKey = str_replace('_', ' ', ucwords($key, '_'));
                        $displayVal = is_array($val) ? implode(', ', $val) : ($val ?? '');
                    @endphp
                    <td class="g-label">{{ $humanKey }}</td>
                    <td class="g-value">
                        @if ($displayVal !== '' && $displayVal !== null)
                            {{ $displayVal }}
                        @else
                            <span class="empty-val">Not provided</span>
                        @endif
                    </td>
                @else
                    <td class="g-label">&nbsp;</td>
                    <td class="g-value">&nbsp;</td>
                @endif
            @endforeach
        </tr>
        @endforeach
    </table>
    @endif
@endforeach

{{-- ===== DOCUMENTS LIST ===== --}}
@if ($applicant->documents->isNotEmpty())
<div class="section-header">Uploaded Documents</div>
<table class="docs-table">
    <thead>
        <tr>
            <th scope="col" style="width:5%;">#</th>
            <th scope="col" style="width:50%;">Document Name</th>
            <th scope="col" style="width:20%;">Status</th>
            <th scope="col" style="width:25%;">Uploaded At</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($applicant->documents as $index => $document)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $document->requiredDocument->name ?? $document->original_name ?? 'Document name not recorded' }}</td>
            <td class="doc-status-{{ $document->status ?? 'pending' }}">
                {{ ucfirst($document->status ?? 'Pending') }}
            </td>
            <td>
                {{ $document->uploaded_at ? $document->uploaded_at->format('d M Y H:i') : 'Upload time not recorded' }}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@else
<div class="section-header">Uploaded Documents</div>
<table class="docs-table">
    <tr>
        <td style="text-align:center; color:#aaa; font-style:italic; padding: 10px;">
            No documents uploaded.
        </td>
    </tr>
</table>
@endif

{{-- ===== DECLARATION ===== --}}
<div class="declaration-box">
    <div class="declaration-title">Declaration</div>
    <div class="declaration-text">
        I hereby declare that all information provided in this application form is true, correct, and complete
        to the best of my knowledge and belief. I understand that any false or misleading information may
        result in the cancellation of my application or admission.
    </div>
    <div style="margin-top: 18px;">
        <div class="signature-blank">&nbsp;</div>
        <div class="signature-label">Signature of Applicant</div>
    </div>
</div>

{{-- ===== FOOTER ===== --}}
<div class="page-footer">
    <table class="footer-table">
        <tr>
            <td>{{ $collegeName }}</td>
            <td class="footer-right">Printed on {{ now()->format('d M Y H:i') }}</td>
        </tr>
    </table>
</div>

</body>
</html>
