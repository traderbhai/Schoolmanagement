<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumChange extends Model {
    protected $fillable = ['program_id','subject_id','proposed_by','title','description',
        'change_type','before_state','after_state','status','reviewed_by','review_remarks','submitted_at','reviewed_at'];
    protected $casts = ['before_state'=>'array','after_state'=>'array','submitted_at'=>'datetime','reviewed_at'=>'datetime'];
    public function program(): BelongsTo { return $this->belongsTo(Program::class); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
    public function proposedBy(): BelongsTo { return $this->belongsTo(User::class,'proposed_by'); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(User::class,'reviewed_by'); }
    public function isPending(): bool { return in_array($this->status,['submitted','under_review']); }
}
