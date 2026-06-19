<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Services\DepartmentHierarchyService;
use Barryvdh\DomPDF\Facade\Pdf;

class ApplicationPdfController extends Controller
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function generate(Applicant $applicant)
    {
        abort_unless(
            $this->hierarchy->canViewAssignedUser(auth()->user(), 'ADM', $applicant->assigned_to, false),
            403
        );

        abort_if($applicant->status === 'draft', 404, 'Application PDF is available only after the application is submitted.');

        $applicant->load(['user', 'program', 'batch', 'documents.requiredDocument']);

        $sections = [
            'Personal Details'  => $applicant->personal_data ?? [],
            'Academic Details'  => $applicant->academic_data ?? [],
            'Family Details'    => $applicant->family_data ?? [],
            'Additional Info'   => $applicant->additional_data ?? [],
        ];

        $pdf = Pdf::loadView('admission.application-pdf.template', [
            'applicant'   => $applicant,
            'sections'    => $sections,
            'collegeName' => config('app.name', 'Institute'),
        ])->setPaper('a4', 'portrait');

        $filename = 'application-' . $applicant->application_number . '.pdf';
        return $pdf->stream($filename);
    }
}
