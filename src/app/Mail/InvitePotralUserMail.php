<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitePotralUserMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user_name;
    public $inviterName;
    public $email;
    public $password;
    public $signupUrl;
    public $moreDetailsUrl;

    public function __construct($user_name, $inviterName, $email, $password, $signupUrl, $moreDetailsUrl)
    {
        $this->user_name = $user_name;
        $this->inviterName = $inviterName;
        $this->email = $email;
        $this->password = $password;
        $this->signupUrl = $signupUrl;
        $this->moreDetailsUrl = $moreDetailsUrl;
    }

    public function build()
    {
        return $this->subject('Welcome to Optiomax')
                    ->view('emails.potralUserInvitationEmail')
                    ->with([
                        'user_name' => $this->user_name,
                        'inviterName' => $this->inviterName,
                        'email' => $this->email,
                        'password' => $this->password,
                        'signupUrl' => $this->signupUrl,
                        'appDetailsUrl' => $this->moreDetailsUrl,
                    ]);
    }
}
