<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionAutomationConflictLog extends Model
{
    protected $fillable = ['automation_id', 'subject_type', 'subject_id', 'conflict_key', 'severity', 'status', 'message', 'metadata'];
    protected $casts = ['metadata' => 'array'];
    public function subject() { return $this->morphTo(); }
}
