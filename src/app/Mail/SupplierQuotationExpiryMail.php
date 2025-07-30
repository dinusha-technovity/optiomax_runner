<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SupplierQuotationExpiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public $request;

    /**
     * Create a new message instance.
     */
    public function __construct($request)
    {
        $this->request = $request;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        // return $this->subject('Supplier Quotation Request Expiration Reminder')
        //             ->view('emails.supplier_quotation_expiry');
        return $this->view('emails.supplier_quotation_expiry')
                    ->subject('Your Quotation Will Expire Soon')
                    ->with(['request' => $this->request]);
    }
}
