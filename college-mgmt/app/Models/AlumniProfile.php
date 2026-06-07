<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlumniProfile extends Model
{
    protected $fillable = [
        'student_id', 'graduation_year', 'current_employer', 'current_role',
        'current_salary', 'linkedin_url', 'city', 'country', 'feedback', 'is_verified',
    ];

    protected $casts = [
        'is_verified'    => 'boolean',
        'current_salary' => 'decimal:2',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
