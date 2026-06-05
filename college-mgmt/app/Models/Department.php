<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Department extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'code', 'description', 'head_name', 'is_active'];

    public function courses() { return $this->hasMany(Course::class); }
    public function subjects() { return $this->hasMany(Subject::class); }
    public function teachers() { return $this->hasMany(Teacher::class); }
    public function students() { return $this->hasMany(Student::class); }
}
