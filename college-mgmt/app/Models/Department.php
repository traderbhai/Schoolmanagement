<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['name', 'code', 'description', 'head_name', 'is_active'];

    public function courses() { return $this->hasMany(Course::class); }
    public function subjects() { return $this->hasMany(Subject::class); }
    public function teachers() { return $this->hasMany(Teacher::class); }
    public function students() { return $this->hasMany(Student::class); }
}
