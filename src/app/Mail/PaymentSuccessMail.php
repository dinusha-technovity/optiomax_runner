<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\tenants;

class PaymentSuccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $tenant;
    public $subscription;
    public $paymentResult;
    public $invoicePdf;

    public function __construct(User $user, tenants $tenant, $subscription, array $paymentResult, $invoicePdf = null)
    {
        $this->user = $user;
        $this->tenant = $tenant;
        $this->subscription = $subscription;
        $this->paymentResult = $paymentResult;
        $this->invoicePdf = $invoicePdf;
    }

    public function build()
    {
        $mail = $this->subject('Payment Successful - Thank You!')
                     ->view('emails.payment-success')
                     ->with([
                         'userName' => $this->user->name,
                         'tenantName' => $this->tenant->tenant_name,
                         'subscription' => $this->subscription,
                         'paymentResult' => $this->paymentResult,
                     ]);

        // Attach PDF if available
        if ($this->invoicePdf && isset($this->invoicePdf['pdf_content'])) {
            $mail->attachData(
                $this->invoicePdf['pdf_content'],
                $this->invoicePdf['filename'] ?? 'receipt.pdf',
                [
                    'mime' => 'application/pdf',
                ]
            );
        }

        return $mail;
    }
}
