<?php

namespace App\Services;

use App\Mail\EnrollmentConfirmed;
use App\Models\{Applicant, Student, User, EnrollmentConfirmation, ActivityLog, RequiredDocument};
use App\Services\NotificationService;
use Illuminate\Support\Facades\{DB, Hash};

class EnrollmentService
{
    public function enroll(Applicant $applicant, array $data, int $confirmedBy): EnrollmentConfirmation
    {
        return DB::transaction(function () use ($applicant, $data, $confirmedBy) {
            $applicant = Applicant::query()
                ->whereKey($applicant->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 1. Validate
            if ($applicant->status !== 'selected') {
                throw new \RuntimeException('Applicant must have status "selected" to be enrolled.');
            }

            $hasVerifiedPayment = $applicant->payments()->where('status', 'verified')->exists();
            if (!$hasVerifiedPayment) {
                throw new \RuntimeException('Applicant does not have a verified admission payment.');
            }

            $mandatoryDocumentIds = RequiredDocument::where('program_id', $applicant->program_id)
                ->where('is_mandatory', true)
                ->where('is_active', true)
                ->pluck('id');

            if ($mandatoryDocumentIds->isNotEmpty()) {
                $verifiedMandatoryCount = $applicant->documents()
                    ->whereIn('required_document_id', $mandatoryDocumentIds)
                    ->where('status', 'verified')
                    ->count();

                if ($verifiedMandatoryCount < $mandatoryDocumentIds->count()) {
                    throw new \RuntimeException('Applicant must have all mandatory documents verified before enrollment.');
                }
            }

            if ($applicant->enrollmentConfirmation()->exists()) {
                throw new \RuntimeException('Applicant already has an enrollment confirmation.');
            }

            // 2. Generate enrollment number
            $programCode = $applicant->program->code ?? 'GEN';
            $enrollmentNumber = 'ENR-' . date('Y') . '-' . $programCode . '-' . str_pad($applicant->id, 5, '0', STR_PAD_LEFT);

            // 3. Get or create User
            $personal = $applicant->personal_data ?? [];
            $name  = $personal['name']  ?? $personal['full_name'] ?? 'Applicant ' . $applicant->id;
            $email = $personal['email'] ?? ($applicant->application_number . '@student.edu');

            if ($applicant->user_id) {
                $user = User::findOrFail($applicant->user_id);
            } else {
                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name'               => $name,
                        'password'           => Hash::make($applicant->application_number),
                        'email_verified_at'  => now(),
                    ]
                );
                $applicant->update(['user_id' => $user->id]);
            }

            // 4. Create Student
            $family   = $applicant->family_data    ?? [];
            $academic = $applicant->academic_data  ?? [];

            // Resolve batch_id early for Student creation too
            $batchId = $applicant->batch_id
                ?? \App\Models\Batch::where('program_id', $applicant->program_id)->value('id')
                ?? \App\Models\Batch::first()?->id;

            if (Student::where('batch_id', $batchId)->where('roll_number', $data['roll_number'])->exists()) {
                throw new \RuntimeException('Roll number is already assigned to another student in this batch.');
            }

            // Resolve course_id: match course by code or pick first course for department
            $program = $applicant->program;
            $course = \App\Models\Course::where('code', $program->code)->first()
                ?? \App\Models\Course::where('department_id', $program->department_id)->first()
                ?? \App\Models\Course::first();

            $student = Student::create([
                'user_id'          => $user->id,
                'course_id'        => $course?->id,
                'program_id'       => $applicant->program_id,
                'batch_id'         => $batchId,
                'enrollment_number'=> $enrollmentNumber,
                'roll_number'      => $data['roll_number'],
                'specialization_id'=> $data['specialization_id'] ?? null,
                'date_of_birth'    => $personal['date_of_birth'] ?? null,
                'gender'           => $personal['gender'] ?? null,
                'phone'            => $personal['phone'] ?? $personal['mobile'] ?? null,
                'address'          => $personal['address'] ?? null,
                'guardian_name'    => $family['guardian_name'] ?? $family['father_name'] ?? null,
                'guardian_phone'   => $family['guardian_phone'] ?? $family['father_phone'] ?? null,
                'admission_date'   => now()->toDateString(),
                'current_semester' => 1,
                'current_term'     => 1,
                'status'           => 'active',
                'department_id'    => $program->department_id ?? null,
            ]);

            // 5. Assign student role, remove applicant role
            $user->removeRole('applicant');
            $user->assignRole('student');

            // 6. Create EnrollmentConfirmation
            $confirmation = EnrollmentConfirmation::create([
                'applicant_id'      => $applicant->id,
                'student_id'        => $student->id,
                'confirmed_by'      => $confirmedBy,
                'confirmed_at'      => now(),
                'enrollment_number' => $enrollmentNumber,
                'roll_number'       => $data['roll_number'],
                'batch_id'          => $batchId,
                'term_id'           => $data['term_id'] ?? null,
                'notes'             => $data['notes'] ?? null,
                'status'            => 'completed',
            ]);

            // 7. Activity log
            ActivityLog::record('enrollment', "Enrolled applicant {$applicant->application_number} as student {$enrollmentNumber}", $confirmation);

            // 8. Send enrollment confirmation email
            $confirmation->load(['student.user', 'student.program', 'student.batch']);
            NotificationService::send(EnrollmentConfirmed::class, $user, [
                'enrollment' => $confirmation,
                'loginUrl'   => url('/login'),
            ]);

            app(AdmissionHandoffService::class)->ensure($applicant->fresh(), $confirmation, User::find($confirmedBy));

            return $confirmation;
        });
    }
}
