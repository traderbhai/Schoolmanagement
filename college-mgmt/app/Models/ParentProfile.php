<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParentProfile extends Model
{
    protected $table = 'parents';
    protected $fillable = ['user_id', 'relation', 'phone', 'occupation', 'annual_income', 'address'];

    public function user() { return $this->belongsTo(User::class); }
    public function students() { return $this->belongsToMany(Student::class, 'parent_student'); }
}
