<?php

namespace App\Http\Middleware;

use App\Models\RoleFeatureAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeatureAccess
{
    /**
     * Check that the authenticated user's role has the required access level
     * for a given feature.
     *
     * Usage on routes:
     *   ->middleware('feature.access:exam.enter_marks,edit')
     *   ->middleware('feature.access:placement.create_drive,create')
     *
     * Admins always pass. When multiple roles are active, the highest
     * access level across all role IDs wins.
     */
    public function handle(Request $request, Closure $next, string $featureCode, string $requiredLevel = 'view'): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401);
        }

        if ($user->hasRole('admin')) {
            return $next($request);
        }

        // Collect all role IDs the user currently holds via Spatie
        $roleIds = $user->roles->pluck('id')->toArray();

        if (empty($roleIds)) {
            abort(403, 'No roles assigned to your account.');
        }

        $levels = RoleFeatureAccess::ACCESS_LEVELS;
        $requiredIndex = array_search($requiredLevel, $levels);

        if ($requiredIndex === false) {
            abort(500, "Unknown access level: $requiredLevel");
        }

        // Check if any of the user's roles has sufficient access
        $hasAccess = RoleFeatureAccess::whereIn('role_id', $roleIds)
            ->where('feature_code', $featureCode)
            ->get()
            ->contains(function ($record) use ($levels, $requiredIndex) {
                $grantedIndex = array_search($record->access_level, $levels);
                return $grantedIndex !== false && $grantedIndex >= $requiredIndex;
            });

        if (!$hasAccess) {
            abort(403, "Access denied: '$featureCode' requires '$requiredLevel' level.");
        }

        return $next($request);
    }
}
