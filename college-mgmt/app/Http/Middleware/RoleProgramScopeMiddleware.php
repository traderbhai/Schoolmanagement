<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\RoleProgramAssignment;
use Symfony\Component\HttpFoundation\Response;

class RoleProgramScopeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Checks if the authenticated user has a RoleProgramAssignment for the
     * requested program. Admin and dean_academics bypass the check.
     */
    public function handle(Request $request, Closure $next, string $programParam = 'program'): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Admins and deans bypass program scope
        if ($user->hasRole(['admin', 'dean_academics'])) {
            return $next($request);
        }

        $programId = $request->route($programParam);

        if ($programId) {
            $programId = is_object($programId) ? $programId->id : (int) $programId;

            $hasAccess = RoleProgramAssignment::where('user_id', $user->id)
                ->where('is_active', true)
                ->whereIn('role_name', ['program_chair', 'hod'])
                ->where(function ($q) use ($programId) {
                    $q->where('program_id', $programId)->orWhereNull('program_id');
                })
                ->exists();

            if (!$hasAccess) {
                abort(403, 'You do not have access to this program.');
            }
        }

        return $next($request);
    }
}
