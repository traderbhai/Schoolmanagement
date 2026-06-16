<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAnomalyLog extends Model {
    protected $fillable = ['exam_id','student_id','anomaly_type','description','severity','action_taken','resolution_notes','reported_by','resolved_by','resolved_at'];
    protected $casts = ['resolved_at'=>'datetime'];
    public function exam(): BelongsTo { return $this->belongsTo(Exam::class); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function reportedBy(): BelongsTo { return $this->belongsTo(User::class,'reported_by'); }
    public function resolvedBy(): BelongsTo { return $this->belongsTo(User::class,'resolved_by'); }
}
