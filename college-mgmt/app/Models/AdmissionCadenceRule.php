<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionCadenceRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'target_type', 'reason', 'channel', 'template_id', 'repeat_rule',
        'max_attempts', 'escalate_after_attempts', 'is_active', 'created_by',
    ];

    protected $casts = [
        'repeat_rule' => 'array',
        'is_active' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(AdmissionCommunicationTemplate::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(AdmissionReminderSchedule::class, 'cadence_rule_id');
    }
}
