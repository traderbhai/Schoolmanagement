<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcAnalyticsSnapshot extends Model
{
    protected $fillable = ['snapshot_type', 'program_id', 'batch_id', 'term_id', 'snapshot_date', 'band', 'score', 'metrics', 'metadata'];

    protected $casts = ['snapshot_date' => 'date', 'metrics' => 'array', 'metadata' => 'array'];

    public function program() { return $this->belongsTo(Program::class); }
}
