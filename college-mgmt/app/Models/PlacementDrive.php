<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlacementDrive extends Model
{
    protected $fillable = ['company_id','title','description','job_role','package','min_cgpa','eligibility','drive_date','last_apply_date','location','status','vacancies'];
    protected $casts = ['drive_date' => 'date', 'last_apply_date' => 'date'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function placements()
    {
        return $this->hasMany(Placement::class, 'drive_id');
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'placements', 'drive_id', 'student_id')
            ->withPivot('application_status','offered_package','joining_date','remarks','offer_letter_number')
            ->withTimestamps();
    }
}
