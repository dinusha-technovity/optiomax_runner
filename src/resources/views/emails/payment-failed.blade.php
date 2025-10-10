{{-- filepath: /home/chamod-randeni/Documents/optiomax project/optiomax_runner/src/resources/views/emails/payment-failed.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>Payment Failed</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #dc2626;">Payment Failed - Action Required</h1>
        
        <p>Dear {{ $user->name }},</p>
        
        <p>We encountered an issue processing your payment for {{ $tenant->tenant_name }}. Your subscription setup was not completed.</p>
        
        <div style="background: #fef2f2; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #dc2626;">
            <h3 style="color: #dc2626; margin-top: 0;">Payment Issue:</h3>
            <p><strong>Package:</strong> {{ $package->name ?? 'Professional' }}</p>
            <p><strong>Error:</strong> {{ $errorMessage }}</p>
        </div>
        
        <div style="background: #f3f4f6; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <h3>What to do next:</h3>
            <ol>
                <li>Check your payment method is valid and has sufficient funds</li>
                <li>Log into your account to update payment information</li>
                <li>Contact support if you continue to experience issues</li>
            </ol>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ config('app.url') }}/billing" 
               style="background: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">
                Update Payment Method
            </a>
        </div>
        
        <p>If you need assistance, please contact our support team. We're here to help!</p>
        
        <p>Best regards,<br>
        The {{ config('app.name') }} Team</p>
        
        <hr style="margin: 30px 0; border: none; border-top: 1px solid #e5e7eb;">
        <p style="font-size: 12px; color: #6b7280;">
            This is an automated email. Please do not reply to this message.
        </p>
    </div>
</body>
</html>