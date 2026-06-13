<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionCounsellorTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'period_type', 'period_start', 'period_end', 'target_calls',
        'target_followups', 'target_applications', 'target_enrollments',
        'created_by', 'metadata',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
