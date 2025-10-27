<x-email-layout :app-details-url="$appDetailsUrl" title="Asset Item Critically Based Schedule Alert">

        <!-- Main Content -->
        <div style="padding: 60px; text-align: left; background-color: #ffffff; border: 1px solid #e0e0e0; border-radius: 6px; margin: 10px 0;">
            <h2 style="margin: 0 0 20px; color: #000; font-size: 22px;">
                Dear {{ $details->responsible_personal_name }},
            </h2>

            <p style="font-size: 16px; color: #000;">
                A <strong>criticality-based maintenance task</strong> has been scheduled for the following asset due to its operational importance:
            </p>

            <ul style="list-style: none; padding: 0; font-size: 16px; color: #000; margin-top: 25px;">
                <li><strong>Asset Group:</strong> {{ $details->asset_group_name }}</li>
                <li><strong>Serial Number:</strong> {{ $details->asset_item_serial_number }}</li>
                <li><strong>Time:</strong> {{ $details->created_at }}</li>
            </ul>

            <p style="margin-top: 30px;">
                Please prioritize and ensure timely action to maintain asset reliability.
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
                View Maintenance Task
            </a>
        </div>

</x-email-layout>
