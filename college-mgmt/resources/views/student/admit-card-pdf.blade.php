<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admit Card — {{ $exam->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #1a1a1a; background: #fff; }

        .outer { border: 3px solid #1e40af; border-radius: 4px; overflow: hidden; }

        .header { background: #1e40af; color: #fff; text-align: center; padding: 14px 16px; }
        .header .inst { font-size: 15px; font-weight: 700; letter-spacing: .5px; }
        .header .sub  { font-size: 9px; opacity: .8; margin-top: 2px; }
        .header .doc  { font-size: 11px; font-weight: 700; margin-top: 6px; text-transform: uppercase;
                        letter-spacing: 1.2px; border-top: 1px solid rgba(255,255,255,.3); padding-top: 6px; }

        .body { padding: 14px 16px; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .info-table td { padding: 5px 8px; border: 1px solid #e2e8f0; font-size: 9.5px; }
        .info-table .lbl { color: #64748b; font-size: 8px; text-transform: uppercase; letter-spacing: .4px;
                           background: #f8fafc; width: 36%; font-weight: 600; }

        .exam-box { border: 2px solid #1e40af; border-radius: 3px; padding: 10px 12px; margin-bottom: 14px; background: #eff6ff; }
        .exam-title { font-size: 12px; font-weight: 700; color: #1e40af; margin-bottom: 8px; }
        .exam-grid { display: table; width: 100%; }
        .exam-row  { display: table-row; }
        .exam-cell { display: table-cell; width: 50%; padding: 3px 0; font-size: 9px; }
        .exam-cell .key { color: #64748b; font-size: 8px; text-transform: uppercase; }
        .exam-cell .val { font-weight: 700; margin-top: 1px; }

        .instructions { background: #fefce8; border: 1px solid #fde047; border-radius: 3px;
                        padding: 8px 10px; margin-bottom: 14px; }
        .instructions .title { font-size: 9px; font-weight: 700; color: #854d0e; margin-bottom: 5px;
                                text-transform: uppercase; letter-spacing: .4px; }
        .instructions ol { margin: 0; padding-left: 14px; }
        .instructions li { font-size: 8.5px; color: #713f12; margin-bottom: 2px; }

        .sig-row { display: table; width: 100%; margin-top: 16px; }
        .sig-cell { display: table-cell; width: 50%; text-align: center; font-size: 8px; color: #64748b; }
        .sig-line { border-top: 1px solid #94a3b8; width: 100px; margin: 20px auto 4px; }

        .barcode-placeholder { text-align: center; margin-bottom: 8px; }
        .barcode-text { font-size: 8px; letter-spacing: 3px; color: #1e40af; font-weight: 700;
                        border: 1px solid #1e40af; display: inline-block; padding: 3px 10px; border-radius: 2px; }

        .footer-strip { background: #1e40af; color: rgba(255,255,255,.7); text-align: center;
                        padding: 4px; font-size: 7.5px; }
    </style>
</head>
<body>
<div class="outer">
    <div class="header">
        <div class="inst">EduManage Institute</div>
        <div class="sub">Autonomous Institute | Affiliated to State University</div>
        <div class="doc">Hall Admission Ticket</div>
    </div>

    <div class="body">
        {{-- Student Info --}}
        <table class="info-table">
            <tr>
                <td class="lbl">Student Name</td>
                <td>{{ $student->user->name ?? '—' }}</td>
                <td class="lbl">Enrollment No.</td>
                <td>{{ $student->enrollment_number ?? '—' }}</td>
            </tr>
            <tr>
                <td class="lbl">Program</td>
                <td>{{ $student->program->name ?? '—' }}</td>
                <td class="lbl">Roll No.</td>
                <td>{{ $student->roll_number ?? ($student->enrollment_number ?? '—') }}</td>
            </tr>
            <tr>
                <td class="lbl">Batch</td>
                <td>{{ $student->batch->name ?? '—' }}</td>
                <td class="lbl">Academic Year</td>
                <td>{{ $exam->semester->academicYear->name ?? ($exam->term->name ?? now()->year) }}</td>
            </tr>
        </table>

        {{-- Exam Details --}}
        <div class="exam-box">
            <div class="exam-title">{{ $exam->name }}</div>
            <div class="exam-grid">
                <div class="exam-row">
                    <div class="exam-cell">
                        <div class="key">Subject</div>
                        <div class="val">{{ $exam->subject->name ?? '—' }}</div>
                    </div>
                    <div class="exam-cell">
                        <div class="key">Subject Code</div>
                        <div class="val">{{ $exam->subject->code ?? '—' }}</div>
                    </div>
                </div>
                <div class="exam-row">
                    <div class="exam-cell">
                        <div class="key">Exam Date</div>
                        <div class="val">{{ $exam->exam_date->format('l, d F Y') }}</div>
                    </div>
                    <div class="exam-cell">
                        <div class="key">Time</div>
                        <div class="val">
                            @if($exam->start_time && $exam->end_time)
                                {{ \Carbon\Carbon::parse($exam->start_time)->format('h:i A') }}
                                &mdash;
                                {{ \Carbon\Carbon::parse($exam->end_time)->format('h:i A') }}
                            @else
                                —
                            @endif
                        </div>
                    </div>
                </div>
                <div class="exam-row">
                    <div class="exam-cell">
                        <div class="key">Venue</div>
                        <div class="val">{{ $exam->classroom->name ?? 'TBA' }}</div>
                    </div>
                    <div class="exam-cell">
                        <div class="key">Max Marks / Passing</div>
                        <div class="val">{{ $exam->total_marks ?? '—' }} / {{ $exam->passing_marks ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Barcode placeholder --}}
        <div class="barcode-placeholder">
            <div class="barcode-text">{{ strtoupper($student->enrollment_number ?? 'ENR-0000') }}-{{ $exam->id }}</div>
        </div>

        {{-- Instructions --}}
        <div class="instructions">
            <div class="title">Instructions to Candidates</div>
            <ol>
                <li>Bring this admit card to the examination hall. No entry without it.</li>
                <li>Carry a valid photo ID (college ID / government ID) along with this card.</li>
                <li>Report at least 15 minutes before the scheduled start time.</li>
                <li>Electronic devices (mobile phones, smartwatches) are strictly prohibited.</li>
                <li>Use only black/blue ballpoint pen. Pencil is not permitted except for diagrams.</li>
                <li>Seating arrangement will be displayed on the notice board.</li>
            </ol>
        </div>

        {{-- Signatures --}}
        <div class="sig-row">
            <div class="sig-cell">
                <div class="sig-line"></div>
                Student Signature
            </div>
            <div class="sig-cell">
                <div class="sig-line"></div>
                Controller of Examinations
            </div>
        </div>
    </div>

    <div class="footer-strip">
        Generated on {{ now()->format('d M Y, H:i') }} &mdash; This is a computer-generated admit card. Valid only with institutional seal.
    </div>
</div>
</body>
</html>
