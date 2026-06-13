<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionProcessStage extends Model
{
    protected $fillable = [
        'admission_process_template_id', 'name', 'stage_key', 'sequence',
        'is_required', 'sla_hours', 'config',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'config' => 'array',
    ];

    public function template()
    {
        return $this->belongsTo(AdmissionProcessTemplate::class, 'admission_process_template_id');
    }
}
