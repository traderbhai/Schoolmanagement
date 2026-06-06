<?php

namespace App\Providers;

use App\Models\Notification;
use App\Models\RoleFeatureAccess;
use App\Policies\NotificationPolicy;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Notification::class, NotificationPolicy::class);

        // @canAccess('feature.code', 'level') — checks RoleFeatureAccess for current user's roles
        Blade::if('canAccess', function (string $featureCode, string $requiredLevel = 'view') {
            $user = auth()->user();
            if (!$user) {
                return false;
            }
            if ($user->hasRole('admin')) {
                return true;
            }
            $roleIds = $user->roles->pluck('id')->toArray();
            foreach ($roleIds as $roleId) {
                if (RoleFeatureAccess::canAccess($roleId, $featureCode, $requiredLevel)) {
                    return true;
                }
            }
            return false;
        });
    }
}
