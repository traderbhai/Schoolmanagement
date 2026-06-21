<?php

namespace Tests\Feature;

use App\Models\AdmissionFeeInstallment;
use App\Models\AdmissionPayment;
use App\Models\Applicant;
use App\Models\Batch;
use App\Models\Program;
use App\Models\User;
use App\Support\InstitutionSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionFeeReceiptBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        @unlink(storage_path('app/settings.json'));

        parent::tearDown();
    }

    public function test_admission_receipt_footer_uses_configured_institution_profile(): void
    {
        file_put_contents(storage_path('app/settings.json'), json_encode([
            'institute_name' => 'North Campus Institute',
            'address' => '12 Knowledge Park, Pune, Maharashtra 411001',
            'phone' => '+91-20-4000-1000',
            'email' => 'accounts@nci.test',
            'website' => 'https://nci.test',
        ]));

        $footer = InstitutionSettings::footerLine();

        $this->assertStringContainsString('North Campus Institute', $footer);
        $this->assertStringContainsString('12 Knowledge Park, Pune, Maharashtra 411001', $footer);
        $this->assertStringContainsString('+91-20-4000-1000', $footer);
        $this->assertStringContainsString('accounts@nci.test', $footer);
        $this->assertStringNotContainsString('Placeholder', $footer);
    }

    public function test_admission_fee_receipt_template_has_no_visible_address_placeholder(): void
    {
        $template = file_get_contents(resource_path('views/admission/fee-receipts/template.blade.php'));

        $this->assertStringNotContainsString('[College Address Placeholder]', $template);
        $this->assertStringNotContainsString('[City, State, PIN]', $template);
        $this->assertStringContainsString('$collegeFooterLine', $template);
    }

    public function test_admission_fee_receipt_template_uses_readable_payment_labels(): void
    {
        $program = Program::factory()->create(['is_active' => true]);
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $user = User::factory()->create([
            'name' => 'Receipt Applicant',
            'email' => 'receipt-applicant@example.test',
        ]);
        $applicant = Applicant::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'application_number' => 'APP-RECEIPT-001',
            'status' => 'submitted',
        ]);
        $installment = AdmissionFeeInstallment::create([
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'name' => 'Registration Fee',
            'amount' => 12500,
            'installment_number' => 1,
            'due_date' => now()->addDays(7),
            'is_active' => true,
        ]);

        $payment = AdmissionPayment::create([
            'applicant_id' => $applicant->id,
            'admission_fee_installment_id' => $installment->id,
            'amount_paid' => 12500,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'upi',
            'transaction_reference' => 'UPI-12345',
            'status' => 'verified',
            'submitted_by' => $user->id,
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        $payment->load('applicant.user', 'applicant.program', 'applicant.batch');

        $html = view('admission.fee-receipts.template', [
            'payment' => $payment,
            'applicant' => $payment->applicant,
            'collegeName' => 'Demo Institute',
            'collegeFooterLine' => 'Demo Institute | 1 Test Road | accounts@example.test',
            'receiptNo' => 'RCP-000001',
        ])->render();

        $this->assertStringContainsString('UPI', $html);
        $this->assertStringContainsString('UPI-12345', $html);
        $this->assertStringContainsString('APP-RECEIPT-001', $html);
        $this->assertStringNotContainsString('Payment method not recorded', $html);
        $this->assertStringNotContainsString('Reference not recorded', $html);
        $this->assertStringNotContainsString('N/A', $html);
        $this->assertStringNotContainsString('â', $html);
        $this->assertStringNotContainsString('Ã', $html);
        $this->assertStringNotContainsString('Â', $html);

        $applicant->setRelation('program', null);
        $applicant->setRelation('batch', null);
        $applicant->setRelation('user', null);
        $applicant->application_number = null;
        $payment->payment_mode = null;
        $payment->transaction_reference = null;
        $payment->verified_at = null;
        $payment->setRelation('applicant', $applicant);

        $fallbackHtml = view('admission.fee-receipts.template', [
            'payment' => $payment,
            'applicant' => $applicant,
            'collegeName' => 'Demo Institute',
            'collegeFooterLine' => 'Demo Institute | 1 Test Road | accounts@example.test',
            'receiptNo' => 'RCP-000002',
        ])->render();

        $this->assertStringContainsString('Application number pending', $fallbackHtml);
        $this->assertStringContainsString('Applicant name not recorded', $fallbackHtml);
        $this->assertStringContainsString('Program not selected', $fallbackHtml);
        $this->assertStringContainsString('Batch not selected', $fallbackHtml);
        $this->assertStringContainsString('Email not recorded', $fallbackHtml);
        $this->assertStringContainsString('Payment method not recorded', $fallbackHtml);
        $this->assertStringContainsString('Reference not recorded', $fallbackHtml);
        $this->assertStringContainsString('Verification time not recorded', $fallbackHtml);
        $this->assertStringNotContainsString('N/A', $fallbackHtml);
        $this->assertStringNotContainsString('â', $fallbackHtml);
        $this->assertStringNotContainsString('Ã', $fallbackHtml);
        $this->assertStringNotContainsString('Â', $fallbackHtml);
    }
}
