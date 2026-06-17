<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notice extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'title', 'content', 'audience', 'priority', 'publish_date', 'expiry_date', 'published_at', 'expires_at', 'is_published'];
    protected $casts = ['publish_date' => 'date', 'expiry_date' => 'date', 'published_at' => 'datetime', 'expires_at' => 'datetime', 'is_published' => 'boolean'];

    public function user() { return $this->belongsTo(User::class); }

    public function scopeActive($query) {
        return $query->where('is_published', true)
            ->where('publish_date', '<=', now())
            ->where(fn($q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', now()));
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $audiences = ['all'];

        if ($user->hasAnyRole(['student', 'parent', 'applicant'])) {
            $audiences[] = 'students';
        }

        if ($user->hasRole('teacher')) {
            $audiences[] = 'teachers';
        }

        if ($user->hasAnyRole(['admin', 'director', 'dean_academics', 'hod', 'program_chair', 'exam_cell', 'accounts_officer', 'cmc'])) {
            $audiences[] = 'admin';
        }

        return $query->active()->whereIn('audience', array_values(array_unique($audiences)));
    }
}
