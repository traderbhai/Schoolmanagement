<?php

namespace App\Http\Middleware;

use App\Models\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProgramScope
{
    /**
     * Restrict access to resources that belong to the authenticated user's
     * program scope. Admins and dean_academics bypass all checks.
     *
     * Route parameter name defaults to "program_id" but can be overridden:
     *   ->middleware('program.scope:program')  (uses $request->program->id)
     *   ->middleware('program.scope:program_id') (uses $request->program_id)
     */
    public function handle(Request $request, Closure $next, string $param = 'program_id'): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401);
        }

        // Bypass for global roles
        if ($user->hasRole(['admin', 'dean_academics'])) {
            return $next($request);
        }

        // Resolve the requested program id from the route
        $routeModel = $request->route($param);
        $requestedProgramId = is_object($routeModel)
            ? $routeModel->id
            : ($request->route($param) ?? $request->input($param));

        if (!$requestedProgramId) {
            return $next($request);
        }

        // Check if user has a role scoped to this program
        $allowed = UserRole::where('user_id', $user->id)
            ->where(function ($q) use ($requestedProgramId) {
                $q->where('program_id', $requestedProgramId)
                  ->orWhereNull('program_id'); // global scope roles also pass
            })
            ->where(function ($q) {
                $q->whereNull('active_until')
                  ->orWhere('active_until', '>=', now()->toDateString());
            })
            ->exists();

        if (!$allowed) {
            abort(403, 'You do not have access to this program.');
        }

        return $next($request);
    }
}
