<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Str;

class InvoiceService
{
    /**
     * Generate a unique invoice number
     */
    public function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $year = date('Y');
        $month = date('m');
        
        // Get the last invoice number for this month
        $lastInvoice = Invoice::where('invoice_number', 'like', "{$prefix}-{$year}-{$month}-%")
            ->orderBy('invoice_number', 'desc')
            ->first();
        
        if ($lastInvoice) {
            $parts = explode('-', $lastInvoice->invoice_number);
            $lastNumber = (int) end($parts);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return sprintf('%s-%s-%s-%04d', $prefix, $year, $month, $newNumber);
    }

    /**
     * Create invoice with items
     */
    public function createInvoice(array $data): Invoice
    {
        // Generate invoice number if not provided
        if (!isset($data['invoice_number'])) {
            $data['invoice_number'] = $this->generateInvoiceNumber();
        }

        // Calculate total from items if not provided
        if (!isset($data['amount']) && isset($data['items'])) {
            $data['amount'] = collect($data['items'])->sum('amount');
        }

        $invoice = Invoice::create($data);

        // Create invoice items
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                    'credits' => $item['credits'] ?? null,
                ]);
            }
        }

        return $invoice->load('items');
    }

    /**
     * Mark invoice as paid
     */
    public function markAsPaid(Invoice $invoice, array $paymentData = []): Invoice
    {
        $invoice->update([
            'status' => 'paid',
            'paid_date' => now(),
            'payment_method' => $paymentData['payment_method'] ?? null,
            'transaction_id' => $paymentData['transaction_id'] ?? null,
        ]);

        return $invoice->fresh();
    }

    /**
     * Check and update overdue invoices
     */
    public function updateOverdueInvoices(): int
    {
        return Invoice::where('status', 'pending')
            ->where('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);
    }
}

