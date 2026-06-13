<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionScriptCompletionLog extends Model
{
    protected $fillable = ['script_template_id', 'call_log_id', 'subject_type', 'subject_id', 'counsellor_user_id', 'step_results', 'compliance_percent', 'metadata'];
    protected $casts = ['step_results' => 'array', 'compliance_percent' => 'float', 'metadata' => 'array'];
    public function subject() { return $this->morphTo(); }
    public function template() { return $this->belongsTo(AdmissionScriptTemplate::class, 'script_template_id'); }
}
