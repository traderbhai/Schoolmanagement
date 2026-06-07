<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExamRegistration extends Model {
    use HasFactory;
    protected $fillable = ['student_id','exam_id','status','attendance_eligible','fee_cleared','remarks','approved_by'];
    protected $casts = ['attendance_eligible'=>'boolean','fee_cleared'=>'boolean'];
    public function student() { return $this->belongsTo(Student::class); }
    public function exam() { return $this->belongsTo(Exam::class); }
    public function approver() { return $this->belongsTo(User::class,'approved_by'); }
}
