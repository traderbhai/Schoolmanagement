<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{FeeStructure, FeePayment, Student, Course, AcademicYear};
use Illuminate\Http\Request;

class FeeController extends Controller
{
    public function index() {
        $structures = FeeStructure::with(['course','academicYear'])->paginate(20);
        return view('admin.fees.index', compact('structures'));
    }
    public function create() {
        $courses = Course::where('is_active',true)->get();
        $years = AcademicYear::all();
        return view('admin.fees.create', compact('courses','years'));
    }
    public function store(Request $request) {
        $data = $request->validate([
            'course_id'        => 'required|exists:courses,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'fee_type'         => 'required|string|max:100',
            'amount'           => 'required|numeric|min:0',
            'semester_number'  => 'nullable|integer',
            'description'      => 'nullable|string',
        ]);
        FeeStructure::create($data);
        return redirect()->route('admin.fees.index')->with('success', 'Fee structure created.');
    }
    public function show(FeeStructure $fee) {
        $fee->load(['course','academicYear','payments.student.user']);
        return view('admin.fees.show', compact('fee'));
    }
    public function edit(FeeStructure $fee) {
        $courses = Course::where('is_active',true)->get();
        $years = AcademicYear::all();
        return view('admin.fees.edit', compact('fee','courses','years'));
    }
    public function update(Request $request, FeeStructure $fee) {
        $data = $request->validate([
            'course_id'        => 'required|exists:courses,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'fee_type'         => 'required|string|max:100',
            'amount'           => 'required|numeric|min:0',
            'semester_number'  => 'nullable|integer',
        ]);
        $fee->update($data);
        return redirect()->route('admin.fees.index')->with('success', 'Updated.');
    }
    public function destroy(FeeStructure $fee) {
        $fee->delete();
        return redirect()->route('admin.fees.index')->with('success', 'Deleted.');
    }

    public function collectPayment(Request $request) {
        $students = Student::with('user')->where('status','active')->get();
        $structures = FeeStructure::with(['course','academicYear'])->get();
        return view('admin.fees.collect', compact('students','structures'));
    }

    public function storePayment(Request $request) {
        $data = $request->validate([
            'student_id'       => 'required|exists:students,id',
            'fee_structure_id' => 'required|exists:fee_structures,id',
            'amount_paid'      => 'required|numeric|min:0',
            'payment_date'     => 'required|date',
            'payment_method'   => 'required|in:cash,online,cheque,dd',
            'transaction_id'   => 'nullable|string',
            'remarks'          => 'nullable|string',
        ]);
        $data['receipt_number'] = 'RCP-' . strtoupper(uniqid());
        FeePayment::create($data);
        return redirect()->route('admin.fees.index')->with('success', 'Payment recorded. Receipt: ' . $data['receipt_number']);
    }
}
