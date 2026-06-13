<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionAssessmentPanelMember extends Model
{
    use HasFactory;

    protected $fillable = ['panel_id', 'user_id', 'role', 'is_chair'];

    protected $casts = ['is_chair' => 'boolean'];

    public function panel(): BelongsTo
    {
        return $this->belongsTo(AdmissionAssessmentPanel::class, 'panel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
