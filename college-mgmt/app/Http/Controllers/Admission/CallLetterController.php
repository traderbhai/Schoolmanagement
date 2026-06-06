<?php
namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\SelectionSession;
use Barryvdh\DomPDF\Facade\Pdf;

class CallLetterController extends Controller
{
    public function generate(Applicant $applicant)
    {
        // Find an upcoming or recent selection session for the applicant's program
        $session = SelectionSession::where('program_id', $applicant->program_id)
            ->whereIn('status', ['scheduled', 'ongoing'])
            ->orderBy('scheduled_date')
            ->first();

        $pdf = Pdf::loadView('admission.call-letters.template', [
            'applicant'   => $applicant,
            'session'     => $session,
            'collegeName' => config('app.name', 'Institute of Technology'),
        ])->setPaper('a4', 'portrait');

        $filename = 'call-letter-' . $applicant->application_number . '.pdf';
        return $pdf->stream($filename);
    }
}
