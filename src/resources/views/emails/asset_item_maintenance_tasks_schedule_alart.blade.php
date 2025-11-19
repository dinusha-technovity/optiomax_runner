<x-email-layout :app-details-url="$appDetailsUrl" title="Maintenance Task Schedule Alert">

        <!-- Main Content -->
        <div style="padding: 60px; text-align: left; background-color: #ffffff; border: 1px solid #e0e0e0; border-radius: 6px; margin: 10px 0;">
            <h2 style="margin: 0 0 20px; color: #000; font-size: 22px;">
                Dear {{ $details->responsible_personal_name }},
            </h2>

            <p style="font-size: 16px; color: #000;">
                A scheduled <strong>maintenance task</strong> has been created for the following asset:
            </p>

            <ul style="list-style: none; padding: 0; font-size: 16px; color: #000; margin-top: 25px;">
                <li><strong>Asset Group:</strong> {{ $details->asset_group_name }}</li>
                <li><strong>Serial Number:</strong> {{ $details->asset_item_serial_number }}</li>
                <li><strong>Task Type:</strong> {{ $details->maintenance_task_type_name ?? 'N/A' }}</li>
                <!-- <li><strong>Schedule:</strong> {{ $details->schedule ?? 'N/A' }}</li> -->
                <!-- <li><strong>Limit/Value:</strong> {{ $details->limit_or_value ?? 'N/A' }}</li> -->
                <!-- <li><strong>Expected Result:</strong> {{ $details->expected_results ?? 'N/A' }}</li> -->
                <!-- <li><strong>Assessment:</strong> {{ $details->assessment_description ?? 'N/A' }}</li> -->
                <!-- <li><strong>Comments:</strong> {{ $details->comments ?? 'N/A' }}</li> -->
                <li><strong>Date:</strong> {{ $details->created_at }}</li>
            </ul>

            <p style="margin-top: 30px;">
                Please review the task details and ensure the required preparations are made before the scheduled date.
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
                View Task in Dashboard
            </a>
        </div>

</x-email-layout>