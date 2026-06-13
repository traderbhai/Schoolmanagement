<?php

namespace App\Http\Middleware;

use App\Services\DepartmentHierarchyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DepartmentFeatureEnabled
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function handle(Request $request, Closure $next, string $departmentCode, string $featureKey): Response
    {
        abort_unless(
            $this->hierarchy->isFeatureEnabled($departmentCode, $featureKey),
            403,
            'This department feature is currently disabled.'
        );

        $response = $next($request);

        if (!$request->isMethodSafe() && $response->getStatusCode() < 400) {
            $route = $request->route();
            $routeName = $route?->getName() ?? $request->path();
            $parameters = collect($route?->parameters() ?? [])
                ->map(fn ($value) => is_object($value) ? ($value->id ?? (string) $value) : $value)
                ->all();

            $this->hierarchy->recordActivity(
                $departmentCode,
                $request->user(),
                'department_feature_action',
                'Performed ' . $request->method() . ' on ' . $routeName . '.',
                null,
                null,
                [
                    'feature_key' => $featureKey,
                    'route' => $routeName,
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'status' => $response->getStatusCode(),
                    'parameters' => $parameters,
                ]
            );
        }

        return $response;
    }
}
