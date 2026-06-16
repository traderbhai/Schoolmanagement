<?php

namespace Tests\Feature;

use App\Mail\GenericBulkMail;
use App\Models\{Applicant, Batch, EmailLog, Program, Student, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminBulkMailWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function studentUser(Program $program, Batch $batch, string $status = 'active'): User
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('student');

        Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => $status,
        ]);

        return $user;
    }

    public function test_bulk_mail_active_student_count_excludes_inactive_student_profiles(): void
    {
        $admin = $this->admin();
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);

        $this->studentUser($program, $batch, 'active');
        $this->studentUser($program, $batch, 'inactive');

        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);
        $applicantUser = User::factory()->create();
        $applicantUser->assignRole('applicant');
        Applicant::factory()->create(['user_id' => $applicantUser->id, 'status' => 'submitted']);

        $this->actingAs($admin)
            ->getJson(route('admin.bulk-mail.count', ['audience' => 'all_students']))
            ->assertOk()
            ->assertJson(['count' => 1]);
    }

    public function test_bulk_mail_send_chunks_only_matching_active_program_batch_students(): void
    {
        Mail::fake();

        $admin = $this->admin();
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $otherProgram = Program::factory()->create();
        $otherBatch = Batch::factory()->create(['program_id' => $otherProgram->id]);

        $eligible = $this->studentUser($program, $batch, 'active');
        $this->studentUser($program, $batch, 'inactive');
        $this->studentUser($otherProgram, $otherBatch, 'active');

        $this->actingAs($admin)
            ->post(route('admin.bulk-mail.send'), [
                'audience' => 'program_batch',
                'program_id' => $program->id,
                'batch_id' => $batch->id,
                'subject' => 'Semester readiness update',
                'body' => 'Please review your timetable and academic calendar.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Bulk email queued for 1 recipient(s).');

        Mail::assertQueued(GenericBulkMail::class, 1);
        $this->assertDatabaseHas('email_logs', [
            'mailable_class' => GenericBulkMail::class,
            'to_email' => $eligible->email,
            'status' => 'queued',
        ]);
        $this->assertSame(1, EmailLog::count());
    }
}
