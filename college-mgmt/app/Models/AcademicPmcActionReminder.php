<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPmcActionReminder extends Model
{
    protected $fillable = ['work_item_id', 'owner_user_id', 'reminder_type', 'status', 'due_at', 'sent_at', 'escalated_at', 'escalated_to', 'message', 'metadata'];
    protected $casts = ['due_at' => 'datetime', 'sent_at' => 'datetime', 'escalated_at' => 'datetime', 'metadata' => 'array'];

    public function workItem() { return $this->belongsTo(AcademicPmcWorkItem::class, 'work_item_id'); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function escalatedTo() { return $this->belongsTo(User::class, 'escalated_to'); }
}
