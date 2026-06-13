<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionScriptTemplate extends Model
{
    protected $fillable = ['name', 'stage', 'program_id', 'steps', 'is_active', 'created_by'];
    protected $casts = ['steps' => 'array', 'is_active' => 'boolean'];
    public function completions() { return $this->hasMany(AdmissionScriptCompletionLog::class, 'script_template_id'); }
}
