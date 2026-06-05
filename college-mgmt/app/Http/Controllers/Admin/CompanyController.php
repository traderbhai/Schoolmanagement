<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $companies = Company::withCount('drives')
            ->when($request->search, fn($q, $v) =>
                $q->where(fn($sq) =>
                    $sq->where('name', 'like', "%$v%")
                       ->orWhere('industry', 'like', "%$v%")
                       ->orWhere('contact_email', 'like', "%$v%")
                )
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $totalCompanies = Company::count();
        $activeCompanies = Company::where('is_active', true)->count();
        $totalDrives = \App\Models\PlacementDrive::count();

        return view('admin.companies.index', compact('companies', 'totalCompanies', 'activeCompanies', 'totalDrives'));
    }

    public function create()
    {
        return view('admin.companies.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:191',
            'industry'      => 'nullable|string|max:191',
            'website'       => 'nullable|url|max:191',
            'contact_person'=> 'nullable|string|max:191',
            'contact_email' => 'nullable|email|max:191',
            'contact_phone' => 'nullable|string|max:20',
            'description'   => 'nullable|string',
            'logo_url'      => 'nullable|url|max:191',
            'is_active'     => 'boolean',
        ]);

        Company::create($request->merge(['is_active' => $request->boolean('is_active')])->all());

        return redirect()->route('admin.companies.index')->with('success', 'Company created successfully.');
    }

    public function edit(Company $company)
    {
        return view('admin.companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company)
    {
        $request->validate([
            'name'          => 'required|string|max:191',
            'industry'      => 'nullable|string|max:191',
            'website'       => 'nullable|url|max:191',
            'contact_person'=> 'nullable|string|max:191',
            'contact_email' => 'nullable|email|max:191',
            'contact_phone' => 'nullable|string|max:20',
            'description'   => 'nullable|string',
            'logo_url'      => 'nullable|url|max:191',
            'is_active'     => 'boolean',
        ]);

        $company->update($request->merge(['is_active' => $request->boolean('is_active')])->all());

        return redirect()->route('admin.companies.index')->with('success', 'Company updated successfully.');
    }

    public function destroy(Company $company)
    {
        $company->delete();
        return redirect()->route('admin.companies.index')->with('success', 'Company deleted.');
    }
}
