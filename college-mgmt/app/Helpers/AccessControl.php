<?php

namespace App\Helpers;

use App\Models\{User, Student, RoleProgramAssignment};

class AccessControl
{
    public static function canManageProgram(User $user, int $programId): bool
    {
        if ($user->hasRole(['admin', 'dean_academics'])) return true;

        return RoleProgramAssignment::where('user_id', $user->id)
            ->where('program_id', $programId)
            ->where('is_active', true)
            ->whereIn('role_name', ['program_chair', 'hod'])
            ->exists();
    }

    public static function canManageExams(User $user): bool
    {
        return $user->hasRole(['admin', 'director', 'exam_cell', 'dean_academics']);
    }

    public static function canManageFees(User $user): bool
    {
        return $user->hasRole(['admin', 'accounts_officer']);
    }

    public static function canManageStudentDocuments(User $user): bool
    {
        return $user->hasRole(['admin', 'director', 'dean_academics', 'accounts_officer']);
    }

    public static function canManageGlobalLeaves(User $user): bool
    {
        return $user->hasRole(['admin', 'director', 'dean_academics']);
    }

    public static function canManageGlobalGrievances(User $user): bool
    {
        return $user->hasRole(['admin', 'director', 'dean_academics']);
    }

    public static function canSendGlobalBulkMail(User $user): bool
    {
        return $user->hasRole(['admin', 'director', 'dean_academics']);
    }

    public static function canManageRoleAssignments(User $user): bool
    {
        return $user->hasRole(['admin', 'director']);
    }

    public static function canManageSystemConfiguration(User $user): bool
    {
        return $user->hasRole(['admin', 'director']);
    }

    public static function canUseGlobalSearch(User $user): bool
    {
        return $user->hasRole(['admin', 'director', 'dean_academics']);
    }

    public static function canViewGlobalActivityLogs(User $user): bool
    {
        return $user->hasRole(['admin', 'director']);
    }

    public static function canViewInstitutionalAnalytics(User $user): bool
    {
        return $user->hasRole(['admin', 'director', 'dean_academics']);
    }

    public static function canExportGlobalStudentData(User $user): bool
    {
        return $user->hasRole(['admin', 'director', 'dean_academics']);
    }

    public static function canExportPlacementData(User $user): bool
    {
        return $user->hasRole(['admin', 'director', 'dean_academics', 'cmc']);
    }

    public static function canManagePlacementOperations(User $user): bool
    {
        return $user->hasRole(['admin', 'director', 'dean_academics', 'cmc']);
    }

    public static function canViewRegulatoryReports(User $user): bool
    {
        return $user->hasRole(['admin', 'director', 'dean_academics']);
    }

    public static function canViewEmailLogs(User $user): bool
    {
        return $user->hasRole(['admin', 'director']);
    }

    public static function canManageOfficialNotices(User $user): bool
    {
        return $user->hasRole(['admin', 'director', 'dean_academics']);
    }

    public static function canViewGlobalFacultyReports(User $user): bool
    {
        return $user->hasRole(['admin', 'director', 'dean_academics']);
    }

    public static function canManageAcademicIdentities(User $user): bool
    {
        return $user->hasRole(['admin', 'director', 'dean_academics']);
    }

    public static function canManageAcademicScheduling(User $user): bool
    {
        return $user->hasRole(['admin', 'director', 'dean_academics']);
    }

    public static function canManageGlobalEnrollments(User $user): bool
    {
        return $user->hasRole(['admin', 'director', 'dean_academics']);
    }

    public static function canManageAcademicStructure(User $user): bool
    {
        return $user->hasRole(['admin', 'director', 'dean_academics']);
    }

    public static function canManageGlobalAttendance(User $user): bool
    {
        return $user->hasRole(['admin', 'director', 'dean_academics']);
    }

    public static function canViewOfficialAcademicDocuments(User $user): bool
    {
        return $user->hasRole(['admin', 'director', 'dean_academics', 'exam_cell']);
    }

    public static function canViewOfficialFinancialDocuments(User $user): bool
    {
        return $user->hasRole(['admin', 'director', 'dean_academics', 'accounts_officer']);
    }

    public static function canManageLegacyAdmissionOperations(User $user): bool
    {
        return $user->hasRole([
            'admin',
            'director',
            'dean_academics',
            'admission_director',
            'admission_head',
            'admission_manager',
            'admission_officer',
        ]);
    }

    public static function canManageAdmissionConfiguration(User $user): bool
    {
        return $user->hasRole([
            'admin',
            'director',
            'dean_academics',
            'admission_director',
            'admission_head',
            'admission_manager',
        ]);
    }

    public static function canManageInstitutionAssets(User $user): bool
    {
        return $user->hasRole(['admin', 'director']);
    }

    public static function canManageTransportOperations(User $user): bool
    {
        return $user->hasRole(['admin', 'director']);
    }

    public static function canManageLibraryOperations(User $user): bool
    {
        return $user->hasRole(['admin', 'director', 'librarian']);
    }

    public static function canManageHostelOperations(User $user): bool
    {
        return $user->hasRole(['admin', 'director', 'hostel_warden']);
    }

    public static function canViewStudentData(User $user, Student $student): bool
    {
        if ($user->hasRole(['admin', 'dean_academics', 'exam_cell'])) return true;

        return static::canManageProgram($user, $student->program_id);
    }
}
