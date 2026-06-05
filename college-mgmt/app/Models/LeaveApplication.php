<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class LeaveApplication extends Model {
    protected $fillable = ['teacher_id','leave_type','from_date','to_date','days','reason','status','admin_remarks','approved_by','approved_at'];
    protected $casts = ['from_date'=>'date','to_date'=>'date','approved_at'=>'datetime'];

    public function teacher() { return $this->belongsTo(Teacher::class); }
    public function approver() { return $this->belongsTo(User::class,'approved_by'); }
}
