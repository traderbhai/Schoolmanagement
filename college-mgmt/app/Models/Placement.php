<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Placement extends Model
{
    protected $fillable = ['drive_id','student_id','application_status','offer_letter_number','offered_package','joining_date','remarks'];
    protected $casts = ['joining_date' => 'date'];

    public function drive()
    {
        return $this->belongsTo(PlacementDrive::class, 'drive_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
