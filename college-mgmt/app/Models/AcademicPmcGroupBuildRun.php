<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcGroupBuildRun extends Model
{
    protected $fillable = [
        'title', 'program_id', 'batch_id', 'term_id', 'subject_id',
        'group_type', 'strategy', 'min_capacity', 'max_capacity',
        'students_considered', 'groups_created', 'warnings_count',
        'status', 'created_by', 'warnings', 'metadata',
    ];

    protected $casts = ['warnings' => 'array', 'metadata' => 'array'];

    public function program() { return $this->belongsTo(Program::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function term() { return $this->belongsTo(Term::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
