<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicantDocument extends Model
{
    protected $fillable = [
        'applicant_id', 'required_document_id', 'file_path', 'original_name',
        'file_size_kb', 'status', 'rejection_reason', 'verified_by', 'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function applicant()         { return $this->belongsTo(Applicant::class); }
    public function requiredDocument()  { return $this->belongsTo(RequiredDocument::class); }
    public function verifier()          { return $this->belongsTo(User::class, 'verified_by'); }
}
