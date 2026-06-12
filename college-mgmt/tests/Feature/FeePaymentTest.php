<?php

namespace Tests\Feature;

use App\Models\{User, Student, Program, FeeDemand};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeePaymentTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): array
    {
        $program = Program::factory()->create(['is_active' => true]);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $user    = User::factory()->create();
        $user->assignRole('student');
        $student = Student::factory()->create(['user_id' => $user->id, 'program_id' => $program->id]);
        return [$user, $student, $program];
    }

    public function test_student_can_view_fee_demands(): void
    {
        [$user] = $this->makeStudent();
        $this->actingAs($user)->get(route('student.fees'))->assertStatus(200);
    }

    public function test_student_can_view_fee_payment_page(): void
    {
        [$user] = $this->makeStudent();
        $this->actingAs($user)->get(route('student.fee-payment.index'))->assertStatus(200);
    }

    public function test_accounts_can_view_outstanding(): void
    {
        Role::firstOrCreate(['name' => 'accounts_officer', 'guard_name' => 'web']);
        $accountsUser = User::factory()->create();
        $accountsUser->assignRole('accounts_officer');

        $this->actingAs($accountsUser)->get(route('accounts.outstanding'))->assertStatus(200);
    }

    public function test_accounts_can_view_fee_collections(): void
    {
        Role::firstOrCreate(['name' => 'accounts_officer', 'guard_name' => 'web']);
        $accountsUser = User::factory()->create();
        $accountsUser->assignRole('accounts_officer');

        $this->actingAs($accountsUser)->get(route('accounts.fee-collections'))->assertStatus(200);
    }
}
