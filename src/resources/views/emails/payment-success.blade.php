{{-- filepath: /home/chamod-randeni/Documents/optiomax project/optiomax_runner/src/resources/views/emails/payment-success.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>Payment Successful</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #27ae60; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f8f9fa; }
        .footer { padding: 15px; text-align: center; color: #666; }
        .success-box { background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>✅ Payment Successful - Thank You!</h2>
        </div>
        
        <div class="content">
            <p>Dear {{ $userName }},</p>
            
            <p>Thank you for your payment! We have successfully processed your subscription payment for <strong>{{ $tenantName }}</strong>.</p>
            
            <div class="success-box">
                <h3 style="margin-top: 0;">💰 Payment Details</h3>
                <strong>Amount:</strong> ${{ number_format($paymentResult['amount_paid'] ?? 0, 2) }}<br>
                <strong>Date:</strong> {{ now()->format('F d, Y') }}<br>
                <strong>Billing Cycle:</strong> {{ ucfirst($subscription->billing_cycle ?? 'monthly') }}<br>
                <strong>Status:</strong> {{ ucfirst($paymentResult['status'] ?? 'completed') }}
            </div>
            
            <p>🧾 Your receipt is attached to this email for your records.</p>
            
            <p>🚀 Your services will continue uninterrupted. Thank you for choosing {{ config('app.name') }}!</p>
            
            <p>If you have any questions about this payment, please don't hesitate to contact our support team.</p>
        </div>
        
        <div class="footer">
            <p>Best regards,<br>{{ config('app.name') }} Team</p>
            <p><small>This is an automated message. Please do not reply to this email.</small></p>
        </div>
    </div>
</body>
</html>