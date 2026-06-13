<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionLeadScore extends Model
{
    protected $fillable = ['lead_id', 'score', 'band', 'explanation', 'scored_by', 'scored_at'];

    protected $casts = ['explanation' => 'array', 'scored_at' => 'datetime'];

    public function lead() { return $this->belongsTo(Lead::class); }
}
