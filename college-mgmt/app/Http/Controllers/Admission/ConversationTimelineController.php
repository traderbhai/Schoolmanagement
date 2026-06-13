<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Lead;
use App\Services\AdmissionConversationTimelineService;
use Illuminate\Http\Request;

class ConversationTimelineController extends Controller
{
    public function show(Request $request, string $subjectType, int $subjectId, AdmissionConversationTimelineService $service)
    {
        $subject = $subjectType === 'lead' ? Lead::findOrFail($subjectId) : Applicant::findOrFail($subjectId);

        return view('admission.v0036.conversation-timeline', [
            'subject' => $subject,
            'subjectType' => $subjectType,
            'events' => $service->forSubject($subject, 100),
        ]);
    }
}
