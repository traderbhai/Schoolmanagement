<?php

namespace Tests\Feature;

use App\Models\AdmissionFeeInstallment;
use App\Models\AdmissionPayment;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\OfferLetter;
use App\Models\ParentProfile;
use App\Models\RequiredDocument;
use App\Models\Student;
use App\Models\StudentGrievance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalOwnershipBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_cannot_open_unlinked_child_detail_routes(): void
    {
        $parentUser = $this->userWithRole('parent');
        $parent = ParentProfile::create([
            'user_id' => $parentUser->id,
            'relation' => 'Father',
            'phone' => '9999999999',
        ]);

        $linkedChild = Student::factory()->create();
        $unlinkedChild = Student::factory()->create();
        $parent->students()->attach($linkedChild->id);

        $this->actingAs($parentUser)
            ->get(route('parent.children.attendance', $linkedChild))
            ->assertOk();

        foreach (['parent.children.attendance', 'parent.children.results', 'parent.children.fees'] as $routeName) {
            $this->actingAs($parentUser)
                ->get(route($routeName, $unlinkedChild))
                ->assertForbidden();
        }
    }

    public function test_applicant_cannot_open_another_applicants_payment_or_document_routes(): void
    {
        Storage::fake('local');

        [$owner, $other] = $this->applicantPair();
        $payment = $this->admissionPaymentFor($owner);
        $document = $this->documentFor($owner);
        Storage::disk('local')->put($document->file_path, 'private-file');

        $this->actingAs($other->user)
            ->get(route('applicant.fees.show', $payment))
            ->assertForbidden();

        $this->actingAs($other->user)
            ->delete(route('applicant.documents.destroy', $document))
            ->assertForbidden();

        $this->assertDatabaseHas('applicant_documents', [
            'id' => $document->id,
            'applicant_id' => $owner->id,
        ]);
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_applicant_cannot_open_or_mutate_another_applicants_offer(): void
    {
        [$owner, $other] = $this->applicantPair(['status' => 'selected']);
        $offer = OfferLetter::create([
            'applicant_id' => $owner->id,
            'program_id' => $owner->program_id,
            'batch_id' => $owner->batch_id,
            'status' => 'issued',
            'acceptance_deadline' => now()->addDays(7)->toDateString(),
            'issued_by' => User::factory()->create()->id,
        ]);

        $this->actingAs($other->user)
            ->get(route('applicant.offer-letters.show', $offer))
            ->assertRedirect(route('applicant.dashboard'))
            ->assertSessionHas('error', 'Unauthorized.');

        $this->actingAs($other->user)
            ->post(route('applicant.offer-letters.accept', $offer))
            ->assertForbidden();

        $this->actingAs($other->user)
            ->post(route('applicant.offer-letters.decline', $offer), ['reason' => 'Not mine'])
            ->assertForbidden();

        $this->assertSame('issued', $offer->fresh()->status);
    }

    public function test_student_cannot_open_or_mutate_another_students_grievance(): void
    {
        $owner = $this->studentWithRole();
        $other = $this->studentWithRole();
        $grievance = StudentGrievance::create([
            'student_id' => $owner->id,
            'program_id' => $owner->program_id,
            'category' => 'academic',
            'title' => 'Private grievance',
            'description' => 'This should remain private to the owner.',
            'status' => 'resolved',
            'priority' => 'normal',
            'resolution_notes' => 'Resolved by staff.',
            'resolved_at' => now(),
        ]);

        $this->actingAs($other->user)
            ->get(route('student.grievances.show', $grievance))
            ->assertForbidden();

        $this->actingAs($other->user)
            ->post(route('student.grievances.comment', $grievance), ['comment' => 'Unauthorized comment'])
            ->assertForbidden();

        $this->actingAs($other->user)
            ->post(route('student.grievances.close', $grievance))
            ->assertForbidden();

        $this->assertSame('resolved', $grievance->fresh()->status);
        $this->assertDatabaseMissing('grievance_comments', [
            'student_grievance_id' => $grievance->id,
            'comment' => 'Unauthorized comment',
        ]);
    }

    /**
     * @return array{0: Applicant, 1: Applicant}
     */
    private function applicantPair(array $overrides = []): array
    {
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);

        $owner = Applicant::factory()->create(array_merge(['status' => 'selected'], $overrides));
        $other = Applicant::factory()->create(array_merge([
            'program_id' => $owner->program_id,
            'batch_id' => $owner->batch_id,
            'status' => 'selected',
        ], $overrides));

        $owner->user->assignRole('applicant');
        $other->user->assignRole('applicant');

        return [$owner, $other];
    }

    private function admissionPaymentFor(Applicant $applicant): AdmissionPayment
    {
        $installment = AdmissionFeeInstallment::create([
            'program_id' => $applicant->program_id,
            'batch_id' => $applicant->batch_id,
            'name' => 'Admission Confirmation Fee',
            'amount' => 15000,
            'installment_number' => 1,
            'due_date' => now()->addDays(5)->toDateString(),
            'is_active' => true,
        ]);

        return AdmissionPayment::create([
            'applicant_id' => $applicant->id,
            'admission_fee_installment_id' => $installment->id,
            'amount_paid' => 15000,
            'payment_date' => now()->toDateString(),
            'payment_mode' => 'upi',
            'transaction_reference' => 'OWN-' . uniqid(),
            'status' => 'pending',
            'submitted_by' => $applicant->user_id,
        ]);
    }

    private function documentFor(Applicant $applicant): ApplicantDocument
    {
        $requiredDocument = RequiredDocument::create([
            'program_id' => $applicant->program_id,
            'name' => 'Ownership Proof',
            'description' => 'Ownership proof document',
            'is_mandatory' => true,
            'accepted_formats' => 'pdf',
            'is_active' => true,
        ]);

        return ApplicantDocument::create([
            'applicant_id' => $applicant->id,
            'required_document_id' => $requiredDocument->id,
            'file_path' => 'applicant-documents/owner-proof.pdf',
            'original_name' => 'owner-proof.pdf',
            'file_size_kb' => 12,
            'status' => 'pending',
            'uploaded_at' => now(),
            'version' => 1,
        ]);
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function studentWithRole(): Student
    {
        $user = $this->userWithRole('student');

        return Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
    }
}
