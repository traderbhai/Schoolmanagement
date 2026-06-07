<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FeePaymentRequest extends Model {
    use HasFactory;
    protected $fillable = ['student_id','fee_demand_id','amount','payment_method','bank_name','transaction_ref','proof_path','submitted_at','verified_by','verified_at','status','notes'];
    protected $casts = ['submitted_at'=>'datetime','verified_at'=>'datetime'];
    public function student() { return $this->belongsTo(Student::class); }
    public function feeDemand() { return $this->belongsTo(FeeDemand::class); }
    public function verifier() { return $this->belongsTo(User::class,'verified_by'); }
}
