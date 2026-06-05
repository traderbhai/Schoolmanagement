<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnrollmentConfirmation extends Model
{
    protected $fillable = [
        'applicant_id', 'student_id', 'confirmed_by', 'confirmed_at',
        'enrollment_number', 'roll_number', 'batch_id', 'term_id',
        'notes', 'status', 'error_message',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];

    public function applicant()   { return $this->belongsTo(Applicant::class); }
    public function student()     { return $this->belongsTo(Student::class); }
    public function confirmedBy() { return $this->belongsTo(User::class, 'confirmed_by'); }
    public function batch()       { return $this->belongsTo(Batch::class); }
    public function term()        { return $this->belongsTo(Term::class); }
}
