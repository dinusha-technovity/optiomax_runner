<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class InvoiceService
{
    public function generateInvoicePDF(array $invoiceData)
    {
        try {
            Log::info("Generating invoice PDF for tenant {$invoiceData['tenant']->id}");
            
            // Ensure invoice_date is a Carbon instance
            if (is_string($invoiceData['invoice_date'])) {
                $invoiceData['invoice_date'] = Carbon::parse($invoiceData['invoice_date']);
            }
            
            // Check if DomPDF is available
            if (class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
                return $this->generatePDFWithDomPDF($invoiceData);
            } else {
                Log::warning("DomPDF package not found, using fallback text invoice");
                return $this->generateFallbackInvoice($invoiceData);
            }
            
        } catch (\Exception $e) {
            Log::error("Failed to generate invoice PDF: " . $e->getMessage());
            
            // Return a simple text-based invoice as fallback
            return $this->generateFallbackInvoice($invoiceData);
        }
    }
    
    private function generatePDFWithDomPDF(array $invoiceData): string
    {
        // Prepare data for PDF generation
        $data = [
            'invoice_number' => $invoiceData['invoice_number'],
            'invoice_date' => $invoiceData['invoice_date'],
            'tenant' => $invoiceData['tenant'],
            'owner_user' => $invoiceData['owner_user'],
            'pricing_details' => $invoiceData['pricing_details'],
            'setup_fee_transaction' => $invoiceData['setup_fee_transaction'] ?? null,
            'company_info' => [
                'name' => config('app.name', 'Optiomax'),
                'address' => '123 Business Street',
                'city' => 'Business City',
                'zip' => '12345',
                'country' => 'United States',
                'email' => 'billing@optiomax.com',
                'phone' => '+1 (555) 123-4567'
            ]
        ];
        
        // Generate PDF using DomPDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.payment-receipt', $data);
        
        return $pdf->output();
    }
    
    private function generateFallbackInvoice(array $invoiceData): string
    {
        $tenant = $invoiceData['tenant'];
        $owner = $invoiceData['owner_user'];
        $pricing = $invoiceData['pricing_details'];
        
        $invoice = "INVOICE\n";
        $invoice .= "=====================================\n\n";
        $invoice .= "Invoice Number: {$invoiceData['invoice_number']}\n";
        $invoice .= "Date: " . (is_string($invoiceData['invoice_date']) ? $invoiceData['invoice_date'] : $invoiceData['invoice_date']->format('Y-m-d')) . "\n\n";
        
        $invoice .= "Bill To:\n";
        $invoice .= "{$owner->name}\n";
        $invoice .= "{$tenant->tenant_name}\n";
        $invoice .= "{$tenant->address}\n";
        $invoice .= "{$owner->email}\n\n";
        
        $invoice .= "From:\n";
        $invoice .= config('app.name', 'Optiomax') . "\n";
        $invoice .= "billing@optiomax.com\n\n";
        
        $invoice .= "Items:\n";
        $invoice .= "-------------------------------------\n";
        $invoice .= "Base Plan: $" . number_format($pricing['base_price'], 2) . "\n";
        
        if (!empty($pricing['addon_details'])) {
            foreach ($pricing['addon_details'] as $addon) {
                $invoice .= "{$addon['name']} (x{$addon['quantity']}): $" . number_format($addon['total_price'], 2) . "\n";
            }
        }
        
        if ($pricing['setup_fee'] > 0) {
            $invoice .= "Setup Fee: $" . number_format($pricing['setup_fee'], 2) . "\n";
        }
        
        $invoice .= "-------------------------------------\n";
        $invoice .= "Total: $" . number_format($pricing['total_amount'], 2) . "\n\n";
        
        $invoice .= "Thank you for your business!\n";
        
        return $invoice;
    }
}
