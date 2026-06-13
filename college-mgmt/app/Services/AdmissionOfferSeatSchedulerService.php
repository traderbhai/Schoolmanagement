<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdmissionOfferSeatSchedulerService
{
    public function run(?User $actor = null): array
    {
        $expiredHolds = app(AdmissionSeatControlService::class)->expireHolds($actor);

        $expiredRounds = DB::table('admission_offer_rounds')
            ->where('status', 'published')
            ->whereNotNull('offer_valid_until')
            ->where('offer_valid_until', '<', now())
            ->update(['status' => 'expired', 'updated_at' => now()]);

        $unrepliedWaitlist = DB::table('admission_waitlist_entries')
            ->where('status', 'promoted')
            ->where('updated_at', '<', now()->subDays(3))
            ->update(['status' => 'expired', 'updated_at' => now()]);

        return compact('expiredHolds', 'expiredRounds', 'unrepliedWaitlist');
    }
}
