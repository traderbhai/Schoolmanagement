<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;

class StatusController extends Controller
{
    public function index()
    {
        $applicant = auth()->user()->applicant()->with([
            'program',
            'batch',
            'documents',
            'payments.installment',
            'offerLetters',
            'enrollmentConfirmation',
        ])->firstOrFail();

        return view('applicant.status', compact('applicant'));
    }
}
