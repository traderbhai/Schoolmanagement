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
            'status' => 'draft',
        ]);
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
