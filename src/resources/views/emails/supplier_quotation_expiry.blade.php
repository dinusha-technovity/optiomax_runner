<x-email-layout :app-details-url="$appDetailsUrl" title="Supplier Quotation Expiry Notice">

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

</x-email-layout>