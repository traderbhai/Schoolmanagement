<?php
namespace App\Http\Controllers\Admin;

use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\{Specialization, Program};
use Illuminate\Http\Request;

class SpecializationController extends Controller
{
    public function store(Request $r, Program $program)
    {
        $this->authorizeAcademicStructure();

        $r->validate([
            'name' => 'required|string|max:191',
            'code' => 'required|string|max:20|unique:specializations,code',
        ]);
        $program->specializations()->create($r->all());
        return back()->with('success', 'Specialization added.');
    }

    public function destroy(Specialization $specialization)
    {
        $this->authorizeAcademicStructure();

        if ($specialization->students()->exists()) {
            return back()->with('error', 'Specializations assigned to students cannot be deleted. Deactivate or rename the specialization instead so student history is preserved.');
        }

        $specialization->delete();
        return back()->with('success', 'Specialization removed.');
    }

    private function authorizeAcademicStructure(): void
    {
        abort_unless(auth()->user() && AccessControl::canManageAcademicStructure(auth()->user()), 403);
    }
}
