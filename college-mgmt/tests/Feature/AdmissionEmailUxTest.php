<?php

namespace Tests\Feature;

use App\Mail\ApplicationReceived;
use App\Mail\ApplicationRejected;
use App\Mail\ApplicationSelected;
use App\Mail\ApplicationShortlisted;
use App\Mail\NewApplicationAlert;
use App\Mail\PaymentRejected;
use App\Mail\PaymentVerified;
use App\Mail\SessionScheduled;
use App\Models\AdmissionFeeInstallment;
use App\Models\AdmissionPayment;
use App\Models\Applicant;
use App\Models\Batch;
use App\Models\EnrollmentConfirmation;
use App\Models\OfferLetter;
use App\Models\Program;
use App\Models\SelectionProcessStep;
use App\Models\SelectionSession;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionEmailUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_admission_email_subjects_and_bodies_use_readable_applicant_facing_labels(): void
    {
        $program = Program::factory()->create(['name' => 'Email UX Program']);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'name' => 'Email UX Batch']);
        $applicant = Applicant::factory()->create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'application_number' => 'APP-EMAIL-001',
        ])->load(['user', 'program', 'batch']);
        $staff = User::factory()->create(['name' => 'Admission Counsellor']);

        $step = SelectionProcessStep::create([
            'program_id' => $program->id,
            'name' => 'Personal Interview',
            'type' => 'pi',
            'step_order' => 1,
            'max_score' => 100,
            'weightage' => 50,
            'is_active' => true,
        ]);
        $session = SelectionSession::create([
            'selection_process_step_id' => $step->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'session_name' => 'PI Panel A',
            'scheduled_date' => today()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'venue' => null,
            'status' => 'scheduled',
            'created_by' => $staff->id,
        ])->load('step');

        $installment = AdmissionFeeInstallment::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'name' => 'Admission Confirmation Fee',
            'amount' => 25000,
            'installment_number' => 1,
            'is_active' => true,
        ]);
        $payment = AdmissionPayment::create([
            'applicant_id' => $applicant->id,
            'admission_fee_installment_id' => $installment->id,
            'amount_paid' => 25000,
            'payment_date' => today(),
            'payment_mode' => 'upi',
            'transaction_reference' => 'UPI-EMAIL-001',
            'status' => 'verified',
            'verified_by' => $staff->id,
            'verified_at' => now(),
            'submitted_by' => $applicant->user_id,
        ]);

        $mailables = [
            new ApplicationReceived(['applicant' => $applicant]),
            new ApplicationShortlisted(['applicant' => $applicant]),
            new ApplicationSelected(['applicant' => $applicant, 'meritRank' => 7]),
            new ApplicationRejected(['applicant' => $applicant]),
            new NewApplicationAlert(['applicant' => $applicant]),
            new SessionScheduled(['applicant' => $applicant, 'session' => $session]),
            new PaymentVerified([
                'applicant' => $applicant,
                'payment' => $payment,
                'installmentName' => $installment->name,
            ]),
            new PaymentRejected([
                'applicant' => $applicant,
                'payment' => $payment,
                'installmentName' => $installment->name,
                'reason' => 'Reference could not be matched.',
            ]),
        ];

        foreach ($mailables as $mailable) {
            $this->assertCleanAdmissionEmailText($mailable->envelope()->subject);
            $this->assertCleanAdmissionEmailText($mailable->render());
        }

        $offer = OfferLetter::create([
            'applicant_id' => $applicant->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'issued',
            'acceptance_deadline' => today()->addWeek(),
            'issued_by' => $staff->id,
            'issued_at' => now(),
        ])->load(['program', 'batch']);
        $student = Student::factory()->create([
            'user_id' => $applicant->user_id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
        ])->load(['user', 'program', 'batch']);
        $enrollment = EnrollmentConfirmation::create([
            'applicant_id' => $applicant->id,
            'student_id' => $student->id,
            'confirmed_by' => $staff->id,
            'confirmed_at' => now(),
            'enrollment_number' => 'ENR-EMAIL-001',
            'roll_number' => 'ROLL-EMAIL-001',
            'batch_id' => $batch->id,
            'status' => 'completed',
        ])->load('student.program', 'student.batch');

        $directViews = [
            view('emails.admission.status-changed', [
                'applicant' => $applicant,
                'newStatus' => 'selected',
                'title' => 'Application Selected',
                'message' => 'Your application has moved to selected status.',
            ])->render(),
            view('emails.admission.offer-issued', [
                'applicant' => $applicant,
                'offerLetter' => $offer,
            ])->render(),
            view('emails.admission.enrollment-confirmed', [
                'enrollment' => $enrollment,
                'loginUrl' => 'https://example.test/student/login',
            ])->render(),
            view('emails.admission-team.followup-reminder', [
                'applicant' => $applicant,
                'counsellor' => $staff,
            ])->render(),
        ];

        foreach ($directViews as $html) {
            $this->assertCleanAdmissionEmailText($html);
        }
    }

    private function assertCleanAdmissionEmailText(string $content): void
    {
        $this->assertStringNotContainsString('N/A', $content);
        $this->assertStringNotContainsString('â', $content);
        $this->assertStringNotContainsString('Ã', $content);
        $this->assertStringNotContainsString('—', $content);
        $this->assertStringNotContainsString('₹', $content);
        $this->assertStringNotContainsString('✓', $content);
    }
}
