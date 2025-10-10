<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $tenant;
    public $invoiceData;
    public $invoicePdf;

    public function __construct($user, $tenant, $invoiceData, $invoicePdf)
    {
        $this->user = $user;
        $this->tenant = $tenant;
        $this->invoiceData = $invoiceData;
        $this->invoicePdf = $invoicePdf;
    }

    public function build()
    {
        $mail = $this->subject('Payment Receipt - ' . $this->invoiceData['invoice_number'])
                     ->view('emails.payment-receipt')
                     ->with([
                         'user' => $this->user,
                         'tenant' => $this->tenant,
                         'invoiceData' => $this->invoiceData
                     ]);

        // Attach PDF if available
        if ($this->invoicePdf) {
            $mail->attachData(
                $this->invoicePdf, 
                'invoice-' . $this->invoiceData['invoice_number'] . '.pdf',
                ['mime' => 'application/pdf']
            );
        }

        return $mail;
    }
}
