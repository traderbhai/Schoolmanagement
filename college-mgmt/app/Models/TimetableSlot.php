<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TimetableSlot extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'start_time', 'end_time', 'is_break', 'sort_order', 'is_active'];
    protected $casts = ['is_break' => 'boolean', 'is_active' => 'boolean'];

    public function entries() { return $this->hasMany(TimetableEntry::class); }

    public function getDurationMinutesAttribute(): int
    {
        return \Carbon\Carbon::parse($this->start_time)->diffInMinutes(\Carbon\Carbon::parse($this->end_time));
    }
}
