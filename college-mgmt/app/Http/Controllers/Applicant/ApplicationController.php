<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Mail\ApplicationReceived;
use App\Mail\NewApplicationAlert;
use App\Models\AdmissionFormConfig;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    private function getSections(int $programId): array
    {
        $formConfig = AdmissionFormConfig::where('program_id', $programId)->first();
        return $formConfig ? $formConfig->form_sections : AdmissionFormConfig::getDefaultSections();
    }

    private function sectionKeyFromIndex(int $index): string
    {
        return match($index) {
            0 => 'personal',
            1 => 'academic',
            2 => 'family',
            3 => 'additional',
            default => 'additional',
        };
    }

    private function columnFromSectionKey(string $key): string
    {
        return match($key) {
            'personal'   => 'personal_data',
            'academic'   => 'academic_data',
            'family'     => 'family_data',
            'additional' => 'additional_data',
            default      => 'additional_data',
        };
    }

    public function show()
    {
        $applicant = auth()->user()->applicant()->with('program')->firstOrFail();
        $sections  = $this->getSections($applicant->program_id);
        $currentStep = request()->query('step', 0);

        return view('applicant.application.show', compact('applicant', 'sections', 'currentStep'));
    }

    public function saveSection(Request $request, string $section)
    {
        $applicant = auth()->user()->applicant()->firstOrFail();

        if ($applicant->status !== 'draft') {
            return back()->with('error', 'Application cannot be edited after submission.');
        }

        $column = $this->columnFromSectionKey($section);
        $existing = $applicant->$column ?? [];
        $merged = array_merge((array) $existing, $request->except(['_token', '_method']));

        $applicant->update([$column => $merged]);

        // Sync category & entrance exam fields to direct columns when saving personal section
        if ($section === 'personal') {
            $syncData = [];
            if (!empty($merged['category'])) {
                // Map display value to enum key
                $categoryMap = [
                    'General' => 'general', 'OBC' => 'obc', 'OBC-NC' => 'obc_nc',
                    'SC' => 'sc', 'ST' => 'st', 'EWS' => 'ews', 'PWD' => 'pwd',
                    'NRI' => 'nri', 'Management Quota' => 'management_quota',
                    // Pass-through if already a key
                    'general' => 'general', 'obc' => 'obc', 'obc_nc' => 'obc_nc',
                    'sc' => 'sc', 'st' => 'st', 'ews' => 'ews', 'pwd' => 'pwd',
                    'nri' => 'nri', 'management_quota' => 'management_quota',
                ];
                $syncData['category'] = $categoryMap[$merged['category']] ?? 'general';
            }
            if (!empty($merged['pwd_percentage'])) {
                $syncData['pwd_percentage'] = (float) $merged['pwd_percentage'];
            }
            if (!empty($merged['domicile_state'])) {
                $syncData['domicile_state'] = $merged['domicile_state'];
            }
            if (!empty($merged['entrance_exam_type'])) {
                $examMap = [
                    'CAT' => 'cat', 'MAT' => 'mat', 'XAT' => 'xat', 'CMAT' => 'cmat',
                    'ATMA' => 'atma', 'GMAT' => 'gmat', 'Other' => 'other', 'None' => 'none',
                    'cat' => 'cat', 'mat' => 'mat', 'xat' => 'xat', 'cmat' => 'cmat',
                    'atma' => 'atma', 'gmat' => 'gmat', 'other' => 'other', 'none' => 'none',
                ];
                $syncData['entrance_exam_type'] = $examMap[$merged['entrance_exam_type']] ?? null;
            }
            if (!empty($merged['entrance_exam_score'])) {
                $syncData['entrance_exam_score'] = (float) $merged['entrance_exam_score'];
            }
            if (!empty($merged['entrance_exam_roll_number'])) {
                $syncData['entrance_exam_roll_number'] = $merged['entrance_exam_roll_number'];
            }
            if (!empty($merged['entrance_exam_year'])) {
                $syncData['entrance_exam_year'] = (int) $merged['entrance_exam_year'];
            }
            if (!empty($syncData)) {
                $applicant->update($syncData);
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('applicant.application.show', ['step' => $this->nextStepIndex($section)])
            ->with('success', 'Section saved successfully.');
    }

    private function nextStepIndex(string $section): int
    {
        $order = ['personal' => 1, 'academic' => 2, 'family' => 3, 'additional' => 3];
        return $order[$section] ?? 0;
    }

    public function submit(Request $request)
    {
        $applicant = auth()->user()->applicant()->firstOrFail();

        if ($applicant->status !== 'draft') {
            return back()->with('error', 'Application already submitted.');
        }

        // Validate required fields from config
        $sections = $this->getSections($applicant->program_id);
        $sectionKeys = ['personal', 'academic', 'family', 'additional'];
        $errors = [];

        foreach ($sections as $i => $section) {
            $key = $sectionKeys[$i] ?? 'additional';
            $data = $applicant->getFormDataForSection($key);
            foreach ($section['fields'] ?? [] as $field) {
                if (!empty($field['required']) && empty($data[$field['key']])) {
                    $errors[] = "{$field['label']} is required.";
                }
            }
        }

        if (!empty($errors)) {
            return back()->withErrors($errors)->with('error', 'Please fill all required fields.');
        }

        $applicant->update([
            'status'     => 'submitted',
            'applied_at' => now(),
        ]);

        // Send notifications
        $applicant->load(['user', 'program']);
        if ($applicant->user) {
            NotificationService::send(ApplicationReceived::class, $applicant->user, ['applicant' => $applicant]);
        }
        // Alert admission team
        $admissionTeam = User::whereHas('roles', function ($query) {
            $query->whereIn('name', \App\Services\DepartmentHierarchyService::ADMISSION_ROLE_NAMES);
        })->get();
        NotificationService::sendBulk(NewApplicationAlert::class, $admissionTeam, ['applicant' => $applicant]);

        return redirect()->route('applicant.status')
            ->with('success', 'Application submitted successfully!');
    }
}
