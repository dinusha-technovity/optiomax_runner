<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Quotation Expiry Notice</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <div style="max-width: 600px; margin: 20px auto; overflow: hidden;">
        <!-- Logo -->
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
            <p style="margin: 30px 0 0; font-size: 30px; font-weight: bold; line-height: normal;">
                Quotation Expiry Reminder
            </p>
        </div>

        <!-- Main Content -->
        <div style="padding: 60px; text-align: left; background-color: #ffffff; border: 1px solid #e0e0e0; border-radius: 6px; margin: 10px 0;">
            <h2 style="margin: 0 0 20px; color: #000; font-size: 22px;">
                Dear Supplier,
            </h2>

            <p style="font-size: 16px; color: #000;">
                This is a gentle reminder that your quotation request is nearing its expiration.
            </p>

            <ul style="list-style: none; padding: 0; font-size: 16px; color: #000; margin-top: 25px;">
                <li><strong>Request Token:</strong> {{ $request->token }}</li>
                <li><strong>Expires At:</strong> {{ \Carbon\Carbon::parse($request->expires_at)->format('Y-m-d') }}</li>
                <li><strong>Email:</strong> {{ $request->email }}</li>
            </ul>

            <p style="margin-top: 30px;">
                Please ensure that you submit your quotation before the expiration date to avoid missing this opportunity.
            </p>

            {{-- <a href="{{ url('/supplier/quotation/' . $request->token) }}" style="
                display: inline-block;
                margin-top: 20px;
                padding: 10px 20px;
                background-color: #1677FF;
                color: #ffffff;
                border-radius: 5px;
                font-size: 14px;
                font-weight: 600;
                text-decoration: none;">
                Submit Quotation
            </a> --}}
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
                        Streamline your procurement and quotation submissions with our real-time platform.
                    </p>
                    <a href="https://www.optiomax.com" style="
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
                        Learn More
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