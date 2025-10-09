{{-- filepath: /home/chamod-randeni/Documents/optiomax project/optiomax_runner/src/resources/views/emails/payment-receipt.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>Payment Receipt</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2563eb;">Payment Receipt</h1>
        
        <p>Dear {{ $user->name }},</p>
        
        <p>Thank you for your payment! Your subscription has been successfully processed.</p>
        
        <div style="background: #f3f4f6; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <h3>Payment Details:</h3>
            <p><strong>Invoice Number:</strong> {{ $invoiceData['invoice_number'] }}</p>
            <p><strong>Date:</strong> {{ $invoiceData['invoice_date']->format('F d, Y') }}</p>
            <p><strong>Amount:</strong> ${{ number_format($invoiceData['pricing_details']['total_amount'], 2) }}</p>
            <p><strong>Billing Cycle:</strong> {{ ucfirst($invoiceData['pricing_details']['billing_cycle']) }}</p>
        </div>
        
        <div style="background: #ecfdf5; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <h3>Subscription Information:</h3>
            <p><strong>Company:</strong> {{ $tenant->tenant_name }}</p>
            <p><strong>Plan:</strong> Professional</p>
            <p><strong>Status:</strong> Active</p>
        </div>
        
        <p>Your invoice is attached to this email. If you have any questions about your payment or subscription, please don't hesitate to contact our support team.</p>
        
        <p>Best regards,<br>
        The {{ config('app.name') }} Team</p>
        
        <hr style="margin: 30px 0; border: none; border-top: 1px solid #e5e7eb;">
        <p style="font-size: 12px; color: #6b7280;">
            This is an automated email. Please do not reply to this message.
        </p>
    </div>
</body>
</html>