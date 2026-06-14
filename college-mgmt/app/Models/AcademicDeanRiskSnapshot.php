<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicDeanRiskSnapshot extends Model
{
    protected $fillable = ['program_id', 'batch_id', 'term_id', 'branch', 'score', 'band', 'trend', 'metrics', 'reasons', 'snapshot_date'];

    protected $casts = ['metrics' => 'array', 'reasons' => 'array', 'snapshot_date' => 'date'];

    public function program() { return $this->belongsTo(Program::class); }
}
