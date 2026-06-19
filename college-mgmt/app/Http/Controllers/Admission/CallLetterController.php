<?php
namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\SessionApplicant;
use App\Services\DepartmentHierarchyService;
use Barryvdh\DomPDF\Facade\Pdf;

class CallLetterController extends Controller
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function generate(Applicant $applicant)
    {
        abort_unless(
            $this->hierarchy->canViewAssignedUser(auth()->user(), 'ADM', $applicant->assigned_to, false),
            403
        );

        abort_if(
            in_array($applicant->status, ['rejected', 'withdrawn', 'enrolled'], true),
            404,
            'Call letter is not available for final-state applications.'
        );

        $assignment = SessionApplicant::with(['session.step', 'session.program'])
            ->where('applicant_id', $applicant->id)
            ->whereHas('session', function ($query) use ($applicant) {
                $query->where('program_id', $applicant->program_id)
                    ->whereIn('status', ['scheduled', 'ongoing']);
            })
            ->whereNotIn('attendance_status', ['absent'])
            ->orderByDesc('assigned_at')
            ->first();

        abort_unless($assignment?->session, 404, 'Call letter is available only after the applicant is assigned to an active selection session.');

        $session = $assignment->session;

        $pdf = Pdf::loadView('admission.call-letters.template', [
            'applicant'   => $applicant,
            'session'     => $session,
            'collegeName' => config('app.name', 'Institute of Technology'),
        ])->setPaper('a4', 'portrait');

        $filename = 'call-letter-' . $applicant->application_number . '.pdf';
        return $pdf->stream($filename);
    }
}
