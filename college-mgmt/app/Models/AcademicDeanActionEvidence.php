<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicDeanActionEvidence extends Model
{
    protected $fillable = ['action_item_id', 'uploaded_by', 'title', 'path', 'notes', 'verification_status', 'verified_by', 'verified_at'];

    protected $casts = ['verified_at' => 'datetime'];
}
