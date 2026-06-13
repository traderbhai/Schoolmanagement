<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\Notification;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Mail\StudentDocumentRequestUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminStudentDocumentRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function student(): Student
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => 'Asha Student']);
        $user->assignRole('student');

        return Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => Program::factory()->create(['name' => 'BCA'])->id,
            'enrollment_number' => 'ENR-1001',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_review_student_document_request_queue(): void
    {
        $student = $this->student();

        DocumentRequest::create([
            'student_id' => $student->id,
            'document_type' => 'bonafide',
            'purpose' => 'Scholarship',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.document-requests.index'))
            ->assertStatus(200)
            ->assertSee('Student Document Requests')
            ->assertSee('Asha Student')
            ->assertSee('Bonafide Certificate')
            ->assertSee('Approve')
            ->assertSee('Mark Ready');
    }

    public function test_admin_can_approve_and_reject_student_document_requests(): void
    {
        Mail::fake();
        $student = $this->student();
        $admin = $this->admin();

        $request = DocumentRequest::create([
            'student_id' => $student->id,
            'document_type' => 'fee_letter',
            'purpose' => 'Bank loan',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.document-requests.approve', $request), [
                'notes' => 'Processing after fee ledger check.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Document request approved for processing.');

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertSame($admin->id, $request->reviewed_by);
        $this->assertSame('Processing after fee ledger check.', $request->notes);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->user_id,
            'title' => 'Document request approved',
            'type' => 'document_request',
        ]);
        Mail::assertQueued(StudentDocumentRequestUpdated::class, 1);

        $this->actingAs($admin)
            ->patch(route('admin.document-requests.reject', $request), [
                'notes' => 'Fee ledger is incomplete.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Document request rejected with staff notes.');

        $request->refresh();
        $this->assertSame('rejected', $request->status);
        $this->assertSame('Fee ledger is incomplete.', $request->notes);
        $this->assertNull($request->output_path);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->user_id,
            'title' => 'Document request rejected',
            'type' => 'document_request',
        ]);
        Mail::assertQueued(StudentDocumentRequestUpdated::class, 2);
    }

    public function test_admin_can_upload_ready_document_for_student_download(): void
    {
        Mail::fake();
        Storage::fake('local');
        $student = $this->student();
        $admin = $this->admin();

        $request = DocumentRequest::create([
            'student_id' => $student->id,
            'document_type' => 'bonafide',
            'purpose' => 'Visa',
            'status' => 'approved',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.document-requests.fulfill', $request), [
                'document_file' => UploadedFile::fake()->create('bonafide.pdf', 48, 'application/pdf'),
                'notes' => 'Ready for visa submission.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Document uploaded and marked ready for the student.');

        $request->refresh();
        $this->assertSame('ready', $request->status);
        $this->assertNotNull($request->fulfilled_at);
        $this->assertNotNull($request->output_path);
        Storage::disk('local')->assertExists($request->output_path);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->user_id,
            'title' => 'Document ready for download',
            'type' => 'document_request',
        ]);
        Mail::assertQueued(StudentDocumentRequestUpdated::class);

        $this->actingAs($admin)
            ->get(route('admin.document-requests.download', $request))
            ->assertStatus(200)
            ->assertHeader('content-disposition');

        $this->actingAs($student->user)
            ->get(route('student.documents.download', $request))
            ->assertStatus(200)
            ->assertHeader('content-disposition');
    }

    public function test_rejecting_requires_staff_notes(): void
    {
        $request = DocumentRequest::create([
            'student_id' => $this->student()->id,
            'document_type' => 'noc',
            'purpose' => 'Internship',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->patch(route('admin.document-requests.reject', $request), [
                'notes' => '',
            ])
            ->assertSessionHasErrors('notes');
    }

    public function test_document_request_notifications_link_to_student_documents(): void
    {
        Mail::fake();
        $student = $this->student();

        $request = DocumentRequest::create([
            'student_id' => $student->id,
            'document_type' => 'character',
            'purpose' => 'Higher studies',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin())
            ->patch(route('admin.document-requests.approve', $request))
            ->assertRedirect();

        $notification = Notification::where('user_id', $student->user_id)->first();

        $this->assertNotNull($notification);
        $this->assertSame(route('student.documents.index'), $notification->action_url);
        $this->assertStringContainsString('Character Certificate', $notification->message);
    }
}
