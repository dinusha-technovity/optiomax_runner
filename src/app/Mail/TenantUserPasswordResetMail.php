<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantUserPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */

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

    /**
     * Send Password Reset email
     */
    
     public function build()
     {
         return $this->subject('Reset Your App Account Password')
                     ->view('emails.tenantUserPasswordResetEmail')
                     ->with([
                         'user_name' => $this->user_name,
                         'email' => $this->email,
                         'password' => $this->password,
                         'signupUrl' => $this->signupUrl,
                         'appDetailsUrl' => $this->moreDetailsUrl,
                        ]);
     }
}
