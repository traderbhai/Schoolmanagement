<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ElectiveRegistrationWindow extends Model {
    protected $fillable = [
        'program_id','term_id','elective_group','opens_at','closes_at',
        'max_selections','status','instructions','created_by',
    ];

    protected $casts = ['opens_at' => 'datetime', 'closes_at' => 'datetime'];

    public function program(): BelongsTo  { return $this->belongsTo(Program::class); }
    public function term(): BelongsTo     { return $this->belongsTo(Term::class); }
    public function creator(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }

    public function isOpen(): bool {
        return $this->status === 'open'
            && now()->between($this->opens_at, $this->closes_at);
    }
}
