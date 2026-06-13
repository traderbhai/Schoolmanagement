<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Services\AdmissionQuickSearchService;
use Illuminate\Http\Request;

class QuickSearchController extends Controller
{
    public function index(Request $request, AdmissionQuickSearchService $service)
    {
        $query = (string) $request->query('q', '');

        return view('admission.v0038.quick-search', [
            'query' => $query,
            'results' => $query !== '' ? $service->search($query, $request->user()) : collect(),
        ]);
    }
}
