<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'notifications';

    protected $fillable = ['user_id', 'title', 'message', 'type', 'action_url', 'is_read', 'read_at'];
    protected $casts = ['is_read' => 'boolean', 'read_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function markAsRead(): void { $this->update(['is_read' => true, 'read_at' => now()]); }
}
