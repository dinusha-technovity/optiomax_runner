<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation Email</title>
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
                You’ve been invited!
            </h2>
            <p style="margin: 10px 0; color: #000; font-size: 16px; line-height: 22px;">
                Dear {{ $user_name }},
            </p>
            <p style="margin: 10px 0; color: #000; font-size: 16px; line-height: 22px;">
                Welcome to Optiomax. You’ve been invited by your colleague {{ $inviterName }}. Sign up Optiomax portal dashboard using below credentials,
            </p>

            <p style="margin: 10px 0; color: #000; font-size: 16px; line-height: 22px;">
            For security reasons, your temporary password will be sent separately. Please check your inbox shortly.
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