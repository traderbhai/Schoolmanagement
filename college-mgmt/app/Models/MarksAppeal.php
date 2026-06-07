<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MarksAppeal extends Model {
    use HasFactory;
    protected $fillable = ['student_id','exam_result_id','reason','description','marks_claimed','status','reviewed_by','review_remarks','revised_marks','reviewed_at'];
    protected $casts = ['reviewed_at'=>'datetime','marks_claimed'=>'decimal:2','revised_marks'=>'decimal:2'];
    public function student() { return $this->belongsTo(Student::class); }
    public function examResult() { return $this->belongsTo(ExamResult::class); }
    public function reviewer() { return $this->belongsTo(User::class,'reviewed_by'); }
}
