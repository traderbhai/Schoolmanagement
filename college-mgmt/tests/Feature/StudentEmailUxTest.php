<?php

namespace Tests\Feature;

use App\Mail\ExamResultPublished;
use App\Mail\ExamResultsMail;
use App\Mail\FeeDueReminder;
use App\Mail\FeePaymentReceipt;
use App\Mail\FeeReceiptMail;
use App\Mail\LowAttendanceAlert;
use App\Models\FeePayment;
use App\Models\Student;
use Tests\TestCase;

class StudentEmailUxTest extends TestCase
{
    public function test_student_email_templates_use_readable_missing_data_labels(): void
    {
        $student = (object) ['user' => (object) ['name' => null]];

        $renders = [
            view('emails.student.result-published', [
                'student' => $student,
                'exam' => (object) ['name' => null],
                'result' => (object) [
                    'marks_obtained' => null,
                    'total_marks' => null,
                    'grade' => null,
                    'passing_marks' => null,
                ],
            ])->render(),
            view('emails.student.low-attendance-alert', [
                'student' => $student,
                'subject' => (object) ['name' => null],
                'attendance_percentage' => 61.5,
            ])->render(),
            view('emails.student.notice-published', [
                'notice' => (object) [
                    'title' => null,
                    'published_at' => null,
                    'content' => null,
                ],
            ])->render(),
            view('emails.student.fee-due-reminder', [
                'student' => $student,
                'feeStructure' => (object) [
                    'amount' => 12500,
                    'due_date' => null,
                ],
                'daysUntilDue' => null,
            ])->render(),
            view('emails.student.fee-receipt', [
                'student' => $student,
                'feePayment' => (object) [
                    'receipt_number' => null,
                    'id' => null,
                    'amount' => 12500,
                    'paid_at' => null,
                    'payment_mode' => null,
                ],
            ])->render(),
        ];

        $combined = implode("\n---EMAIL---\n", $renders);

        $this->assertStringContainsString('Exam name pending', $combined);
        $this->assertStringContainsString('Marks pending / Total marks pending', $combined);
        $this->assertStringContainsString('Grade pending', $combined);
        $this->assertStringContainsString('Subject not linked', $combined);
        $this->assertStringContainsString('Notice title pending', $combined);
        $this->assertStringContainsString('Notice content pending.', $combined);
        $this->assertStringContainsString('Rs. 12,500.00', $combined);
        $this->assertStringContainsString('Due date not published', $combined);
        $this->assertStringContainsString('Receipt number pending', $combined);
        $this->assertStringContainsString('Payment mode pending', $combined);
        $this->assertStringContainsString(config('app.name') . ' - All rights reserved.', $combined);
        $this->assertStringNotContainsString('N/A', $combined);
        $this->assertStringNotContainsString('â', $combined);
        $this->assertStringNotContainsString('Â', $combined);
        $this->assertStringNotContainsString('&mdash;', $combined);
        $this->assertStringNotContainsString('&ndash;', $combined);
        $this->assertStringNotContainsString('&bull;', $combined);
    }

    public function test_student_mail_subjects_are_ascii_safe_and_readable(): void
    {
        $subjects = [
            (new ExamResultPublished(['exam' => (object) ['name' => 'Term Final']]))->envelope()->subject,
            (new ExamResultsMail(new Student, 'Semester 2', 8.1, 'Pass'))->envelope()->subject,
            (new FeeDueReminder(['feeStructure' => (object) ['due_date' => null]]))->envelope()->subject,
            (new FeePaymentReceipt(['feePayment' => (object) ['amount' => 12500]]))->envelope()->subject,
            (new FeeReceiptMail(new FeePayment(['receipt_number' => null])))->envelope()->subject,
            (new LowAttendanceAlert([
                'subject' => (object) ['name' => 'Operations Research'],
                'attendance_percentage' => 72.5,
            ]))->envelope()->subject,
        ];

        $combined = implode("\n", $subjects);

        $this->assertStringContainsString('Results Published - Term Final', $combined);
        $this->assertStringContainsString('Your Exam Results are Available - Semester 2', $combined);
        $this->assertStringContainsString('Fee Payment Reminder - Due soon', $combined);
        $this->assertStringContainsString('Fee Payment Receipt - Rs. 12,500.00', $combined);
        $this->assertStringContainsString('Fee Payment Confirmation - Receipt #pending', $combined);
        $this->assertStringContainsString('Attendance Alert - Operations Research: 72.5%', $combined);
        $this->assertStringNotContainsString('N/A', $combined);
        $this->assertStringNotContainsString('â', $combined);
        $this->assertStringNotContainsString('Â', $combined);
        $this->assertStringNotContainsString('₹', $combined);
        $this->assertStringNotContainsString('—', $combined);
        $this->assertStringNotContainsString('–', $combined);
    }

    public function test_legacy_shared_student_email_views_use_readable_fallbacks(): void
    {
        $student = (object) [
            'user' => (object) [
                'name' => null,
                'email' => null,
            ],
            'enrollment_number' => null,
        ];
        $payment = (object) [
            'student' => $student,
            'feeStructure' => (object) ['fee_type' => null],
            'receipt_number' => null,
            'amount_paid' => 12500,
            'payment_date' => null,
            'payment_method' => null,
        ];
        $notice = (object) [
            'title' => null,
            'publish_date' => null,
            'content' => null,
        ];

        $renders = [
            view('emails.exam-results', [
                'student' => $student,
                'semesterName' => '',
                'sgpa' => null,
                'overallResult' => '',
            ])->render(),
            view('emails.fee-receipt', ['payment' => $payment])->render(),
            view('emails.notice', ['notice' => $notice])->render(),
            view('emails.welcome-student', ['student' => $student])->render(),
        ];

        $combined = implode("\n---LEGACY-EMAIL---\n", $renders);

        $this->assertStringContainsString('Semester not linked', $combined);
        $this->assertStringContainsString('SGPA pending', $combined);
        $this->assertStringContainsString('Result status pending', $combined);
        $this->assertStringContainsString('Receipt number pending', $combined);
        $this->assertStringContainsString('Fee type not linked', $combined);
        $this->assertStringContainsString('Rs. 12,500.00', $combined);
        $this->assertStringContainsString('Payment date pending', $combined);
        $this->assertStringContainsString('Payment method pending', $combined);
        $this->assertStringContainsString('Notice title pending', $combined);
        $this->assertStringContainsString('Publish date pending', $combined);
        $this->assertStringContainsString('Notice content pending.', $combined);
        $this->assertStringContainsString('Enrollment number pending', $combined);
        $this->assertStringContainsString('Login email pending', $combined);
        $this->assertStringNotContainsString('N/A', $combined);
        $this->assertStringNotContainsString('â', $combined);
        $this->assertStringNotContainsString('Â', $combined);
        $this->assertStringNotContainsString('₹', $combined);
        $this->assertStringNotContainsString('&#8377;', $combined);
        $this->assertStringNotContainsString('&mdash;', $combined);
        $this->assertStringNotContainsString('&ndash;', $combined);
        $this->assertStringNotContainsString('&bull;', $combined);
    }
}
