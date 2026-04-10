<?php

namespace App\Mail;

use App\Models\Teacher;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeacherOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Teacher $teacher,
        public string $otpCode,
        public int $expiresInMinutes
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Teacher Account OTP Verification'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.teacher-otp'
        );
    }
}
