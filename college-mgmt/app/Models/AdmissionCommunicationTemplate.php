<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionCommunicationTemplate extends Model
{
    protected $fillable = ['name', 'channel', 'purpose', 'subject', 'body', 'variables', 'is_active', 'created_by'];

    protected $casts = ['variables' => 'array', 'is_active' => 'boolean'];
}
