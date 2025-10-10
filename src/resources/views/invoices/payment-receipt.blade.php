{{-- filepath: /home/chamod-randeni/Documents/optiomax project/optiomax_runner/src/resources/views/invoices/payment-receipt.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>Invoice - {{ $invoice_number }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; }
        .company-info { float: left; width: 45%; }
        .invoice-info { float: right; width: 45%; text-align: right; }
        .bill-to { clear: both; margin: 30px 0; }
        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th, .items-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .items-table th { background-color: #f5f5f5; }
        .total-row { font-weight: bold; background-color: #f9f9f9; }
        .footer { margin-top: 30px; text-align: center; color: #666; }
        .clearfix::after { content: ""; display: table; clear: both; }
    </style>
</head>
<body>
    <div class="header">
        <h1>INVOICE</h1>
    </div>
    
    <div class="clearfix">
        <div class="company-info">
            <h3>{{ $company_info['name'] }}</h3>
            <p>
                {{ $company_info['address'] }}<br>
                {{ $company_info['city'] }}, {{ $company_info['zip'] }}<br>
                {{ $company_info['country'] }}<br>
                Email: {{ $company_info['email'] }}<br>
                Phone: {{ $company_info['phone'] }}
            </p>
        </div>
        
        <div class="invoice-info">
            <h3>Invoice Details</h3>
            <p>
                <strong>Invoice #:</strong> {{ $invoice_number }}<br>
                <strong>Date:</strong> {{ $invoice_date->format('F d, Y') }}<br>
                <strong>Billing Cycle:</strong> {{ ucfirst($pricing_details['billing_cycle']) }}
            </p>
        </div>
    </div>
    
    <div class="bill-to">
        <h3>Bill To:</h3>
        <p>
            <strong>{{ $owner_user->name }}</strong><br>
            {{ $tenant->tenant_name }}<br>
            {{ $tenant->address }}<br>
            {{ $owner_user->email }}
        </p>
    </div>
    
    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $pricing_details['billing_cycle'] === 'yearly' ? 'Annual' : 'Monthly' }} Subscription</td>
                <td>1</td>
                <td>${{ number_format($pricing_details['base_price'], 2) }}</td>
                <td>${{ number_format($pricing_details['base_price'], 2) }}</td>
            </tr>
            
            @if(!empty($pricing_details['addon_details']))
                @foreach($pricing_details['addon_details'] as $addon)
                <tr>
                    <td>{{ $addon['name'] }}</td>
                    <td>{{ $addon['quantity'] }}</td>
                    <td>${{ number_format($addon['unit_price'], 2) }}</td>
                    <td>${{ number_format($addon['total_price'], 2) }}</td>
                </tr>
                @endforeach
            @endif
            
            @if($pricing_details['setup_fee'] > 0)
            <tr>
                <td>Setup Fee</td>
                <td>1</td>
                <td>${{ number_format($pricing_details['setup_fee'], 2) }}</td>
                <td>${{ number_format($pricing_details['setup_fee'], 2) }}</td>
            </tr>
            @endif
            
            <tr class="total-row">
                <td colspan="3"><strong>Total</strong></td>
                <td><strong>${{ number_format($pricing_details['total_amount'], 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
    
    <div class="footer">
        <p>Thank you for your business!</p>
        <p><small>This invoice was generated automatically on {{ now()->format('F d, Y \a\t g:i A') }}</small></p>
    </div>
</body>
</html>