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

    public function test_transport_assignment_rejects_inactive_stop_and_hides_inactive_options(): void
    {
        $admin = $this->userWithRole('admin');
        [$route, $stop] = $this->routeWithStop();
        $inactiveRoute = TransportRoute::create([
            'name' => 'Inactive South Route',
            'code' => 'ISR-01',
            'start_point' => 'South Depot',
            'end_point' => 'Campus',
            'distance_km' => 11,
            'monthly_fee' => 2200,
            'is_active' => false,
        ]);
        $inactiveStop = TransportStop::create([
            'transport_route_id' => $route->id,
            'name' => 'Closed Stop',
            'sequence' => 2,
            'pickup_time' => '08:30',
            'drop_time' => '17:45',
            'is_active' => false,
        ]);
        $inactiveVehicle = $this->vehicle([
            'registration_number' => 'DL01BUS9999',
            'status' => 'maintenance',
        ]);
        $student = $this->student('Inactive Stop Student');

        $this->actingAs($admin)
            ->post(route('admin.transport.assignments.store'), [
                'student_id' => $student->id,
                'transport_route_id' => $route->id,
                'transport_stop_id' => $inactiveStop->id,
                'transport_vehicle_id' => null,
                'start_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('transport_stop_id');

        $response = $this->actingAs($admin)
            ->get(route('admin.transport.index'))
            ->assertStatus(200);

        $response->assertSee('<option value="' . $route->id . '">North Campus Route</option>', false);
        $response->assertSee('<option value="' . $stop->id . '">NCR-01 - City Center</option>', false);
        $response->assertDontSee('<option value="' . $inactiveRoute->id . '">Inactive South Route</option>', false);
        $response->assertDontSee('<option value="' . $inactiveStop->id . '">NCR-01 - Closed Stop</option>', false);
        $response->assertDontSee('<option value="' . $inactiveVehicle->id . '">DL01BUS9999</option>', false);

        $this->assertSame('maintenance', $inactiveVehicle->fresh()->status);
        $this->assertFalse($inactiveRoute->fresh()->is_active);
    }

    public function test_admin_can_update_vehicle_when_capacity_and_status_are_safe(): void
    {
        $admin = $this->userWithRole('admin');
        $vehicle = $this->vehicle([
            'capacity' => 3,
            'driver_name' => 'Old Driver',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.transport.vehicles.update', $vehicle), [
                'registration_number' => 'DL01BUS1234',
                'vehicle_type' => 'mini_bus',
                'capacity' => 4,
                'driver_name' => 'Updated Driver',
                'driver_phone' => '9000000000',
                'attendant_name' => 'Updated Attendant',
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Transport vehicle updated.');

        $vehicle->refresh();
        $this->assertSame('mini_bus', $vehicle->vehicle_type);
        $this->assertSame(4, $vehicle->capacity);
        $this->assertSame('Updated Driver', $vehicle->driver_name);
        $this->assertSame('9000000000', $vehicle->driver_phone);
        $this->assertSame('Updated Attendant', $vehicle->attendant_name);
    }

    public function test_admin_cannot_reduce_vehicle_capacity_below_active_assignments_or_deactivate_assigned_vehicle(): void
    {
        $admin = $this->userWithRole('admin');
        [$route, $stop] = $this->routeWithStop();
        $vehicle = $this->vehicle(['capacity' => 3]);
        $firstStudent = $this->student('Assigned One');
        $secondStudent = $this->student('Assigned Two');

        foreach ([$firstStudent, $secondStudent] as $student) {
            TransportAssignment::create([
                'student_id' => $student->id,
                'transport_route_id' => $route->id,
                'transport_stop_id' => $stop->id,
                'transport_vehicle_id' => $vehicle->id,
                'start_date' => now()->toDateString(),
                'monthly_fee' => 2750,
                'status' => 'active',
            ]);
        }

        $payload = [
            'registration_number' => $vehicle->registration_number,
            'vehicle_type' => $vehicle->vehicle_type,
            'capacity' => 1,
            'driver_name' => $vehicle->driver_name,
            'driver_phone' => $vehicle->driver_phone,
            'attendant_name' => $vehicle->attendant_name,
            'status' => 'active',
        ];

        $this->actingAs($admin)
            ->patch(route('admin.transport.vehicles.update', $vehicle), $payload)
            ->assertSessionHasErrors('capacity');

        $this->assertSame(3, $vehicle->fresh()->capacity);

        $payload['capacity'] = 3;
        $payload['status'] = 'maintenance';

        $this->actingAs($admin)
            ->patch(route('admin.transport.vehicles.update', $vehicle), $payload)
            ->assertSessionHasErrors('status');

        $this->assertSame('active', $vehicle->fresh()->status);
    }

    public function test_transport_index_shows_vehicle_fleet_update_controls_and_assignment_counts(): void
    {
        $admin = $this->userWithRole('admin');
        [$route, $stop] = $this->routeWithStop();
        $vehicle = $this->vehicle(['capacity' => 2]);
        $student = $this->student('Fleet Count Student');

        TransportAssignment::create([
            'student_id' => $student->id,
            'transport_route_id' => $route->id,
            'transport_stop_id' => $stop->id,
            'transport_vehicle_id' => $vehicle->id,
            'start_date' => now()->toDateString(),
            'monthly_fee' => 2750,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.transport.index'))
            ->assertStatus(200)
            ->assertSee('Vehicle Fleet')
            ->assertSee(route('admin.transport.vehicles.update', $vehicle), false)
            ->assertSee('DL01BUS1234')
            ->assertSee('Active Assignments')
            ->assertSee('Fleet Count Student');
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

    public function test_admin_cannot_end_transport_assignment_before_start_date(): void
    {
        $admin = $this->userWithRole('admin');
        [$route, $stop] = $this->routeWithStop();
        $student = $this->student();

        $assignment = TransportAssignment::create([
            'student_id' => $student->id,
            'transport_route_id' => $route->id,
            'transport_stop_id' => $stop->id,
            'start_date' => now()->toDateString(),
            'monthly_fee' => 2750,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.transport.assignments.end', $assignment), [
                'end_date' => now()->subDay()->toDateString(),
            ])
            ->assertSessionHasErrors('end_date');

        $assignment->refresh();
        $this->assertSame('active', $assignment->status);
        $this->assertNull($assignment->end_date);
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
