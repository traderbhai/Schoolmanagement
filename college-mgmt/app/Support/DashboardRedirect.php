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
        'dean_academics' => 'academics.dean-os.index',
        'program_chair' => 'academics.pmc.command',
        'hod' => 'hod.dashboard',
        'exam_cell' => 'exam-cell.dashboard',
        'exam_manager' => 'academics.coe.index',
        'exam_officer' => 'academics.coe.index',
        'iqac_head' => 'academics.iqac.index',
        'iqac_manager' => 'academics.iqac.index',
        'iqac_officer' => 'academics.iqac.index',
        'pmc_head' => 'academics.pmc.command',
        'pmc_manager' => 'academics.pmc.command',
        'pmc_officer' => 'academics.pmc.command',
        'program_director' => 'academics.program-leadership.index',
        'program_leader' => 'academics.program-leadership.index',
        'semester_coordinator' => 'academics.program-leadership.index',
        'course_coordinator' => 'academics.course-delivery.index',
        'faculty_mentor' => 'academics.course-delivery.index',
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
        'admission_partner' => 'admission.partner-portal.dashboard',
        'teacher' => 'teacher.dashboard',
        'parent' => 'parent.dashboard',
        'applicant' => 'applicant.dashboard',
    ];

    public static function forUser(User $user): RedirectResponse
    {
        return redirect()->route(self::routeNameForUser($user));
    }

    public static function routeNameForUser(User $user): string
    {
        foreach (self::ROLE_DASHBOARDS as $role => $routeName) {
            if ($user->hasRole($role)) {
                return $routeName;
            }
        }

        if (\App\Models\AdmissionPartner::where('contact_user_id', $user->id)->orWhere('contact_email', $user->email)->exists()) {
            return 'admission.partner-portal.dashboard';
        }

        return 'student.dashboard';
    }
}
