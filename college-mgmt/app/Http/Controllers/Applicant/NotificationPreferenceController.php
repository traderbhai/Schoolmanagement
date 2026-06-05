<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function edit()
    {
        $pref = NotificationPreference::firstOrCreate(
            ['user_id' => auth()->id()],
            [
                'email_application_updates' => true,
                'email_payment_updates'     => true,
                'email_result_published'    => true,
                'email_notices'             => true,
            ]
        );

        return view('applicant.notification-preferences', compact('pref'));
    }

    public function update(Request $request)
    {
        $pref = NotificationPreference::firstOrCreate(['user_id' => auth()->id()]);

        $pref->update([
            'email_application_updates' => $request->boolean('email_application_updates'),
            'email_payment_updates'     => $request->boolean('email_payment_updates'),
            'email_result_published'    => $request->boolean('email_result_published'),
            'email_notices'             => $request->boolean('email_notices'),
        ]);

        return back()->with('success', 'Notification preferences saved.');
    }
}
