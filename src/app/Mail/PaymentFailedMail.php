<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $tenant;
    public $package;
    public $errorMessage;

    public function __construct($user, $tenant, $package, $errorMessage)
    {
        $this->user = $user;
        $this->tenant = $tenant;
        $this->package = $package;
        $this->errorMessage = $errorMessage;
    }

    public function build()
    {
        return $this->subject('Payment Failed - Action Required')
                    ->view('emails.payment-failed')
                    ->with([
                        'user' => $this->user,
                        'tenant' => $this->tenant,
                        'package' => $this->package,
                        'errorMessage' => $this->errorMessage
                    ]);
    }
}
                        'errorMessage' => $this->errorMessage,
                        'subscription' => $this->subscription,
                        'supportUrl' => config('app.url') . '/support',
                        'paymentUrl' => config('app.url') . '/billing/payment-methods',
                    ]);
    }
}
