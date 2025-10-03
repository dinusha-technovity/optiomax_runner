<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Email</title>
</head>

<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <div style="max-width: 600px; margin: 20px auto; overflow: hidden;">
        <!-- Navigation -->
        <div style="text-align: center; padding: 20px;">
            <img src="http://213.199.44.42/_next/image?url=%2Flogo.png&w=256&q=75" alt="logo" style="width: auto; height: 110px; border-radius: 5px;">
        </div>

        <!-- Header -->
        <div style="
            text-align: center; 
            padding: 20px; 
            color: white; 
            background: linear-gradient(to bottom, rgba(38, 77, 169, 0), rgba(5, 5, 110, 1)),
                        url('http://213.199.44.42/images/backgroundimage/main_side_image.jpeg'); 
            background-size: cover; 
            background-position: center; 
            border: 1px solid #e0e0e0; 
            border-radius: 6px;">
            <p style="margin: 30px 0 0; font-size: 36px; font-weight: bold; line-height: normal;">
                Integrate. Automate. Streamline.
            </p>
        </div>

        <!-- Main Content -->
        <div style="padding: 60px; text-align: center; background-color: #ffffff; border: 1px solid #e0e0e0; border-radius: 6px; margin: 10px 0;">
            <h2 style="margin: 0 0 10px; color: #000; font-size: 20px; font-weight: 700; line-height: 22px; margin-bottom: 29px;">
                Your Optiomax Account Password
            </h2>
            <p style="margin: 10px 0; color: #000; font-size: 16px; line-height: 22px;">
                Dear {{ $user_name }},
            </p>
            <p style="margin: 10px 0; color: #000; font-size: 16px; line-height: 22px;">
                As part of your Optiomax account setup, here is your temporary password:
            </p>

            <p style="margin: 9px 0; color: #000; font-size: 15px; line-height: 20px;">For security reasons, please do not share this password with anyone.</p>

            <p style="margin: 9px 0; color: #000; font-size: 15px; line-height: 20px;">We recommend changing your password after your first login for enhanced security. You can update your password in your account settings.</p>

            <p style="margin: 9px 0; color: #000; font-size: 15px; line-height: 20px;">If you did not request this account or need any assistance, please contact our support team.</p>

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

        <!-- App Details -->
        <div style="padding: 20px; text-align: center;">
            <div style="
                box-shadow: 0px 4px 15px 0px rgba(0, 0, 0, 0.05);
                display: flex; 
                justify-content: center; 
                align-items: center; 
                gap: 15px; 
                background-color: #ffffff; 
                border-radius: 6px; 
                padding: 20px;">
                <img src="https://topflightapps.com/wp-content/uploads/2020/08/visual-vs-text-on-a-dashboard-.png" alt="App Screenshot" style="width: auto; height: 185px; border-radius: 5px;">
                <div style="flex: 1; text-align: left;">
                    <h3 style="margin: 0 0 10px; font-size: 18px; color: #333333;">Optiomax App</h3>
                    <p style="margin: 0; font-size: 12px; color: #666666; line-height: 20px;">
                        Short description about the app goes here.
                    </p>
                    <a href="{{ $appDetailsUrl }}" style="
                        display: inline-block; 
                        margin-top: 10px; 
                        padding: 6px 10px; 
                        color: #000000; 
                        background-color: #ffffff; 
                        border: 1px solid #000; 
                        border-radius: 5px; 
                        font-size: 14px; 
                        font-weight: 600; 
                        text-decoration: none;">
                        More Details
                    </a>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align: center; padding: 20px; font-size: 14px; color: #666666;">
            <p>www.optiomax.com</p>
        </div>
    </div>
</body>

</html>