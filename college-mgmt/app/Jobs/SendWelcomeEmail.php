<?php
namespace App\Jobs;

use App\Mail\WelcomeStudentMail;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Student $student) {}

    public function handle(): void
    {
        $this->student->loadMissing('user');
        Mail::to($this->student->user->email)->send(new WelcomeStudentMail($this->student));
    }
}
