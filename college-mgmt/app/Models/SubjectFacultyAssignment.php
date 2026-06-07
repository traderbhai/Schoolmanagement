<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectFacultyAssignment extends Model {
    protected $fillable = [
        'subject_id','teacher_id','term_id','batch_id','program_id','assigned_by','is_primary',
    ];

    protected $casts = ['is_primary' => 'boolean'];

    public function subject(): BelongsTo   { return $this->belongsTo(Subject::class); }
    public function teacher(): BelongsTo   { return $this->belongsTo(Teacher::class); }
    public function term(): BelongsTo      { return $this->belongsTo(Term::class); }
    public function batch(): BelongsTo     { return $this->belongsTo(Batch::class); }
    public function program(): BelongsTo   { return $this->belongsTo(Program::class); }
    public function assignedBy(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by'); }
}
