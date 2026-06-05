<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    protected $fillable = ['user_id', 'title', 'content', 'audience', 'priority', 'publish_date', 'expiry_date', 'published_at', 'expires_at', 'is_published'];
    protected $casts = ['publish_date' => 'date', 'expiry_date' => 'date', 'published_at' => 'datetime', 'expires_at' => 'datetime', 'is_published' => 'boolean'];

    public function user() { return $this->belongsTo(User::class); }

    public function scopeActive($query) {
        return $query->where('is_published', true)
            ->where('publish_date', '<=', now())
            ->where(fn($q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', now()));
    }
}
