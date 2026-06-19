<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\AccessControl;
use App\Mail\GenericBulkMail;
use App\Models\Applicant;
use App\Models\Batch;
use App\Models\Program;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class BulkMailController extends Controller
{
    private const CHUNK_SIZE = 200;

    public function index(Request $request)
    {
        $this->authorizeBulkMail($request);

        $programs  = Program::where('is_active', true)->orderBy('name')->get();
        $batches   = Batch::orderBy('name')->get();
        $count     = null;

        if ($request->isMethod('get') && $request->has('audience')) {
            $count = $this->countAudience($request);
        }

        return view('admin.bulk-mail.index', compact('programs', 'batches', 'count'));
    }

    public function previewCount(Request $request)
    {
        $this->authorizeBulkMail($request);

        $count = $this->countAudience($request);
        return response()->json(['count' => $count]);
    }

    public function send(Request $request)
    {
        $this->authorizeBulkMail($request);

        $request->validate([
            'audience'   => 'required|string',
            'subject'    => 'required|string|max:255',
            'body'       => 'required|string',
            'program_id' => 'nullable|exists:programs,id',
            'batch_id'   => 'nullable|exists:batches,id',
            'role'       => 'nullable|string',
        ]);

        $sent = 0;
        $seenEmails = [];

        $this->buildAudienceQuery($request)
            ->select('users.*')
            ->whereNotNull('users.email')
            ->orderBy('users.id')
            ->chunk(self::CHUNK_SIZE, function ($recipients) use ($request, &$sent, &$seenEmails) {
                foreach ($recipients as $recipient) {
                    $emailKey = mb_strtolower(trim($recipient->email));

                    if ($emailKey === '' || isset($seenEmails[$emailKey])) {
                        continue;
                    }

                    $seenEmails[$emailKey] = true;

                    NotificationService::send(GenericBulkMail::class, $recipient, [
                        'subject'        => $request->subject,
                        'body'           => $request->body,
                        'recipient_name' => $recipient->name,
                    ]);

                    $sent++;
                }
            });

        return back()->with('success', "Bulk email queued for {$sent} recipient(s).");
    }

    private function countAudience(Request $request): int
    {
        return (clone $this->buildAudienceQuery($request))
            ->whereNotNull('users.email')
            ->distinct('users.email')
            ->count('users.email');
    }

    private function buildAudienceQuery(Request $request): Builder
    {
        $audience = $request->audience;

        return match ($audience) {
            'all_students' => User::role('student')
                ->whereHas('student', fn($q) => $q->where('status', 'active')),
            'all_applicants' => User::role('applicant')
                ->whereHas('applicant'),
            'applicants_by_status' => $this->applicantsByStatus($request),
            'program_batch' => $this->programBatchUsers($request),
            'role' => User::role($request->role ?? 'student'),
            default => User::query()->whereRaw('1 = 0'),
        };
    }

    private function applicantsByStatus(Request $request): Builder
    {
        $status = $request->applicant_status ?? 'submitted';
        return User::role('applicant')
            ->whereHas('applicant', fn($q) => $q->where('status', $status));
    }

    private function programBatchUsers(Request $request): Builder
    {
        return User::role('student')
            ->whereHas('student', function ($query) use ($request) {
                $query->where('status', 'active')
                    ->when($request->program_id, fn($q) => $q->where('program_id', $request->program_id))
                    ->when($request->batch_id, fn($q) => $q->where('batch_id', $request->batch_id));
            });
    }

    private function authorizeBulkMail(Request $request): void
    {
        abort_unless(AccessControl::canSendGlobalBulkMail($request->user()), 403);
    }
}
