<?php
namespace App\Http\Controllers;

use App\Models\Applicant;
use Illuminate\Http\Request;

class StatusTrackerController extends Controller
{
    public function index()
    {
        return view('public.status-tracker', ['applicant' => null, 'searched' => false]);
    }

    public function track(Request $request)
    {
        $request->validate([
            'application_number' => 'required|string|max:50',
            'email'              => 'required|email',
        ]);

        $applicant = Applicant::with(['program', 'batch', 'user'])
            ->where('application_number', $request->application_number)
            ->whereHas('user', fn($q) => $q->where('email', $request->email))
            ->first();

        return view('public.status-tracker', [
            'applicant' => $applicant,
            'searched'  => true,
        ]);
    }
}
