<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PotralUserPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user_name;
    public $email;
    public $password;
    public $signupUrl;
    public $moreDetailsUrl;

    public function __construct($user_name, $email, $password, $signupUrl, $moreDetailsUrl)
    {
        $this->user_name = $user_name;
        $this->email = $email;
        $this->password = $password;
        $this->signupUrl = $signupUrl;
        $this->moreDetailsUrl = $moreDetailsUrl;
    }

    public function build()
    {
        return $this->subject('Reset Your Potral Account Password')
                    ->view('emails.potralUserPasswordResetEmail')
                    ->with([
                        'user_name' => $this->user_name,
                        'email' => $this->email,
                        'password' => $this->password,
                        'signupUrl' => $this->signupUrl,
                        'appDetailsUrl' => $this->moreDetailsUrl,
                    ]);
    }
}