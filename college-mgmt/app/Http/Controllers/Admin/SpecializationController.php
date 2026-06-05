<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Specialization, Program};
use Illuminate\Http\Request;

class SpecializationController extends Controller
{
    public function store(Request $r, Program $program)
    {
        $r->validate([
            'name' => 'required|string|max:191',
            'code' => 'required|string|max:20|unique:specializations,code',
        ]);
        $program->specializations()->create($r->all());
        return back()->with('success', 'Specialization added.');
    }

    public function destroy(Specialization $specialization)
    {
        $specialization->delete();
        return back()->with('success', 'Specialization removed.');
    }
}
