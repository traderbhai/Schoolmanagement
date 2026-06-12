<?php

namespace App\Providers;

use App\Models\{Notification, RoleFeatureAccess, OfferLetter, LeaveApplication, ExamResult, FeePayment};
use App\Observers\AuditObserver;
use App\Policies\NotificationPolicy;
use Illuminate\Support\Facades\{Blade, Gate};
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

        // Audit trail — observe key state-change models
        OfferLetter::observe(AuditObserver::class);
        LeaveApplication::observe(AuditObserver::class);
        ExamResult::observe(AuditObserver::class);
        FeePayment::observe(AuditObserver::class);

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
