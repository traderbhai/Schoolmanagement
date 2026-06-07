<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimetableVersion extends Model {
    protected $fillable = [
        'program_id','term_id','batch_id','version_number','status',
        'created_by','published_by','published_at','effective_from','notes',
    ];

    protected $casts = ['published_at' => 'datetime', 'effective_from' => 'date'];

    public function program(): BelongsTo   { return $this->belongsTo(Program::class); }
    public function term(): BelongsTo      { return $this->belongsTo(Term::class); }
    public function batch(): BelongsTo     { return $this->belongsTo(Batch::class); }
    public function creator(): BelongsTo   { return $this->belongsTo(User::class, 'created_by'); }
    public function publisher(): BelongsTo { return $this->belongsTo(User::class, 'published_by'); }
    public function entries(): HasMany     { return $this->hasMany(TimetableEntry::class); }

    public function isPublished(): bool { return $this->status === 'published'; }
    public function isDraft(): bool     { return $this->status === 'draft'; }
}
