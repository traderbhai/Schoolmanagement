<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AcademicYear extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'start_year', 'end_year', 'start_date', 'end_date', 'is_current'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'is_current' => 'boolean'];

    public function semesters() { return $this->hasMany(Semester::class); }
    public function feeStructures() { return $this->hasMany(FeeStructure::class); }
    public function batches() { return $this->hasMany(Batch::class); }

    public static function current() { return static::where('is_current', true)->first(); }
}
