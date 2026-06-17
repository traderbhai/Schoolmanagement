<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_student_account_cannot_self_delete_and_cascade_academic_history(): void
    {
        $user = User::factory()->create();
        $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $this->actingAs($user)
            ->from('/profile')
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'user_id' => $user->id]);
    }

    public function test_teacher_parent_and_applicant_accounts_cannot_self_delete_institutional_history(): void
    {
        $teacherUser = User::factory()->create();
        $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id, 'status' => 'active']);

        $parentUser = User::factory()->create();
        $parent = ParentProfile::create([
            'user_id' => $parentUser->id,
            'relation' => 'guardian',
            'phone' => '9999999999',
        ]);

        $applicant = Applicant::factory()->create();
        $applicantUser = $applicant->user;

        foreach ([
            [$teacherUser, 'teachers', $teacher->id],
            [$parentUser, 'parents', $parent->id],
            [$applicantUser, 'applicants', $applicant->id],
        ] as [$user, $table, $profileId]) {
            $this->actingAs($user)
                ->from('/profile')
                ->delete('/profile', ['password' => 'password'])
                ->assertSessionHasErrorsIn('userDeletion', 'password')
                ->assertRedirect('/profile');

            $this->assertDatabaseHas('users', ['id' => $user->id]);
            $this->assertDatabaseHas($table, ['id' => $profileId]);
        }
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
