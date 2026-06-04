<?php
namespace App\Jobs;

use App\Mail\ExamResultsMail;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendExamResultsEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Student $student,
        public string $semesterName,
        public float $sgpa,
        public string $overallResult
    ) {}

    public function handle(): void
    {
        $this->student->loadMissing('user');
        Mail::to($this->student->user->email)->send(
            new ExamResultsMail($this->student, $this->semesterName, $this->sgpa, $this->overallResult)
        );
    }
}
