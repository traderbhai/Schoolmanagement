<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LibraryMembership extends Model {
    use HasFactory;
    protected $fillable = ['user_id','member_type','max_books_allowed','max_days_allowed','fine_per_day','is_active','expiry_date'];
    protected $casts = ['is_active' => 'boolean', 'expiry_date' => 'date'];

    public function user() { return $this->belongsTo(User::class); }
}
