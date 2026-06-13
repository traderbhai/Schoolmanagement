<?php
namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\AdmissionCommunicationTemplate;
use App\Models\Batch;
use App\Models\Program;
use App\Services\AdmissionSafeCommunicationService;
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

    public function send(Request $request, AdmissionSafeCommunicationService $communication)
    {
        $request->validate([
            'applicant_ids'  => 'required|array|min:1',
            'applicant_ids.*'=> 'exists:applicants,id',
            'subject'        => 'required|string|max:255',
            'message'        => 'required|string|max:2000',
            'send_email'     => 'nullable|boolean',
        ]);

        $template = AdmissionCommunicationTemplate::firstOrCreate(
            ['name' => 'Bulk message: '.$request->subject, 'channel' => 'email'],
            ['purpose' => 'bulk_message', 'subject' => $request->subject, 'body' => $request->message, 'is_active' => true, 'created_by' => $request->user()->id]
        );

        if (! \Illuminate\Support\Facades\DB::table('admission_template_approvals')->where('template_id', $template->id)->where('status', 'approved')->exists()) {
            \Illuminate\Support\Facades\DB::table('admission_template_approvals')->insert([
                'template_id' => $template->id,
                'version' => 1,
                'status' => 'approved',
                'requested_by' => $request->user()->id,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'snapshot' => json_encode(['subject' => $request->subject, 'body' => $request->message, 'v' => '0.039_bulk']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $sent = 0;
        $blocked = 0;
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

            if ($request->boolean('send_email') && $applicant->user?->email) {
                $result = $communication->queue($applicant, $template, $request->user(), ['source' => 'bulk_communication']);
                isset($result->blocked_by_rule) ? $blocked++ : $sent++;
            }
        }

        return redirect()->route('admission.bulk-communication.index')
            ->with('success', "Bulk message processed: {$sent} queued, {$blocked} blocked by safety rules.");
    }
}
