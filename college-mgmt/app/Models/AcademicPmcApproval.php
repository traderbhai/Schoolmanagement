<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcApproval extends Model
{
    protected $fillable = ['approval_type', 'title', 'description', 'program_id', 'batch_id', 'term_id', 'subject_id', 'requested_by', 'owner_user_id', 'decided_by', 'status', 'sla_status', 'due_at', 'decided_at', 'decision_reason', 'source_type', 'source_key', 'evidence', 'metadata'];

    protected $casts = ['due_at' => 'datetime', 'decided_at' => 'datetime', 'evidence' => 'array', 'metadata' => 'array'];

    public function program() { return $this->belongsTo(Program::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function decider() { return $this->belongsTo(User::class, 'decided_by'); }
}
