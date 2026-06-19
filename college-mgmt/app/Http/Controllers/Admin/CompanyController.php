<?php
namespace App\Http\Controllers\Admin;

use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Internship;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePlacementOperations($request);

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
        $this->authorizePlacementOperations(request());

        return view('admin.companies.create');
    }

    public function store(Request $request)
    {
        $this->authorizePlacementOperations($request);

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
        $this->authorizePlacementOperations(request());

        return view('admin.companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company)
    {
        $this->authorizePlacementOperations($request);

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

        $data = $request->merge(['is_active' => $request->boolean('is_active')])->all();

        if ($company->hasOperationalHistory() && $data['name'] !== $company->name) {
            return back()
                ->withErrors(['name' => 'Company name cannot be changed after placement or internship history exists.'])
                ->withInput();
        }

        if ($company->is_active && ! $data['is_active'] && $company->hasActivePlacementDrives()) {
            return back()
                ->withErrors(['is_active' => 'Company cannot be deactivated while upcoming or ongoing placement drives exist.'])
                ->withInput();
        }

        $company->update($data);

        return redirect()->route('admin.companies.index')->with('success', 'Company updated successfully.');
    }

    public function destroy(Company $company)
    {
        $this->authorizePlacementOperations(request());

        $hasOperationalHistory = $company->hasOperationalHistory();

        if ($hasOperationalHistory) {
            $company->update(['is_active' => false]);
            ActivityLog::record('archived', "Company archived instead of deleted to preserve placement and internship history: {$company->name}", $company);

            return redirect()->route('admin.companies.index')->with('success', 'Company archived. Placement and internship history was preserved.');
        }

        $company->delete();
        return redirect()->route('admin.companies.index')->with('success', 'Company deleted.');
    }

    private function authorizePlacementOperations(Request $request): void
    {
        abort_unless($request->user() && AccessControl::canManagePlacementOperations($request->user()), 403);
    }
}
