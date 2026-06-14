<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicDeanRiskMitigation extends Model
{
    protected $fillable = ['risk_snapshot_id', 'owner_user_id', 'status', 'plan', 'due_at', 'metadata'];

    protected $casts = ['due_at' => 'datetime', 'metadata' => 'array'];
}
