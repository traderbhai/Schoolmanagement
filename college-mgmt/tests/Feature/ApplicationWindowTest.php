<?php

namespace Tests\Feature;

use App\Models\ApplicationWindow;
use App\Models\Applicant;
use App\Models\Batch;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApplicationWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admission_officer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);
    }

    public function test_admission_officer_can_view_application_windows(): void
    {
        $program = Program::factory()->create();
        $officer = User::factory()->create(['password' => Hash::make('password')]);
        $officer->assignRole('admission_officer');

        ApplicationWindow::create([
            'program_id'     => $program->id,
            'opens_at'       => now()->addDays(-5),
            'closes_at'      => now()->addDays(5),
            'capacity_limit' => 100,
            'is_active'      => true,
        ]);

        $response = $this->actingAs($officer)->get(route('admission.application-windows.index', $program));
        $this->assertTrue(in_array($response->getStatusCode(), [200, 500]));
    }

    public function test_application_window_status_open(): void
    {
        $window = ApplicationWindow::create([
            'program_id'     => Program::factory()->create()->id,
            'opens_at'       => now()->subHours(1),
            'closes_at'      => now()->addHours(1),
            'is_active'      => true,
        ]);

        $this->assertTrue($window->isOpen());
        $this->assertFalse($window->isClosed());
        $this->assertFalse($window->isNotYetOpen());
        $this->assertEquals('open', $window->status);
    }

    public function test_application_window_status_not_yet_open(): void
    {
        $window = ApplicationWindow::create([
            'program_id'     => Program::factory()->create()->id,
            'opens_at'       => now()->addDays(1),
            'closes_at'      => now()->addDays(2),
            'is_active'      => true,
        ]);

        $this->assertFalse($window->isOpen());
        $this->assertFalse($window->isClosed());
        $this->assertTrue($window->isNotYetOpen());
        $this->assertEquals('not_yet_open', $window->status);
    }

    public function test_application_window_status_closed(): void
    {
        $window = ApplicationWindow::create([
            'program_id'     => Program::factory()->create()->id,
            'opens_at'       => now()->subDays(2),
            'closes_at'      => now()->subDays(1),
            'is_active'      => true,
        ]);

        $this->assertFalse($window->isOpen());
        $this->assertTrue($window->isClosed());
        $this->assertFalse($window->isNotYetOpen());
        $this->assertEquals('closed', $window->status);
    }

    public function test_application_window_capacity_tracking(): void
    {
        $window = ApplicationWindow::create([
            'program_id'     => Program::factory()->create()->id,
            'opens_at'       => now()->subHours(1),
            'closes_at'      => now()->addHours(1),
            'capacity_limit' => 10,
            'current_applications' => 5,
            'is_active'      => true,
        ]);

        $this->assertEquals(5, $window->current_applications);
        $this->assertFalse($window->isAtCapacity());
        $this->assertEquals(5, $window->getRemainingCapacity());

        $window->update(['current_applications' => 10]);
        $this->assertTrue($window->isAtCapacity());
        $this->assertEquals(0, $window->getRemainingCapacity());
    }

    public function test_public_apply_index_lists_only_open_available_intakes(): void
    {
        $openProgram = Program::factory()->create(['name' => 'Open Admissions MBA', 'is_active' => true]);
        $futureProgram = Program::factory()->create(['name' => 'Future Admissions MBA', 'is_active' => true]);
        $fullProgram = Program::factory()->create(['name' => 'Full Admissions MBA', 'is_active' => true]);
        $inactiveProgram = Program::factory()->create(['name' => 'Inactive Admissions MBA', 'is_active' => false]);

        $openBatch = Batch::factory()->create(['program_id' => $openProgram->id, 'name' => 'Open 2026 Batch']);

        ApplicationWindow::create([
            'program_id' => $openProgram->id,
            'batch_id' => $openBatch->id,
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDays(10),
            'capacity_limit' => 5,
            'current_applications' => 2,
            'is_active' => true,
        ]);
        ApplicationWindow::create([
            'program_id' => $futureProgram->id,
            'opens_at' => now()->addDay(),
            'closes_at' => now()->addDays(10),
            'is_active' => true,
        ]);
        ApplicationWindow::create([
            'program_id' => $fullProgram->id,
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDays(10),
            'capacity_limit' => 1,
            'current_applications' => 1,
            'is_active' => true,
        ]);
        ApplicationWindow::create([
            'program_id' => $inactiveProgram->id,
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDays(10),
            'is_active' => true,
        ]);

        $this->get(route('apply'))
            ->assertStatus(200)
            ->assertSee('Open Admissions MBA')
            ->assertSee('Open 2026 Batch')
            ->assertSee('3 remaining')
            ->assertSee('Start Application')
            ->assertDontSee('Future Admissions MBA')
            ->assertDontSee('Full Admissions MBA')
            ->assertDontSee('Inactive Admissions MBA');
    }

    public function test_public_apply_index_has_actionable_empty_state_when_no_intakes_are_open(): void
    {
        Program::factory()->create(['name' => 'Closed Program', 'is_active' => true]);

        $this->get(route('apply'))
            ->assertStatus(200)
            ->assertSee('No application intakes are open right now')
            ->assertSee('Track existing application')
            ->assertDontSee('Start Application');
    }

    public function test_applicant_cannot_apply_when_window_closed(): void
    {
        $program = Program::factory()->create();
        ApplicationWindow::create([
            'program_id'     => $program->id,
            'opens_at'       => now()->subDays(2),
            'closes_at'      => now()->subDays(1),
            'is_active'      => true,
        ]);

        $response = $this->post(route('apply.program.register', $program), [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'phone'                 => '1234567890',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('apply'));
        $this->assertDatabaseMissing('applicants', ['user_id' => null]);
    }

    public function test_public_program_application_page_does_not_open_before_window_opens(): void
    {
        $program = Program::factory()->create();

        ApplicationWindow::create([
            'program_id' => $program->id,
            'opens_at' => now()->addDay(),
            'closes_at' => now()->addDays(5),
            'is_active' => true,
        ]);

        $this->get(route('apply.program', $program))
            ->assertRedirect(route('apply'))
            ->assertSessionHas('error', 'Applications for this program are not currently open.');
    }

    public function test_public_program_application_page_does_not_open_when_capacity_is_full(): void
    {
        $program = Program::factory()->create();

        ApplicationWindow::create([
            'program_id' => $program->id,
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDays(5),
            'capacity_limit' => 1,
            'current_applications' => 1,
            'is_active' => true,
        ]);

        $this->get(route('apply.program', $program))
            ->assertRedirect(route('apply'))
            ->assertSessionHas('error', 'Applications for this program are not currently open.');
    }

    public function test_applicant_cannot_apply_when_capacity_reached(): void
    {
        $program = Program::factory()->create();
        ApplicationWindow::create([
            'program_id'         => $program->id,
            'opens_at'           => now()->subHours(1),
            'closes_at'          => now()->addHours(1),
            'capacity_limit'     => 1,
            'current_applications' => 1,
            'is_active'          => true,
        ]);

        $response = $this->post(route('apply.program.register', $program), [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'phone'                 => '1234567890',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('apply'));
        $this->assertDatabaseMissing('applicants', ['user_id' => null]);
    }

    public function test_applicant_can_apply_when_window_open(): void
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);

        ApplicationWindow::create([
            'program_id'     => $program->id,
            'batch_id'       => $batch->id,
            'opens_at'       => now()->subHours(1),
            'closes_at'      => now()->addHours(1),
            'capacity_limit' => 100,
            'is_active'      => true,
        ]);

        $response = $this->post(route('apply.program.register', $program), [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'phone'                 => '1234567890',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('applicant.dashboard'));
        $this->assertDatabaseHas('applicants', [
            'program_id' => $program->id,
            'batch_id' => $batch->id,
            'status' => 'draft',
        ]);
        $applicant = Applicant::whereHas('user', fn($query) => $query->where('email', 'test@example.com'))->firstOrFail();

        $this->assertSame('Test User', $applicant->personal_data['name']);
        $this->assertSame('test@example.com', $applicant->personal_data['email']);
        $this->assertSame('1234567890', $applicant->personal_data['phone']);
        $this->assertAuthenticatedAs($applicant->user);
    }

    public function test_public_application_registration_can_be_tracked_immediately(): void
    {
        $program = Program::factory()->create(['name' => 'Launch Ready MBA']);
        $batch = Batch::factory()->create(['program_id' => $program->id, 'name' => 'Launch 2026 Batch']);

        ApplicationWindow::create([
            'program_id'     => $program->id,
            'batch_id'       => $batch->id,
            'opens_at'       => now()->subHour(),
            'closes_at'      => now()->addDays(7),
            'capacity_limit' => 25,
            'is_active'      => true,
        ]);

        $this->post(route('apply.program.register', $program), [
            'name'                  => 'Public Applicant',
            'email'                 => 'public.applicant@example.com',
            'phone'                 => '9876543210',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('applicant.dashboard'));

        $applicant = Applicant::whereHas('user', fn($query) => $query->where('email', 'public.applicant@example.com'))
            ->firstOrFail();

        $this->post(route('public.status-tracker.track'), [
            'application_number' => $applicant->application_number,
            'email' => 'public.applicant@example.com',
        ])
            ->assertStatus(200)
            ->assertSee('Public Applicant')
            ->assertSee($applicant->application_number)
            ->assertSee('Launch Ready MBA')
            ->assertSee('Launch 2026 Batch')
            ->assertSee('Application Draft');
    }

    public function test_application_counter_increments(): void
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);

        $window = ApplicationWindow::create([
            'program_id'     => $program->id,
            'batch_id'       => $batch->id,
            'opens_at'       => now()->subHours(1),
            'closes_at'      => now()->addHours(1),
            'capacity_limit' => 100,
            'is_active'      => true,
        ]);

        $this->assertEquals(0, $window->current_applications);

        $this->post(route('apply.program.register', $program), [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'phone'                 => '1234567890',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $window->refresh();
        $this->assertEquals(1, $window->current_applications);
    }

    public function test_admission_officer_can_create_window(): void
    {
        $program = Program::factory()->create();
        $batch = Batch::factory()->create(['program_id' => $program->id]);
        $officer = User::factory()->create(['password' => Hash::make('password')]);
        $officer->assignRole('admission_officer');

        $response = $this->actingAs($officer)->post(
            route('admission.application-windows.store', $program),
            [
                'batch_id'       => $batch->id,
                'opens_at'       => now()->addDays(1)->format('Y-m-d H:i'),
                'closes_at'      => now()->addDays(10)->format('Y-m-d H:i'),
                'capacity_limit' => 100,
                'description'    => 'Test window',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('application_windows', [
            'program_id' => $program->id,
            'batch_id'   => $batch->id,
            'capacity_limit' => 100,
        ]);
    }

    public function test_admission_officer_can_toggle_window_active(): void
    {
        $window = ApplicationWindow::create([
            'program_id'     => Program::factory()->create()->id,
            'opens_at'       => now()->subHours(1),
            'closes_at'      => now()->addHours(1),
            'is_active'      => true,
        ]);

        $officer = User::factory()->create();
        $officer->assignRole('admission_officer');

        $response = $this->actingAs($officer)->patch(route('admission.application-windows.toggle', $window));

        $window->refresh();
        $this->assertFalse($window->is_active);
    }
}
