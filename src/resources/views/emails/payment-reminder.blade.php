{{-- filepath: /home/chamod-randeni/Documents/optiomax project/optiomax_runner/src/resources/views/emails/payment-reminder.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>Payment Reminder</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #f39c12; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #f8f9fa; }
        .footer { padding: 15px; text-align: center; color: #666; }
        .button { background-color: #e74c3c; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; }
        .warning-box { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>⚠️ Payment Reminder - Action Required</h2>
        </div>
        
        <div class="content">
            <p>Dear {{ $userName }},</p>
            
            <p>This is a reminder that we've been unable to process the payment for your <strong>{{ $tenantName }}</strong> subscription.</p>
            
            <div class="warning-box">
                <h3 style="margin-top: 0;">📊 Payment Status</h3>
                <strong>Amount Due:</strong> ${{ number_format($retryLog->amount, 2) }}<br>
                <strong>Retry Attempts:</strong> {{ $retryLog->retry_attempt }} of {{ $retryLog->max_retry_attempts }}<br>
                <strong>Next Retry:</strong> {{ $retryLog->next_retry_date->format('F d, Y') }}<br>
                <strong>Grace Period Ends:</strong> {{ $retryLog->grace_period_end->format('F d, Y') }}
            </div>
            
            <p><strong>📝 Last Error:</strong> {{ $retryLog->last_failure_reason }}</p>
            
            <p><strong>🚨 Important:</strong> If payment is not successful by {{ $retryLog->grace_period_end->format('F d, Y') }}, your account may be suspended.</p>
            
            <p><strong>To avoid service interruption:</strong></p>
            <ol>
                <li>Update your payment method</li>
                <li>Ensure sufficient funds are available</li>
                <li>Contact your bank if needed</li>
            </ol>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $paymentUrl }}" class="button">
                    🔧 Update Payment Method Now
                </a>
            </div>
            
            <p>Need help? <a href="{{ $supportUrl }}">Contact our support team</a> - we're here to help!</p>
        </div>
        
        <div class="footer">
            <p>Best regards,<br>{{ config('app.name') }} Team</p>
            <p><small>This is an automated message. Please do not reply to this email.</small></p>
        </div>
    </div>
</body>
</html>