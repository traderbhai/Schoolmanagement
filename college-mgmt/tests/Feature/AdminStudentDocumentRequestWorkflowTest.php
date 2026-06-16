<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\FeeDemand;
use App\Models\HostelAllocation;
use App\Models\HostelBlock;
use App\Models\HostelFeeDemand;
use App\Models\HostelRoom;
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

    private function hostelAllocation(Student $student): HostelAllocation
    {
        $block = HostelBlock::create([
            'name' => 'NOC Hostel Block',
            'gender' => 'mixed',
            'total_floors' => 2,
            'is_active' => true,
        ]);

        $room = HostelRoom::create([
            'hostel_block_id' => $block->id,
            'room_number' => 'NOC-101',
            'floor' => 1,
            'room_type' => 'single',
            'capacity' => 1,
            'monthly_fee' => 7500,
            'status' => 'occupied',
        ]);

        return HostelAllocation::create([
            'hostel_room_id' => $room->id,
            'student_id' => $student->id,
            'bed_number' => 1,
            'allocated_from' => now()->subMonth()->toDateString(),
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

    public function test_admin_cannot_approve_noc_until_library_clearance_is_complete(): void
    {
        $student = $this->student();
        $admin = $this->admin();
        $request = DocumentRequest::create([
            'student_id' => $student->id,
            'document_type' => 'noc',
            'purpose' => 'Higher studies',
            'status' => 'pending',
        ]);
        $book = Book::create([
            'title' => 'Library Clearance Book',
            'author' => 'Library',
            'isbn' => 'NOC-' . uniqid(),
            'total_copies' => 1,
            'available_copies' => 0,
            'is_active' => true,
        ]);
        $copy = BookCopy::create([
            'book_id' => $book->id,
            'accession_number' => 'NOC-' . uniqid(),
            'is_available' => false,
        ]);
        BookIssue::create([
            'book_copy_id' => $copy->id,
            'student_id' => $student->id,
            'issued_by' => $admin->id,
            'issued_at' => now(),
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => 'issued',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.document-requests.approve', $request), [
                'notes' => 'Clear NOC',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'NOC cannot be processed until library clearance is complete: Has unreturned books.');

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_admin_cannot_approve_noc_until_fee_clearance_is_complete(): void
    {
        $student = $this->student();
        $request = DocumentRequest::create([
            'student_id' => $student->id,
            'document_type' => 'noc',
            'purpose' => 'Higher studies',
            'status' => 'pending',
        ]);
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 12000,
            'penalty_amount' => 500,
            'status' => 'overdue',
        ]);

        $this->actingAs($this->admin())
            ->patch(route('admin.document-requests.approve', $request), [
                'notes' => 'Clear NOC',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'NOC cannot be processed until fee clearance is complete: INR 12,500.00 remains open.');

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_paid_fee_demand_does_not_block_noc_approval(): void
    {
        Mail::fake();
        $student = $this->student();
        $admin = $this->admin();
        $request = DocumentRequest::create([
            'student_id' => $student->id,
            'document_type' => 'noc',
            'purpose' => 'Higher studies',
            'status' => 'pending',
        ]);
        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 12000,
            'penalty_amount' => 500,
            'status' => 'fully_paid',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.document-requests.approve', $request), [
                'notes' => 'All no-dues checks clear.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Document request approved for processing.');

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertSame($admin->id, $request->reviewed_by);
    }

    public function test_admin_cannot_approve_noc_until_hostel_fee_clearance_is_complete(): void
    {
        $student = $this->student();
        $allocation = $this->hostelAllocation($student);
        $request = DocumentRequest::create([
            'student_id' => $student->id,
            'document_type' => 'noc',
            'purpose' => 'Higher studies',
            'status' => 'pending',
        ]);

        HostelFeeDemand::create([
            'hostel_allocation_id' => $allocation->id,
            'student_id' => $student->id,
            'month' => now()->format('Y-m'),
            'amount' => 7500,
            'status' => 'pending',
            'due_date' => now()->addDays(10)->toDateString(),
        ]);

        $this->actingAs($this->admin())
            ->patch(route('admin.document-requests.approve', $request), [
                'notes' => 'Clear NOC',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'NOC cannot be processed until hostel fee clearance is complete: INR 7,500.00 remains open.');

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_paid_or_waived_hostel_fee_demands_do_not_block_noc_approval(): void
    {
        Mail::fake();
        $student = $this->student();
        $admin = $this->admin();
        $allocation = $this->hostelAllocation($student);
        $request = DocumentRequest::create([
            'student_id' => $student->id,
            'document_type' => 'noc',
            'purpose' => 'Higher studies',
            'status' => 'pending',
        ]);

        HostelFeeDemand::create([
            'hostel_allocation_id' => $allocation->id,
            'student_id' => $student->id,
            'month' => now()->subMonth()->format('Y-m'),
            'amount' => 7500,
            'status' => 'paid',
            'due_date' => now()->subMonth()->endOfMonth()->toDateString(),
            'paid_at' => now()->subDays(5),
        ]);

        HostelFeeDemand::create([
            'hostel_allocation_id' => $allocation->id,
            'student_id' => $student->id,
            'month' => now()->format('Y-m'),
            'amount' => 7500,
            'status' => 'waived',
            'due_date' => now()->addDays(10)->toDateString(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.document-requests.approve', $request), [
                'notes' => 'All no-dues checks clear.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Document request approved for processing.');

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertSame($admin->id, $request->reviewed_by);
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
