<x-email-layout :app-details-url="$appDetailsUrl" title="User Invitation Email">

        <!-- Main Content -->
        <div style="padding: 60px; text-align: center; background-color: #ffffff; border: 1px solid #e0e0e0; border-radius: 6px; margin: 10px 0;">
            <h2 style="margin: 0 0 10px; color: #000; font-size: 20px; font-weight: 700; line-height: 22px; margin-bottom: 29px;">
                Temporary Password
            </h2>
            <p style="margin: 10px 0; color: #000; font-size: 16px; line-height: 22px;">
                Dear {{ $user_name }},
            </p>
            <p style="margin: 10px 0; color: #000; font-size: 16px; line-height: 22px;">
                Your Optiomax account has been created successfully. Please use the temporary password below to log in for the first time:
            </p>

            <p style="margin: 9px 0; color: #000; font-size: 15px; line-height: 20px;">Please do not share this password with anyone.</p>

            <p style="margin: 9px 0; color: #000; font-size: 15px; line-height: 20px;">For your security, we recommend changing this password immediately after logging in.</p>

            <p style="margin: 9px 0; color: #000; font-size: 15px; line-height: 20px;">If you did not request this or need assistance, please contact our support team at
                <a href="mailto:{{ $supportEmail }}" style="color: #1677FF; text-decoration: none;">{{ $supportEmail }}</a> or 
            <a href="tel:{{ $supportContact }}" style="color: #1677FF; text-decoration: none;">{{ $supportContact }}</a>.
            </p>

            <table style="width: 100%; font-family: Arial, sans-serif;">
                <tr>
                    <td style="padding: 10px;  text-align: center;">
                        <strong>Your Temporary Password:</strong>
                        <div style="display: block; padding: 10px; background-color: #f4f4f4; border: 1px solid #ddd; font-size: 16px; 
                        color: #333; margin-top: 5px; word-break: break-all;">
                            {{ $password }}
                        </div>
                        <p style="font-size: 12px; color: #777; margin-top: 5px;">(Please copy and paste it manually.)</p>
                    </td>
                </tr>
            </table>

        </div>

</x-email-layout>