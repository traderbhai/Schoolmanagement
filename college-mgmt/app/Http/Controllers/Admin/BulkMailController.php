<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\GenericBulkMail;
use App\Models\Applicant;
use App\Models\Batch;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class BulkMailController extends Controller
{
    public function index(Request $request)
    {
        $programs  = Program::where('is_active', true)->orderBy('name')->get();
        $batches   = Batch::orderBy('name')->get();
        $count     = null;

        if ($request->isMethod('get') && $request->has('audience')) {
            $count = $this->buildAudience($request)->count();
        }

        return view('admin.bulk-mail.index', compact('programs', 'batches', 'count'));
    }

    public function previewCount(Request $request)
    {
        $count = $this->buildAudience($request)->count();
        return response()->json(['count' => $count]);
    }

    public function send(Request $request)
    {
        $request->validate([
            'audience'   => 'required|string',
            'subject'    => 'required|string|max:255',
            'body'       => 'required|string',
            'program_id' => 'nullable|exists:programs,id',
            'batch_id'   => 'nullable|exists:batches,id',
            'role'       => 'nullable|string',
        ]);

        $recipients = $this->buildAudience($request);
        $sent       = 0;

        foreach ($recipients as $recipient) {
            $email = $recipient->email ?? null;
            $name  = $recipient->name  ?? null;

            if (! $email) continue;

            NotificationService::send(GenericBulkMail::class, $recipient, [
                'subject'        => $request->subject,
                'body'           => $request->body,
                'recipient_name' => $name,
            ]);

            $sent++;
        }

        return back()->with('success', "Bulk email queued for {$sent} recipient(s).");
    }

    private function buildAudience(Request $request): \Illuminate\Support\Collection
    {
        $audience = $request->audience;

        return match ($audience) {
            'all_students'  => User::role('student')->get(),
            'all_applicants' => User::role('applicant')->get(),
            'applicants_by_status' => $this->applicantsByStatus($request),
            'program_batch' => $this->programBatchUsers($request),
            'role'          => User::role($request->role ?? 'student')->get(),
            default         => collect(),
        };
    }

    private function applicantsByStatus(Request $request): \Illuminate\Support\Collection
    {
        $status = $request->applicant_status ?? 'submitted';
        return User::whereHas('applicant', fn($q) => $q->where('status', $status))->get();
    }

    private function programBatchUsers(Request $request): \Illuminate\Support\Collection
    {
        $query = Student::with('user')
            ->where('status', 'active');

        if ($request->program_id) {
            $query->where('program_id', $request->program_id);
        }
        if ($request->batch_id) {
            $query->where('batch_id', $request->batch_id);
        }

        return $query->get()->pluck('user')->filter()->values();
    }
}
