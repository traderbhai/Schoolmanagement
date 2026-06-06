<?php
namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Batch;
use App\Models\Program;
use App\Services\AdmissionNotificationService;
use Illuminate\Http\Request;

class BulkCommunicationController extends Controller
{
    public function index(Request $request)
    {
        $programs = Program::where('is_active', true)->orderBy('name')->get();
        $batches  = Batch::orderBy('name')->get();
        $statuses = ['submitted', 'under_review', 'shortlisted', 'selected', 'rejected', 'withdrawn'];

        $applicants = collect();
        $preview = false;

        if ($request->filled('filter_status') || $request->filled('filter_program_id') || $request->filled('filter_batch_id')) {
            $preview = true;
            $query = Applicant::with(['user', 'program']);

            if ($request->filled('filter_status')) {
                $query->where('status', $request->filter_status);
            }
            if ($request->filled('filter_program_id')) {
                $query->where('program_id', $request->filter_program_id);
            }
            if ($request->filled('filter_batch_id')) {
                $query->where('batch_id', $request->filter_batch_id);
            }

            $applicants = $query->orderBy('created_at', 'desc')->limit(200)->get();
        }

        return view('admission.bulk-communication.index', compact(
            'programs', 'batches', 'statuses', 'applicants', 'preview'
        ));
    }

    public function send(Request $request, AdmissionNotificationService $notifService)
    {
        $request->validate([
            'applicant_ids'  => 'required|array|min:1',
            'applicant_ids.*'=> 'exists:applicants,id',
            'subject'        => 'required|string|max:255',
            'message'        => 'required|string|max:2000',
            'send_email'     => 'nullable|boolean',
        ]);

        $sent = 0;
        foreach ($request->applicant_ids as $id) {
            $applicant = Applicant::with('user')->find($id);
            if (!$applicant) continue;

            // Create DB notification
            \App\Models\Notification::create([
                'user_id' => $applicant->user_id,
                'title'   => $request->subject,
                'message' => $request->message,
                'type'    => 'bulk_message',
                'read_at' => null,
            ]);

            // Optionally send email
            if ($request->boolean('send_email') && $applicant->user?->email) {
                try {
                    \Illuminate\Support\Facades\Mail::raw(
                        $request->subject . "\n\n" . $request->message,
                        fn($m) => $m->to($applicant->user->email)->subject($request->subject)
                    );
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Bulk email failed for applicant ' . $id . ': ' . $e->getMessage());
                }
            }

            $sent++;
        }

        return redirect()->route('admission.bulk-communication.index')
            ->with('success', "Message sent to {$sent} applicant(s).");
    }
}
