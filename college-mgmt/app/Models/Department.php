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
    public function departmentRoles() { return $this->hasMany(DepartmentRole::class); }
    public function departmentTeams() { return $this->hasMany(DepartmentTeam::class); }
    public function departmentMembers() { return $this->hasMany(DepartmentMember::class); }
    public function featureSettings() { return $this->hasMany(DepartmentFeatureSetting::class); }
    public function activityLogs() { return $this->hasMany(DepartmentActivityLog::class); }
    public function impersonationSessions() { return $this->hasMany(DepartmentImpersonationSession::class); }
}
