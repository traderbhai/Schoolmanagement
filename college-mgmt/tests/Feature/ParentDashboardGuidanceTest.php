<?php

namespace Tests\Feature;

use App\Models\ParentProfile;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ParentDashboardGuidanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_dashboard_explains_no_linked_children_state(): void
    {
        $parentUser = $this->parentUser();
        ParentProfile::create([
            'user_id' => $parentUser->id,
            'relation' => 'Guardian',
            'phone' => '9999999999',
        ]);

        $this->actingAs($parentUser)
            ->get(route('parent.dashboard'))
            ->assertOk()
            ->assertSee('No children linked to your account')
            ->assertSee('Ask the administration office to link your parent profile with the student record')
            ->assertSee('Owner: Parent')
            ->assertSee('Source: Linked child records')
            ->assertDontSee('N/A');
    }

    public function test_parent_dashboard_uses_readable_missing_child_data_labels(): void
    {
        $parentUser = $this->parentUser();
        $parent = ParentProfile::create([
            'user_id' => $parentUser->id,
            'relation' => 'Father',
            'phone' => '9999999999',
        ]);
        $student = Student::factory()->create([
            'program_id' => null,
            'status' => 'active',
        ]);
        $parent->students()->attach($student->id);

        $this->actingAs($parentUser)
            ->get(route('parent.dashboard'))
            ->assertOk()
            ->assertSee('No records')
            ->assertSee('Not published')
            ->assertSee('No due date published')
            ->assertSee('Owner:')
            ->assertSee('Source:')
            ->assertDontSee('N/A');
    }

    private function parentUser(): User
    {
        Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => 'Parent Guide']);
        $user->assignRole('parent');

        return $user;
    }
}
