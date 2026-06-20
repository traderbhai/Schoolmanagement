<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\FeeDemand;
use App\Models\HostelAllocation;
use App\Models\HostelBlock;
use App\Models\HostelFeeDemand;
use App\Models\HostelRoom;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentDocumentRequestGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private function student(): Student
    {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('student');

        return Student::factory()->create([
            'user_id' => $user->id,
            'program_id' => Program::factory()->create()->id,
            'status' => 'active',
        ]);
    }

    public function test_document_page_surfaces_ready_download_priority(): void
    {
        Storage::fake('local');
        $student = $this->student();
        Storage::disk('local')->put('student-documents/bonafide.pdf', 'PDF');

        DocumentRequest::create([
            'student_id' => $student->id,
            'document_type' => 'bonafide',
            'purpose' => 'Scholarship application',
            'status' => 'ready',
            'output_path' => 'student-documents/bonafide.pdf',
            'fulfilled_at' => now(),
        ]);

        $this->actingAs($student->user)
            ->get(route('student.documents.index'))
            ->assertStatus(200)
            ->assertSee('Document Priority')
            ->assertSee('A requested document is ready')
            ->assertSee('Bonafide Certificate')
            ->assertSee('Download');

        $this->actingAs($student->user)
            ->get(route('student.documents.download', DocumentRequest::first()))
            ->assertStatus(200)
            ->assertHeader('content-disposition');
    }

    public function test_student_cannot_create_duplicate_open_document_request(): void
    {
        $student = $this->student();

        DocumentRequest::create([
            'student_id' => $student->id,
            'document_type' => 'fee_letter',
            'purpose' => 'Bank loan',
            'status' => 'pending',
        ]);

        $this->actingAs($student->user)
            ->post(route('student.documents.store'), [
                'document_type' => 'fee_letter',
                'purpose' => 'Scholarship',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'You already have an open request for this document type. Track the existing request before submitting another one.');

        $this->assertSame(1, DocumentRequest::where('student_id', $student->id)->where('document_type', 'fee_letter')->count());
    }

    public function test_student_cannot_create_duplicate_request_when_same_document_is_ready(): void
    {
        Storage::fake('local');
        $student = $this->student();
        Storage::disk('local')->put('student-documents/bonafide-ready.pdf', 'PDF');

        DocumentRequest::create([
            'student_id' => $student->id,
            'document_type' => 'bonafide',
            'purpose' => 'Visa',
            'status' => 'ready',
            'output_path' => 'student-documents/bonafide-ready.pdf',
            'fulfilled_at' => now(),
        ]);

        $this->actingAs($student->user)
            ->post(route('student.documents.store'), [
                'document_type' => 'bonafide',
                'purpose' => 'Scholarship',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'You already have an open request for this document type. Track the existing request before submitting another one.');

        $this->assertSame(1, DocumentRequest::where('student_id', $student->id)->where('document_type', 'bonafide')->count());
    }

    public function test_document_request_requires_purpose(): void
    {
        $student = $this->student();

        $this->actingAs($student->user)
            ->post(route('student.documents.store'), [
                'document_type' => 'bonafide',
            ])
            ->assertSessionHasErrors('purpose');
    }

    public function test_inactive_student_can_view_history_but_cannot_create_new_document_request(): void
    {
        $student = $this->student();
        $student->update(['status' => 'inactive']);

        DocumentRequest::create([
            'student_id' => $student->id,
            'document_type' => 'character',
            'purpose' => 'Historical request',
            'status' => 'rejected',
            'notes' => 'Closed before deactivation.',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.documents.index'))
            ->assertOk()
            ->assertSee('Character Certificate');

        $this->actingAs($student->user)
            ->get(route('student.documents.create'))
            ->assertOk()
            ->assertSee('New document requests are available only for active students. Contact the administration office if you need archived records.')
            ->assertSee('disabled');

        $this->actingAs($student->user)
            ->post(route('student.documents.store'), [
                'document_type' => 'bonafide',
                'purpose' => 'Trying after deactivation',
            ])
            ->assertRedirect(route('student.documents.index'))
            ->assertSessionHas('error', 'New document requests are available only for active students. Contact the administration office if you need archived records.');

        $this->assertDatabaseMissing('document_requests', [
            'student_id' => $student->id,
            'document_type' => 'bonafide',
        ]);
    }

    public function test_student_cannot_download_another_students_document(): void
    {
        Storage::fake('local');
        $owner = $this->student();
        $other = $this->student();
        Storage::disk('local')->put('student-documents/private.pdf', 'PDF');

        $request = DocumentRequest::create([
            'student_id' => $owner->id,
            'document_type' => 'bonafide',
            'purpose' => 'Visa',
            'status' => 'ready',
            'output_path' => 'student-documents/private.pdf',
        ]);

        $this->actingAs($other->user)
            ->get(route('student.documents.download', $request))
            ->assertForbidden();
    }

    public function test_ready_noc_priority_shows_clearance_blocker_when_download_is_no_longer_allowed(): void
    {
        Storage::fake('local');
        $student = $this->student();
        Storage::disk('local')->put('student-documents/noc-ready.pdf', 'PDF');

        $request = DocumentRequest::create([
            'student_id' => $student->id,
            'document_type' => 'noc',
            'purpose' => 'Transfer clearance',
            'status' => 'ready',
            'output_path' => 'student-documents/noc-ready.pdf',
            'fulfilled_at' => now(),
        ]);

        FeeDemand::factory()->create([
            'student_id' => $student->id,
            'final_amount' => 2500,
            'penalty_amount' => 250,
            'status' => 'pending',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.documents.index'))
            ->assertOk()
            ->assertSee('A ready document needs clearance before download')
            ->assertSee('NOC cannot be processed until fee clearance is complete')
            ->assertSee('Review Status')
            ->assertDontSee('A requested document is ready');

        $this->actingAs($student->user)
            ->get(route('student.documents.download', $request))
            ->assertForbidden();
    }

    public function test_ready_noc_priority_blocks_download_when_hostel_fee_is_open(): void
    {
        Storage::fake('local');
        $student = $this->student();
        Storage::disk('local')->put('student-documents/noc-hostel-ready.pdf', 'PDF');
        $block = HostelBlock::create([
            'name' => 'NOC Hostel Fee Block',
            'gender' => 'mixed',
            'total_floors' => 1,
            'is_active' => true,
        ]);
        $room = HostelRoom::create([
            'hostel_block_id' => $block->id,
            'room_number' => 'NOC-201',
            'floor' => 1,
            'room_type' => 'single',
            'capacity' => 1,
            'monthly_fee' => 4500,
            'status' => 'occupied',
        ]);
        $allocation = HostelAllocation::create([
            'hostel_room_id' => $room->id,
            'student_id' => $student->id,
            'bed_number' => 1,
            'allocated_from' => now()->subMonth()->toDateString(),
            'status' => 'active',
        ]);
        $request = DocumentRequest::create([
            'student_id' => $student->id,
            'document_type' => 'noc',
            'purpose' => 'Final hostel clearance',
            'status' => 'ready',
            'output_path' => 'student-documents/noc-hostel-ready.pdf',
            'fulfilled_at' => now(),
        ]);
        HostelFeeDemand::create([
            'hostel_allocation_id' => $allocation->id,
            'student_id' => $student->id,
            'month' => '2026-06',
            'amount' => 4500,
            'status' => 'pending',
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        $this->actingAs($student->user)
            ->get(route('student.documents.index'))
            ->assertOk()
            ->assertSee('A ready document needs clearance before download')
            ->assertSee('NOC cannot be processed until hostel fee clearance is complete')
            ->assertSee('INR 4,500.00')
            ->assertDontSee('A requested document is ready');

        $this->actingAs($student->user)
            ->get(route('student.documents.download', $request))
            ->assertForbidden();
    }
}
