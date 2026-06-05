<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;

class StatusController extends Controller
{
    public function index()
    {
        $applicant = auth()->user()->applicant()->with(['program', 'batch'])->firstOrFail();
        return view('applicant.status', compact('applicant'));
    }
}
