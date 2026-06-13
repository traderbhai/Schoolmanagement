<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\TransportAssignment;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use App\Models\TransportVehicle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TransportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function student(string $name = 'Transport Student'): Student
    {
        $user = $this->userWithRole('student');
        $user->update(['name' => $name]);

        return Student::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    private function routeWithStop(): array
    {
        $route = TransportRoute::create([
            'name' => 'North Campus Route',
            'code' => 'NCR-01',
            'start_point' => 'North Depot',
            'end_point' => 'Main Campus',
            'distance_km' => 18,
            'monthly_fee' => 3000,
            'is_active' => true,
        ]);

        $stop = TransportStop::create([
            'transport_route_id' => $route->id,
            'name' => 'City Center',
            'sequence' => 1,
            'pickup_time' => '08:00',
            'drop_time' => '17:15',
            'monthly_fee_override' => 2750,
            'is_active' => true,
        ]);

        return [$route, $stop];
    }

    private function vehicle(array $overrides = []): TransportVehicle
    {
        return TransportVehicle::create(array_merge([
            'registration_number' => 'DL01BUS1234',
            'vehicle_type' => 'bus',
            'capacity' => 2,
            'driver_name' => 'Ramesh Driver',
            'driver_phone' => '9999999999',
            'attendant_name' => 'Sita Attendant',
            'status' => 'active',
        ], $overrides));
    }

    public function test_admin_can_create_transport_setup_and_assign_student(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();

        $this->actingAs($admin)
            ->post(route('admin.transport.routes.store'), [
                'name' => 'East Route',
                'code' => 'ER-01',
                'start_point' => 'East Gate',
                'end_point' => 'Campus',
                'distance_km' => 12,
                'monthly_fee' => 2500,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Transport route created.');

        $route = TransportRoute::firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.transport.stops.store'), [
                'transport_route_id' => $route->id,
                'name' => 'Metro Stop',
                'sequence' => 1,
                'pickup_time' => '08:15',
                'drop_time' => '17:30',
                'monthly_fee_override' => 2400,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Transport stop added.');

        $this->actingAs($admin)
            ->post(route('admin.transport.vehicles.store'), [
                'registration_number' => 'DL01BUS4321',
                'vehicle_type' => 'bus',
                'capacity' => 40,
                'driver_name' => 'Transport Driver',
                'driver_phone' => '9888888888',
                'attendant_name' => 'Transport Attendant',
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Transport vehicle added.');

        $stop = TransportStop::firstOrFail();
        $vehicle = TransportVehicle::firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.transport.assignments.store'), [
                'student_id' => $student->id,
                'transport_route_id' => $route->id,
                'transport_stop_id' => $stop->id,
                'transport_vehicle_id' => $vehicle->id,
                'start_date' => now()->toDateString(),
                'notes' => 'Use gate 2 pickup.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Student transport assignment created.');

        $this->assertDatabaseHas('transport_assignments', [
            'student_id' => $student->id,
            'transport_route_id' => $route->id,
            'transport_stop_id' => $stop->id,
            'transport_vehicle_id' => $vehicle->id,
            'monthly_fee' => 2400,
            'status' => 'active',
        ]);
    }

    public function test_transport_assignment_guards_duplicate_student_and_vehicle_capacity(): void
    {
        $admin = $this->userWithRole('admin');
        [$route, $stop] = $this->routeWithStop();
        $vehicle = $this->vehicle(['capacity' => 1]);
        $firstStudent = $this->student('First Transport Student');
        $secondStudent = $this->student('Second Transport Student');

        TransportAssignment::create([
            'student_id' => $firstStudent->id,
            'transport_route_id' => $route->id,
            'transport_stop_id' => $stop->id,
            'transport_vehicle_id' => $vehicle->id,
            'start_date' => now()->toDateString(),
            'monthly_fee' => 2750,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.transport.assignments.store'), [
                'student_id' => $firstStudent->id,
                'transport_route_id' => $route->id,
                'transport_stop_id' => $stop->id,
                'transport_vehicle_id' => null,
                'start_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('student_id');

        $this->actingAs($admin)
            ->post(route('admin.transport.assignments.store'), [
                'student_id' => $secondStudent->id,
                'transport_route_id' => $route->id,
                'transport_stop_id' => $stop->id,
                'transport_vehicle_id' => $vehicle->id,
                'start_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('transport_vehicle_id');
    }

    public function test_admin_can_end_active_transport_assignment(): void
    {
        $admin = $this->userWithRole('admin');
        [$route, $stop] = $this->routeWithStop();
        $student = $this->student();

        $assignment = TransportAssignment::create([
            'student_id' => $student->id,
            'transport_route_id' => $route->id,
            'transport_stop_id' => $stop->id,
            'start_date' => now()->subMonth()->toDateString(),
            'monthly_fee' => 2750,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.transport.assignments.end', $assignment), [
                'end_date' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Transport assignment ended.');

        $assignment->refresh();
        $this->assertSame('inactive', $assignment->status);
        $this->assertNotNull($assignment->end_date);
    }

    public function test_student_can_view_active_transport_assignment(): void
    {
        [$route, $stop] = $this->routeWithStop();
        $vehicle = $this->vehicle();
        $student = $this->student('Visible Transport Student');

        TransportAssignment::create([
            'student_id' => $student->id,
            'transport_route_id' => $route->id,
            'transport_stop_id' => $stop->id,
            'transport_vehicle_id' => $vehicle->id,
            'start_date' => now()->toDateString(),
            'monthly_fee' => 2750,
            'status' => 'active',
            'notes' => 'Carry transport ID card.',
        ]);

        $this->actingAs($student->user)
            ->get(route('student.transport.index'))
            ->assertStatus(200)
            ->assertSee('North Campus Route')
            ->assertSee('City Center')
            ->assertSee('DL01BUS1234')
            ->assertSee('Ramesh Driver')
            ->assertSee('Rs. 2,750.00')
            ->assertSee('Carry transport ID card.');
    }
}
