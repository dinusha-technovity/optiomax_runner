<x-email-layout :app-details-url="$appDetailsUrl" title="User Invitation Email">

        <!-- Main Content -->
        <div style="padding: 60px; text-align: center; background-color: #ffffff; border: 1px solid #e0e0e0; border-radius: 6px; margin: 10px 0;">
            <h2 style="margin: 0 0 10px; color: #000; font-size: 20px; font-weight: 700; line-height: 22px; margin-bottom: 29px;">
                You’ve been invited!
            </h2>
            <p style="margin: 10px 0; color: #000; font-size: 16px; line-height: 22px;">
                Dear {{ $user_name }},
            </p>
            <p style="margin: 10px 0; color: #000; font-size: 16px; line-height: 22px;">
                Welcome to Optiomax! You’ve been invited by your colleague, {{ $inviterName }}. Sign in by creating your own Optiomax account.
            </p>

            <p style="margin: 10px 0; color: #000; font-size: 16px; line-height: 22px;">
            For your security, a temporary password will be sent to you in a separate email. Please check your inbox shortly.
            </p>

            <p style="margin: 10px 0; color: #000; font-size: 16px; line-height: 22px;">
           
            </p>
        
            <a href="{{ $signupUrl }}" style="
                display: inline-block; 
                text-decoration: none; 
                margin-top: 37px; 
                padding: 10px 20px; 
                background-color: #1677FF; 
                color: #ffffff; 
                border-radius: 5px; 
                font-size: 14px;  
                font-weight: 600; 
                line-height: 20px;">
                + Sign Up Now
            </a>
        </div>

</x-email-layout>