<?php

namespace App\Mail;

use App\Models\Student;
use App\Models\ClassModel;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StudentEnrolledNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $student;
    public $class;

    /**
     * Create a new message instance.
     */
    public function __construct(Student $student, ClassModel $class)
    {
        $this->student = $student;
        $this->class = $class;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('New Student Enrolled in Your Class')
                    ->view('emails.student-enrolled')
                    ->with([
                        'student' => $this->student,
                        'class' => $this->class,
                    ]);
    }
}
