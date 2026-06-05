<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            width: 100%;
            padding: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #007bff;
            font-size: 24px;
            margin-bottom: 5px;
        }
        .header p {
            color: #666;
            font-size: 12px;
        }
        .content {
            margin-bottom: 40px;
        }
        .greeting {
            margin-bottom: 20px;
        }
        .greeting p {
            margin-bottom: 10px;
        }
        .offer-box {
            border: 2px solid #007bff;
            padding: 20px;
            margin: 20px 0;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .offer-box h3 {
            color: #007bff;
            margin-bottom: 15px;
            font-size: 16px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: bold;
            width: 40%;
        }
        .detail-value {
            width: 60%;
        }
        .terms {
            margin: 30px 0;
            font-size: 12px;
        }
        .terms h3 {
            color: #007bff;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .terms ul {
            margin-left: 20px;
            margin-bottom: 10px;
        }
        .terms li {
            margin-bottom: 8px;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
            font-size: 12px;
            color: #666;
        }
        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        .signature {
            width: 40%;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 30px;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>OFFER LETTER</h1>
            <p>Admission to Academic Program</p>
        </div>

        <div class="content">
            <div class="greeting">
                <p>Dear <strong>{{ $offerLetter->applicant->user->name }}</strong>,</p>
                <p style="margin-top: 15px;">
                    Congratulations! We are pleased to inform you that you have been selected for admission to the <strong>{{ $offerLetter->program->name }}</strong> program at our institution.
                </p>
            </div>

            <div class="offer-box">
                <h3>OFFER DETAILS</h3>
                <div class="detail-row">
                    <span class="detail-label">Offer Number:</span>
                    <span class="detail-value">{{ $offerLetter->offer_number }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Program:</span>
                    <span class="detail-value">{{ $offerLetter->program->name }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Batch:</span>
                    <span class="detail-value">{{ $offerLetter->batch->name }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Application Number:</span>
                    <span class="detail-value">{{ $offerLetter->applicant->application_number }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date of Offer:</span>
                    <span class="detail-value">{{ $offerLetter->issued_at->format('d M Y') }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Acceptance Deadline:</span>
                    <span class="detail-value" style="color: #007bff; font-weight: bold;">{{ $offerLetter->acceptance_deadline->format('d M Y') }}</span>
                </div>
            </div>

            <div class="greeting" style="margin-top: 20px;">
                <p>
                    This offer is contingent upon:
                </p>
            </div>

            <div class="terms">
                <ul>
                    <li>Verification of all submitted documents and academic credentials</li>
                    <li>Successful completion of all prescribed qualifying examinations</li>
                    <li>Compliance with all admission terms and conditions</li>
                    <li>Satisfactory conduct and background clearance</li>
                    <li>Payment of admission fees as per the fee schedule</li>
                </ul>
            </div>

            <div class="terms">
                <h3>ACTION REQUIRED</h3>
                <p>
                    You must accept or decline this offer by <strong>{{ $offerLetter->acceptance_deadline->format('d M Y') }}</strong>.
                    Please log in to your applicant portal to respond to this offer. Failure to respond by the deadline may result in forfeiture of your admission.
                </p>
            </div>

            <div class="signature-section">
                <div class="signature">
                    <div class="signature-line">
                        Admission Officer
                    </div>
                </div>
                <div class="signature">
                    <div class="signature-line">
                        Date
                    </div>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>This is a system-generated document. No signature is required for digital offer letters.</p>
            <p style="margin-top: 10px;">For queries, contact: admission@institution.edu | Phone: +1-XXXX-XXXXXX</p>
        </div>
    </div>
</body>
</html>
