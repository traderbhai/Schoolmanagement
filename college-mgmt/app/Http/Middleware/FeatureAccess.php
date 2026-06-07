<?php

namespace App\Http\Middleware;

use App\Models\RoleFeatureAccess;
use App\Services\RoleHierarchyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeatureAccess
{
    public function __construct(private RoleHierarchyService $hierarchy)
    {
    }

    /**
     * Check that the authenticated user's role has the required access level
     * for a given feature. Uses role hierarchy inheritance so higher-level
     * roles automatically inherit lower-level role permissions.
     *
     * Usage on routes:
     *   ->middleware('feature.access:exam.enter_marks,edit')
     *   ->middleware('feature.access:placement.create_drive,create')
     *
     * Admins always pass. When multiple roles are active, the highest
     * access level across all inherited role IDs wins.
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

        $userRoleNames = $user->roles->pluck('name')->toArray();

        if (empty($userRoleNames)) {
            abort(403, 'No roles assigned to your account.');
        }

        $levels = RoleFeatureAccess::ACCESS_LEVELS;
        $requiredIndex = array_search($requiredLevel, $levels);

        if ($requiredIndex === false) {
            abort(500, "Unknown access level: $requiredLevel");
        }

        // Use hierarchy-aware effective access level
        $effectiveLevel = $this->hierarchy->getEffectiveAccessLevel($userRoleNames, $featureCode);

        if ($effectiveLevel === null) {
            abort(403, "Access denied: '$featureCode' requires '$requiredLevel' level.");
        }

        $grantedIndex = array_search($effectiveLevel, $levels);

        if ($grantedIndex === false || $grantedIndex < $requiredIndex) {
            abort(403, "Access denied: '$featureCode' requires '$requiredLevel' level.");
        }

        return $next($request);
    }
}
