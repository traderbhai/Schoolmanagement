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

class AdminTransportAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_director_can_open_transport_operations(): void
    {
        foreach (['admin', 'director'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)
                ->get(route('admin.transport.index'))
                ->assertOk();
        }
    }

    public function test_broad_admin_group_roles_cannot_read_transport_operations(): void
    {
        foreach (['dean_academics', 'program_chair', 'hod', 'exam_cell', 'accounts_officer', 'cmc'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)
                ->get(route('admin.transport.index'))
                ->assertForbidden();
        }
    }

    public function test_broad_admin_group_roles_cannot_mutate_transport_operations(): void
    {
        [$route, $stop] = $this->routeWithStop();
        $vehicle = $this->vehicle();
        $student = $this->student('Transport Access Student');
        $assignment = TransportAssignment::create([
            'student_id' => $student->id,
            'transport_route_id' => $route->id,
            'transport_stop_id' => $stop->id,
            'transport_vehicle_id' => $vehicle->id,
            'start_date' => now()->subWeek()->toDateString(),
            'monthly_fee' => 2750,
            'status' => 'active',
        ]);

        foreach (['dean_academics', 'program_chair', 'hod', 'exam_cell', 'accounts_officer', 'cmc'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)
                ->post(route('admin.transport.routes.store'), [
                    'name' => "Blocked Route {$role}",
                    'code' => "BR-{$user->id}",
                    'start_point' => 'Blocked Start',
                    'end_point' => 'Blocked End',
                    'monthly_fee' => 1000,
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.transport.stops.store'), [
                    'transport_route_id' => $route->id,
                    'name' => "Blocked Stop {$role}",
                    'sequence' => $user->id + 10,
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.transport.vehicles.store'), [
                    'registration_number' => "BLOCKED-{$user->id}",
                    'vehicle_type' => 'bus',
                    'capacity' => 30,
                    'driver_name' => "Blocked Driver {$role}",
                    'status' => 'active',
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->patch(route('admin.transport.vehicles.update', $vehicle), [
                    'registration_number' => $vehicle->registration_number,
                    'vehicle_type' => 'mini_bus',
                    'capacity' => 50,
                    'driver_name' => "Changed Driver {$role}",
                    'driver_phone' => $vehicle->driver_phone,
                    'attendant_name' => $vehicle->attendant_name,
                    'status' => 'active',
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.transport.assignments.store'), [
                    'student_id' => $student->id,
                    'transport_route_id' => $route->id,
                    'transport_stop_id' => $stop->id,
                    'transport_vehicle_id' => $vehicle->id,
                    'start_date' => now()->toDateString(),
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.transport.assignments.end', $assignment), [
                    'end_date' => now()->toDateString(),
                ])
                ->assertForbidden();
        }

        $this->assertSame(1, TransportRoute::count());
        $this->assertSame(1, TransportStop::count());
        $this->assertSame(1, TransportVehicle::count());
        $this->assertSame('Ramesh Driver', $vehicle->fresh()->driver_name);
        $this->assertSame(1, TransportAssignment::count());
        $this->assertSame('active', $assignment->fresh()->status);
        $this->assertNull($assignment->fresh()->end_date);
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function student(string $name): Student
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
            'code' => 'NCR-ACCESS',
            'start_point' => 'North Depot',
            'end_point' => 'Main Campus',
            'monthly_fee' => 3000,
            'is_active' => true,
        ]);

        $stop = TransportStop::create([
            'transport_route_id' => $route->id,
            'name' => 'City Center',
            'sequence' => 1,
            'is_active' => true,
        ]);

        return [$route, $stop];
    }

    private function vehicle(): TransportVehicle
    {
        return TransportVehicle::create([
            'registration_number' => 'DL01BUS1234',
            'vehicle_type' => 'bus',
            'capacity' => 30,
            'driver_name' => 'Ramesh Driver',
            'driver_phone' => '9999999999',
            'attendant_name' => 'Sita Attendant',
            'status' => 'active',
        ]);
    }
}
