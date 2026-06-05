<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentVerificationRequest extends Model
{
    protected $fillable = ['applicant_id', 'requested_by', 'message', 'status'];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
