<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionCounsellorPlaybook extends Model
{
    protected $fillable = ['name', 'playbook_type', 'program_id', 'stage', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean'];

    public function steps() { return $this->hasMany(AdmissionCounsellorPlaybookStep::class, 'playbook_id')->orderBy('sort_order'); }
    public function program() { return $this->belongsTo(Program::class); }
}
