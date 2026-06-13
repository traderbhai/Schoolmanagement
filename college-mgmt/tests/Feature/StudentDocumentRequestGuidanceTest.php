<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
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

    public function test_document_request_requires_purpose(): void
    {
        $student = $this->student();

        $this->actingAs($student->user)
            ->post(route('student.documents.store'), [
                'document_type' => 'bonafide',
            ])
            ->assertSessionHasErrors('purpose');
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
}
