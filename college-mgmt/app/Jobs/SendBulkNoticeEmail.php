<?php
namespace App\Jobs;

use App\Mail\NoticeMail;
use App\Models\Notice;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendBulkNoticeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Notice $notice) {}

    public function handle(): void
    {
        Student::with('user')->where('status', 'active')->each(function (Student $student) {
            Mail::to($student->user->email)->queue(new NoticeMail($this->notice));
        });
    }
}
