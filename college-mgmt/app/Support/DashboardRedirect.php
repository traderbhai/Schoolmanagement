<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

class DashboardRedirect
{
    /**
     * Role priority matters for users with multiple roles. Keep the highest
     * operational scope first so shared accounts land in the broadest workspace.
     */
    private const ROLE_DASHBOARDS = [
        'admin' => 'admin.dashboard',
        'dean_academics' => 'dean.dashboard',
        'program_chair' => 'chair.dashboard',
        'hod' => 'hod.dashboard',
        'exam_cell' => 'exam-cell.dashboard',
        'accounts_officer' => 'accounts.dashboard',
        'cmc' => 'cmc.dashboard',
        'director' => 'director.dashboard',
        'admission_director' => 'admission.dashboard',
        'admission_head' => 'admission.dashboard',
        'admission_manager' => 'admission.dashboard',
        'jr_admission_manager' => 'admission.dashboard',
        'admission_counsellor' => 'admission.dashboard',
        'admission_telecaller' => 'admission.dashboard',
        'admission_officer' => 'admission.dashboard',
        'teacher' => 'teacher.dashboard',
        'parent' => 'parent.dashboard',
        'applicant' => 'applicant.dashboard',
    ];

    public static function forUser(User $user): RedirectResponse
    {
        foreach (self::ROLE_DASHBOARDS as $role => $routeName) {
            if ($user->hasRole($role)) {
                return redirect()->route($routeName);
            }
        }

        return redirect()->route('student.dashboard');
    }
}
