<x-email-layout :app-details-url="$appDetailsUrl" title="Manufacturer Based Alert">

        <!-- Main Content -->
        <div style="padding: 60px; text-align: left; background-color: #ffffff; border: 1px solid #e0e0e0; border-radius: 6px; margin: 10px 0;">
            <h2 style="margin: 0 0 20px; color: #000; font-size: 22px;">
                Dear {{ $details->responsible_personal_name }},
            </h2>

            <p style="font-size: 16px; color: #000;">
                A <strong>manufacturer-recommended maintenance</strong> task for the following asset is scheduled:
            </p>

            <ul style="list-style: none; padding: 0; font-size: 16px; color: #000; margin-top: 25px;">
                <li><strong>Asset Group:</strong> {{ $details->asset_group_name }}</li>
                <li><strong>Serial Number:</strong> {{ $details->asset_item_serial_number }}</li>
                <li><strong>Maintenance Parameter:</strong> {{ $details->maintain_schedule_parameters_name }}</li>
                <li><strong>Limit/Value:</strong> {{ $details->limit_or_value }}</li>
                <!-- <li><strong>Operator:</strong> {{ $details->operator }}</li> -->
                <!-- <li><strong>Reading:</strong> {{ $details->reading_parameters }}</li> -->
                <li><strong>Date:</strong> {{ $details->created_at }}</li>
            </ul>

            <p style="margin-top: 30px;">
                Please review the maintenance details and confirm readiness.
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

</x-email-layout>