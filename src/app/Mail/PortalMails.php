<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PortalMails extends Mailable
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
    public $supportEmail;
    public $supportContact;

    /**
     * Create a new message instance.
     */
    public function __construct($user_name, $inviterName, $email, $password, $signupUrl, $moreDetailsUrl, $moreInfo, $emailType, $supportEmail = null, $supportContact = null)
    {
        $this->user_name = $user_name;
        $this->inviterName = $inviterName;
        $this->email = $email;
        $this->password = $password;
        $this->signupUrl = $signupUrl;
        $this->moreDetailsUrl = $moreDetailsUrl;
        $this->moreInfo =$moreInfo;
        $this->emailType = $emailType;
        $this->supportEmail = $supportEmail;
        $this->supportContact = $supportContact;
    }
 
    public function build()
    {
        if($this->emailType === "PORTAL_USER_INVITATION") {

            return $this->subject('Welcome to Optiomax PORTAL')
            ->view('emails.portalUserInvitationEmail')
            ->with([
                'user_name' => $this->user_name,
                'inviterName' => $this->inviterName,
                'signupUrl' => $this->signupUrl,
                'appDetailsUrl' => $this->moreDetailsUrl,
                'supportEmail' => env('SUPPORT_EMAIL', 'support@optiomax.com'),
                'supportContact' => env('SUPPORT_CONTACT', '+1234567890'),
            ]);
        }
        elseif ($this->emailType === "PORTAL_USER_INVITATION_PASSWORD") {

            return $this->subject('Your Optiomax Portal Account Temporary Password')
            ->view('emails.portalUserPasswordSendingEmail')
            ->with([
                'user_name' => $this->user_name,
                'password' => $this->password,
                'appDetailsUrl' => $this->moreDetailsUrl,
                'supportEmail' => env('SUPPORT_EMAIL', 'support@optiomax.com'),
                'supportContact' => env('SUPPORT_CONTACT', '+1234567890'),
            ]);
        }
        elseif ($this->emailType === "PORTAL_USER_PASSWORD_CHANGE") {

            return $this->subject('Your Password Has Changed')
            ->view('emails.password_changed')
            ->with([
                'user_name' => $this->user_name,
                'appDetailsUrl' => $this->moreDetailsUrl,
            ]);
        }
        elseif ($this->emailType === "RESET_PORTAL_ACC_EMAIL") {

            return $this->subject('Reset Your Account')
            ->view('emails.potralUserPasswordResetEmail')
            ->with([
                'user_name' => $this->user_name,
                'appDetailsUrl' => $this->moreDetailsUrl,
                'signupUrl'=>$this->signupUrl,
            ]);
        }

        elseif ($this->emailType === "RESET_PORTAL_ACC_PASSWORD_EMAIL") {

            return $this->subject('Temporary password for Reset Your Account')
            ->view('emails.portalUserPasswordSendingEmail')
            ->with([
                'user_name' => $this->user_name,
                'password'=>$this->password,
                'appDetailsUrl' => $this->moreDetailsUrl,
                'supportEmail' => env('SUPPORT_EMAIL', 'support@optiomax.com'),
                'supportContact' => env('SUPPORT_CONTACT', '+1234567890'),
                
            ]);
        }
    }

   
}
