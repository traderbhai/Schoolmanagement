<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FeePayment extends Model
{
    use HasFactory;
    protected $fillable = [
        'student_id', 'fee_structure_id', 'amount_paid', 'payment_date',
        'receipt_number', 'payment_method', 'transaction_id', 'status', 'remarks',
    ];
    protected $casts = ['payment_date' => 'date', 'amount_paid' => 'decimal:2'];

    public function student() { return $this->belongsTo(Student::class); }
    public function feeStructure() { return $this->belongsTo(FeeStructure::class); }
}
