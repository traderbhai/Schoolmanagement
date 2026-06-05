<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'email_application_updates',
        'email_payment_updates',
        'email_result_published',
        'email_notices',
    ];

    protected $casts = [
        'email_application_updates' => 'boolean',
        'email_payment_updates'     => 'boolean',
        'email_result_published'    => 'boolean',
        'email_notices'             => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
