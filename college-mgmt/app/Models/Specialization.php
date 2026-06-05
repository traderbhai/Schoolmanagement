<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Specialization extends Model
{
    protected $fillable = ['program_id', 'name', 'code', 'description', 'is_active'];
    public function program() { return $this->belongsTo(Program::class); }
    public function students() { return $this->hasMany(Student::class); }
}
