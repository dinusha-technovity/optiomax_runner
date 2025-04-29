<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantMails extends Mailable
{
    use Queueable, SerializesModels;

    public $user_name;
    public $inviterName;
    public $email;
    public $password;
    public $signupUrl;
    public $moreDetailsUrl;
    public $emailType;
    public $moreInfo;


    public function __construct($user_name, $inviterName, $email, $password, $signupUrl, $moreDetailsUrl, $moreInfo, $emailType)
    {
        $this->user_name = $user_name;
        $this->inviterName = $inviterName;
        $this->email = $email;
        $this->password = $password;
        $this->signupUrl = $signupUrl;
        $this->moreDetailsUrl = $moreDetailsUrl;
        $this->moreInfo =$moreInfo;
        $this->emailType = $emailType;
    }

    public function build()
    {
        if($this->emailType === "USER_INVITATION") {

            return $this->subject('Welcome to Optiomax')
            ->view('emails.userInvitationEmail')
            ->with([
                'user_name' => $this->user_name,
                'inviterName' => $this->inviterName,
                'signupUrl' => $this->signupUrl,
                'appDetailsUrl' => $this->moreDetailsUrl,
            ]);
        }
        elseif ($this->emailType === "USER_INVITATION_PASSWORD") {

            return $this->subject('Welcome to Optiomax')
            ->view('emails.userPasswordSendingEmail')
            ->with([
                'user_name' => $this->user_name,
                'password' => $this->password,
                'appDetailsUrl' => $this->moreDetailsUrl,
            ]);
        }
        elseif ($this->emailType === "USER_PASSWORD_RESET") {

            return $this->subject('Account Password Reset')
            ->view('emails.tenantUserPasswordResetEmail')
            ->with([
                'user_name' => $this->user_name,
                'signupUrl' => $this->signupUrl,
                'appDetailsUrl' => $this->moreDetailsUrl,
            ]);
        }
        elseif ($this->emailType === "USER_PASSWORD_RESET_PASSWORD") {

            return $this->subject('Account Temporary Password')
            ->view('emails.userPasswordSendingEmail')
            ->with([
                'user_name' => $this->user_name,
                'password' => $this->password,
                'appDetailsUrl' => $this->moreDetailsUrl,
            ]);
        }
    }
}
