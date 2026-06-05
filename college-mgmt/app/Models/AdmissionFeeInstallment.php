<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AdmissionFeeInstallment extends Model
{
    protected $fillable = ['program_id', 'batch_id', 'name', 'amount', 'installment_number', 'due_date', 'description', 'is_active'];
    protected $casts = ['due_date' => 'date', 'is_active' => 'boolean'];
    public function program() { return $this->belongsTo(Program::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function payments() { return $this->hasMany(AdmissionPayment::class, 'admission_fee_installment_id'); }
}
