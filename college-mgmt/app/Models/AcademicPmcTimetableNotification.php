<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcTimetableNotification extends Model
{
    protected $fillable = ['notification_type', 'recipient_type', 'recipient_user_id', 'title', 'message', 'status', 'source_type', 'source_key', 'metadata'];
    protected $casts = ['metadata' => 'array'];
}
