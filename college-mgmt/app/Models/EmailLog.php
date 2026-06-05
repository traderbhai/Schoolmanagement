<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $fillable = [
        'mailable_class',
        'to_email',
        'to_name',
        'subject',
        'status',
        'error_message',
        'queued_at',
        'sent_at',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'sent_at'   => 'datetime',
    ];
}
