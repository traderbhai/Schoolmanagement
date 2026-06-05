<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .content {
            margin-bottom: 20px;
        }
        .offer-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
        }
        .button.accept {
            background-color: #28a745;
        }
        .button.decline {
            background-color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="color: #007bff; margin: 0;">Congratulations!</h1>
            <p style="margin: 0; color: #666;">Your Admission Offer Letter</p>
        </div>

        <div class="content">
            <p>Dear {{ $offerLetter->applicant->user->name }},</p>

            <p>We are pleased to inform you that you have been selected for admission to <strong>{{ $offerLetter->program->name }}</strong> at our institution.</p>

            <div class="offer-details">
                <h3 style="margin-top: 0;">Offer Details</h3>
                <p><strong>Offer Number:</strong> {{ $offerLetter->offer_number }}</p>
                <p><strong>Program:</strong> {{ $offerLetter->program->name }}</p>
                <p><strong>Batch:</strong> {{ $offerLetter->batch->name }}</p>
                <p><strong>Acceptance Deadline:</strong> {{ $offerLetter->acceptance_deadline->format('d M Y') }}</p>
            </div>

            <p>Please log in to your applicant portal to accept or decline this offer. You have until <strong>{{ $offerLetter->acceptance_deadline->format('d M Y') }}</strong> to respond.</p>

            <p style="text-align: center;">
                <a href="{{ route('applicant.offer-letters.show', $offerLetter) }}" class="button accept">View Offer Letter</a>
            </p>

            <p>If you have any questions, please contact our admission office.</p>

            <p>Best regards,<br>Admission Team</p>
        </div>
    </div>
</body>
</html>
