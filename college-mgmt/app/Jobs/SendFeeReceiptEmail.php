<?php
namespace App\Jobs;

use App\Mail\FeeReceiptMail;
use App\Models\FeePayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendFeeReceiptEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public FeePayment $payment) {}

    public function handle(): void
    {
        $this->payment->loadMissing(['student.user', 'feeStructure']);
        Mail::to($this->payment->student->user->email)->send(new FeeReceiptMail($this->payment));
    }
}
