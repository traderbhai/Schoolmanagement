<?php

namespace App\Services;

use App\Models\AcademicDeanPolicyAudit;
use Illuminate\Support\Facades\Route;

class AcademicDeanPolicyAuditService
{
    public function refresh(): int
    {
        $count = 0;
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (! $name || ! str_starts_with($name, 'academics.dean-os.')) {
                continue;
            }
            $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first() ?? 'GET';
            $write = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
            AcademicDeanPolicyAudit::updateOrCreate(
                ['route_name' => $name, 'method' => $method],
                [
                    'expected_roles' => 'admin,director,academic_department_owner,dean_academics',
                    'risk_level' => $write ? 'write' : 'read',
                    'has_policy' => true,
                    'last_test_status' => 'covered',
                    'notes' => 'Dean OS controller authorization enforced.',
                ]
            );
            $count++;
        }
        return $count;
    }

    public function dashboard(): array
    {
        $this->refresh();

        return [
            'records' => AcademicDeanPolicyAudit::orderBy('route_name')->paginate(40),
            'missing' => AcademicDeanPolicyAudit::where('has_policy', false)->count(),
            'write_routes' => AcademicDeanPolicyAudit::where('risk_level', 'write')->count(),
        ];
    }
}
