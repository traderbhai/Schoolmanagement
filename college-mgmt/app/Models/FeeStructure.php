<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class FeeStructure extends Model
{
    protected $fillable = ['course_id', 'program_id', 'batch_id', 'academic_year_id', 'fee_type', 'amount', 'semester_number', 'description'];
    protected $casts = ['amount' => 'decimal:2'];

    public function course() { return $this->belongsTo(Course::class); }
    public function program() { return $this->belongsTo(Program::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class); }
    public function payments() { return $this->hasMany(FeePayment::class); }
}
