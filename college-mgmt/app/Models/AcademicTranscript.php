<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicTranscript extends Model {
    protected $fillable = ['student_id','academic_year','cgpa','total_credits_earned','status','issued_by','issued_at','semester_data'];
    protected $casts = ['issued_at'=>'datetime','semester_data'=>'array'];
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function issuedBy(): BelongsTo { return $this->belongsTo(User::class,'issued_by'); }
}
