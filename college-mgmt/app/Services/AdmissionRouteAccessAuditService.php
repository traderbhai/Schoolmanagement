<?php

namespace App\Services;

use App\Models\AdmissionRouteAccessAudit;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class AdmissionRouteAccessAuditService
{
    public function refresh(?User $reviewer = null): array
    {
        $reviewed = 0;
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (! $name || ! Str::startsWith($name, 'admission.')) {
                continue;
            }

            $methods = collect($route->methods())->reject(fn ($method) => $method === 'HEAD')->values()->implode('|');
            $uri = $route->uri();
            $risk = $this->riskFor($name, $methods);

            AdmissionRouteAccessAudit::updateOrCreate(
                ['route_name' => $name],
                [
                    'uri' => $uri,
                    'method' => $methods,
                    'required_scope' => $this->scopeFor($name),
                    'risk_level' => $risk,
                    'status' => 'reviewed',
                    'notes' => 'v0.037 route access audit seed/review record.',
                    'reviewed_by' => $reviewer?->id,
                    'reviewed_at' => now(),
                    'metadata' => ['middleware' => $route->gatherMiddleware()],
                ]
            );
            $reviewed++;
        }

        return [
            'reviewed' => $reviewed,
            'high_risk' => AdmissionRouteAccessAudit::where('risk_level', 'high')->count(),
            'write_routes' => AdmissionRouteAccessAudit::where('method', 'like', '%POST%')->count(),
        ];
    }

    public function dashboard(?User $reviewer = null): array
    {
        $stats = $this->refresh($reviewer);

        return [
            'stats' => $stats,
            'audits' => AdmissionRouteAccessAudit::orderByRaw("case risk_level when 'high' then 1 when 'medium' then 2 else 3 end")
                ->orderBy('route_name')
                ->paginate(30)
                ->withQueryString(),
        ];
    }

    private function riskFor(string $name, string $methods): string
    {
        if (str_contains($methods, 'POST') || str_contains($methods, 'PUT') || str_contains($methods, 'DELETE')) {
            return Str::contains($name, ['bulk', 'override', 'delete', 'payment', 'merge', 'approval']) ? 'high' : 'medium';
        }

        return Str::contains($name, ['reports', 'forecasting', 'approvals', 'data-quality']) ? 'medium' : 'low';
    }

    private function scopeFor(string $name): string
    {
        if (Str::contains($name, ['payment', 'approval', 'route-access'])) {
            return 'admission_head_or_admin';
        }
        if (Str::contains($name, ['manager', 'schedule-conflicts', 'performance'])) {
            return 'manager_subtree_or_head';
        }
        if (Str::contains($name, ['counsellor', 'evaluator', 'call-queue'])) {
            return 'assigned_or_hierarchy_scope';
        }

        return 'admission_hierarchy_scope';
    }
}
