<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookCopy extends Model {
    use HasFactory;
    protected $fillable = ['book_id','accession_number','condition_status','is_available'];
    protected $casts = ['is_available' => 'boolean'];

    public function book() { return $this->belongsTo(Book::class); }
    public function issues() { return $this->hasMany(BookIssue::class); }
    public function currentIssue() { return $this->hasOne(BookIssue::class)->where('status','issued'); }
}
