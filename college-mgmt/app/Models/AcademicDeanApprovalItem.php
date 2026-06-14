<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicDeanApprovalItem extends Model
{
    protected $fillable = ['approval_type', 'title', 'source_type', 'source_key', 'owner_user_id', 'status', 'risk_level', 'due_at', 'decision_reason', 'metadata'];

    protected $casts = ['due_at' => 'datetime', 'metadata' => 'array'];

    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
}
