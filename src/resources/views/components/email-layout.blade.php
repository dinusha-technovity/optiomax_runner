<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Optiomax Email' }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <div style="max-width: 600px; margin: 20px auto; overflow: hidden;">
        
        <!-- Navigation -->
        <div style="text-align: center; padding: 20px;">
            <img src="https://app.optiomax.com/_next/image?url=%2Flogo.png&w=256&q=75" 
                 alt="logo" 
                 style="width: auto; height: 110px; border-radius: 5px;">
        </div>

        <!-- Header -->
        <div style="
            text-align: center; 
            padding: 20px; 
            color: white; 
            background: linear-gradient(to bottom, rgba(38, 77, 169, 0), rgba(5, 5, 110, 1)),
                        url('https://app.optiomax.com/images/backgroundimage/main_side_image.jpeg'); 
            background-size: cover; 
            background-position: center; 
            border: 1px solid #e0e0e0; 
            border-radius: 6px;">
            <p style="margin: 30px 0 0; font-size: 36px; font-weight: bold;">
                Integrate. Automate. Streamline.
            </p>
        </div>

        <!-- Main Content -->
        <div style="padding: 60px; background-color: #ffffff; border: 1px solid #e0e0e0; border-radius: 6px; margin: 10px 0;">
            {{ $slot }}
        </div>

        <!-- App Details -->
        <!-- <div style="padding: 20px; text-align: center;">
            <div style="
                box-shadow: 0px 4px 15px rgba(0,0,0,0.05);
                display: flex; 
                justify-content: center; 
                align-items: center; 
                gap: 15px; 
                background-color: #ffffff; 
                border-radius: 6px; 
                padding: 20px;">
                <img src="https://topflightapps.com/wp-content/uploads/2020/08/visual-vs-text-on-a-dashboard-.png" 
                     alt="App Screenshot" 
                     style="width: auto; height: 185px; border-radius: 5px;">
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
        </div> -->

        <!-- Footer -->
        <div style="text-align: center; padding: 20px; font-size: 14px; color: #666666;">
            <table cellpadding="0" cellspacing="0" border="0" align="center" style="margin: 0 auto 15px;">
                <tr>
                    <!-- Facebook -->
                    <td style="padding: 0 5px;">
                        <table cellpadding="0" cellspacing="0" border="0" style="background-color: #171e88; border-radius: 50%; width: 32px; height: 32px;">
                            <tr>
                                <td style="text-align: center; vertical-align: middle; height: 32px;">
                                    <a href="#" style="text-decoration: none; display: block;">
                                        <img src="https://app.optiomax.com/images/socialMediaIcon/facebook.png" width="16" height="16" alt="Facebook" style="display: block; margin: 0 auto;" />
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <!-- Instagram -->
                    <td style="padding: 0 5px;">
                        <table cellpadding="0" cellspacing="0" border="0" style="background-color: #171e88; border-radius: 50%; width: 32px; height: 32px;">
                            <tr>
                                <td style="text-align: center; vertical-align: middle; height: 32px;">
                                    <a href="#" style="text-decoration: none; display: block;">
                                        <img src="https://app.optiomax.com/images/socialMediaIcon/instagram.png" width="16" height="16" alt="Instagram" style="display: block; margin: 0 auto;" />
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <!-- YouTube -->
                    <td style="padding: 0 5px;">
                        <table cellpadding="0" cellspacing="0" border="0" style="background-color: #171e88; border-radius: 50%; width: 32px; height: 32px;">
                            <tr>
                                <td style="text-align: center; vertical-align: middle; height: 32px;">
                                    <a href="#" style="text-decoration: none; display: block;">
                                        <img src="https://app.optiomax.com/images/socialMediaIcon/youtube1.png" width="16" height="16" alt="YouTube" style="display: block; margin: 0 auto;" />
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <!-- X (Twitter) -->
                    <td style="padding: 0 5px;">
                        <table cellpadding="0" cellspacing="0" border="0" style="background-color: #171e88; border-radius: 50%; width: 32px; height: 32px;">
                            <tr>
                                <td style="text-align: center; vertical-align: middle; height: 32px;">
                                    <a href="#" style="text-decoration: none; display: block;">
                                        <img src="https://app.optiomax.com/images/socialMediaIcon/twitter.png" width="16" height="16" alt="X" style="display: block; margin: 0 auto; filter: brightness(0) invert(1);" />
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <!-- Threads -->
                    <td style="padding: 0 5px;">
                        <table cellpadding="0" cellspacing="0" border="0" style="background-color: #171e88; border-radius: 50%; width: 32px; height: 32px;">
                            <tr>
                                <td style="text-align: center; vertical-align: middle; height: 32px;">
                                    <a href="#" style="text-decoration: none; display: block;">
                                        <img src="https://app.optiomax.com/images/socialMediaIcon/threads.png" width="16" height="16" alt="Threads" style="display: block; margin: 0 auto; filter: brightness(0) invert(1);" />
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <!-- LinkedIn -->
                    <td style="padding: 0 5px;">
                        <table cellpadding="0" cellspacing="0" border="0" style="background-color: #171e88; border-radius: 50%; width: 32px; height: 32px;">
                            <tr>
                                <td style="text-align: center; vertical-align: middle; height: 32px;">
                                    <a href="#" style="text-decoration: none; display: block;">
                                        <img src="https://app.optiomax.com/images/socialMediaIcon/linkedin.png" width="16" height="16" alt="LinkedIn" style="display: block; margin: 0 auto; filter: brightness(0) invert(1);" />
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <p style="margin: 5px 0;">www.optiomax.com</p>
        </div>

    </div>
</body>
</html>