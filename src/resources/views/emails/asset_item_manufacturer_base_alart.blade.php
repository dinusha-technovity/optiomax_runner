<!-- resources/views/emails/asset_action.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manufacturer Maintenance Alert</title>
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
            <p style="margin: 30px 0 0; font-size: 36px; font-weight: bold; line-height: normal;">
                Manufacturer Maintenance Alert
            </p>
        </div>

        <!-- Main Content -->
        <div style="padding: 60px; text-align: left; background-color: #ffffff; border: 1px solid #e0e0e0; border-radius: 6px; margin: 10px 0;">
            <h2 style="margin: 0 0 20px; color: #000; font-size: 22px;">
                Dear {{ $details->responsible_personal_name }},
            </h2>

            <p style="font-size: 16px; color: #000;">
                This is an automated alert based on the manufacturer's recommended maintenance schedule for one of your assigned assets.
            </p>

            <ul style="list-style: none; padding: 0; font-size: 16px; color: #000; margin-top: 25px;">
                <li><strong>Asset Group:</strong> {{ $details->asset_group_name }}</li>
                <li><strong>Serial Number:</strong> {{ $details->asset_item_serial_number }}</li>
                <li><strong>Maintenance Parameter:</strong> {{ $details->maintain_schedule_parameters_name }}</li>
                <li><strong>Limit/Value:</strong> {{ $details->limit_or_value }}</li>
                <li><strong>Operator:</strong> {{ $details->operator }}</li>
                <li><strong>Reading:</strong> {{ $details->reading_parameters }}</li>
                <li><strong>time:</strong> {{ $details->created_at }}</li>
            </ul>

            <p style="margin-top: 30px;">
                Please ensure this maintenance task is performed in accordance with manufacturer guidelines to avoid performance issues or warranty conflicts.
            </p>

            <a href="#" style="
                display: inline-block;
                margin-top: 20px;
                padding: 10px 20px;
                background-color: #1677FF;
                color: #ffffff;
                border-radius: 5px;
                font-size: 14px;
                font-weight: 600;
                text-decoration: none;">
                View Maintenance Details
            </a>
        </div>

        <!-- App Info -->
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
                        Keep your assets in peak condition with Optiomax alerts driven by manufacturer and operational guidelines.
                    </p>
                    <a href="#" style="
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