<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionPartner extends Model
{
    protected $fillable = [
        'name', 'type', 'contact_user_id', 'contact_name', 'contact_email',
        'contact_phone', 'allowed_program_ids', 'status', 'commission_status',
        'approved_by', 'approved_at',
    ];

    protected $casts = ['allowed_program_ids' => 'array', 'approved_at' => 'datetime'];

    public function leads() { return $this->hasMany(Lead::class); }
    public function applicants() { return $this->hasMany(Applicant::class); }
}
