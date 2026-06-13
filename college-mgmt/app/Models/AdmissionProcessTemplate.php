<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionProcessTemplate extends Model
{
    protected $fillable = ['program_id', 'batch_id', 'name', 'is_active', 'config'];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
    ];

    public function program() { return $this->belongsTo(Program::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function stages() { return $this->hasMany(AdmissionProcessStage::class)->orderBy('sequence'); }
}
