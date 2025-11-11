<x-email-layout :app-details-url="$appDetailsUrl" title="Warranty Expired Alert">

        <!-- Main Content -->
        <div style="padding: 60px; text-align: left; background-color: #ffffff; border: 1px solid #e0e0e0; border-radius: 6px; margin: 10px 0;">
            <h2 style="margin: 0 0 20px; color: #000; font-size: 22px;">
                Dear {{ $details->responsible_personal_name }},
            </h2>

            <p style="font-size: 16px; color: #000;">
                This is to notify you that the <strong>warranty period</strong> for the following asset is approaching its expiry date:</strong>
            </p>

            <ul style="list-style: none; padding: 0; font-size: 16px; color: #000; margin-top: 25px;">
                <li><strong>Asset Group:</strong> {{ $details->asset_group_name }}</li>
                <li><strong>Serial Number:</strong> {{ $details->asset_item_serial_number }}</li>
                <!-- <li><strong>Warranty Period:</strong> {{ $details->warranty }}</li> -->
                <li><strong>Expiry Date:</strong> {{ $details->warranty_exparing_at }}</li>
                <li><strong>Time:</strong> {{ $details->created_at }}</li>
            </ul>

            <p style="margin-top: 30px;">
                Please review the asset details and consider renewal or extended warranty options if applicable.
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
                View Asset Details
            </a>
        </div>

</x-email-layout>