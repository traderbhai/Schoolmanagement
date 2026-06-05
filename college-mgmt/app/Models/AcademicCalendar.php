<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AcademicCalendar extends Model
{
    use HasFactory;

    protected $fillable = [
        'term_id', 'event_date', 'event_name', 'description',
        'event_type', 'is_holiday',
    ];

    protected $casts = [
        'event_date' => 'date',
        'is_holiday' => 'boolean',
    ];

    public function term() { return $this->belongsTo(Term::class); }

    public function isHoliday(): bool
    {
        return $this->is_holiday;
    }

    public function isExamWeek(): bool
    {
        return $this->event_type === 'exam_week';
    }

    public function isPast(): bool
    {
        return $this->event_date->isPast();
    }

    public function isUpcoming(): bool
    {
        return $this->event_date->isFuture();
    }
}
