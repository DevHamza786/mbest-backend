<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Student;
use App\Models\Tutor;
use App\Models\User;
use App\Models\ParentModel;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $students = Student::all();
        $tutors = Tutor::all();

        if ($students->isEmpty() || $tutors->isEmpty()) {
            $this->command->warn('Please run UserSeeder first!');
            return;
        }

        $statuses = ['pending', 'paid', 'overdue'];
        $currencies = ['USD', 'EUR', 'GBP'];
        $paymentMethods = ['credit_card', 'bank_transfer', 'paypal', 'cash'];

        foreach ($students as $student) {
            $parentUser = $student->parents()->first(); // This returns a User, not ParentModel
            
            // Create 2-4 invoices per student
            $invoiceCount = rand(2, 4);
            
            for ($i = 0; $i < $invoiceCount; $i++) {
                $tutor = $tutors->random();
                $issueDate = Carbon::now()->subMonths(rand(0, 6))->subDays(rand(0, 30));
                $dueDate = $issueDate->copy()->addDays(30);
                $status = $statuses[array_rand($statuses)];
                $paidDate = $status === 'paid' ? $dueDate->copy()->subDays(rand(0, 15)) : null;

                $invoice = Invoice::create([
                    'invoice_number' => 'INV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                    'student_id' => $student->id,
                    'parent_id' => $parentUser ? $parentUser->id : null,
                    'tutor_id' => $tutor->id,
                    'amount' => 0, // Will be calculated from items
                    'currency' => $currencies[array_rand($currencies)],
                    'status' => $status,
                    'due_date' => $dueDate,
                    'paid_date' => $paidDate,
                    'issue_date' => $issueDate,
                    'period_start' => $issueDate->copy()->startOfMonth(),
                    'period_end' => $issueDate->copy()->endOfMonth(),
                    'description' => 'Monthly tutoring fees for ' . $issueDate->format('F Y'),
                    'tutor_address' => '123 Tutor Street, City, State 12345',
                    'notes' => $status === 'paid' ? 'Payment received on time' : 'Payment pending',
                    'payment_method' => $status === 'paid' ? $paymentMethods[array_rand($paymentMethods)] : null,
                    'transaction_id' => $status === 'paid' ? 'TXN-' . strtoupper(uniqid()) : null,
                ]);

                // Create invoice items
                $itemCount = rand(1, 3);
                $totalAmount = 0;

                for ($j = 0; $j < $itemCount; $j++) {
                    $itemAmount = rand(200, 1000);
                    $totalAmount += $itemAmount;

                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'description' => ['Monthly Tutoring Fee', 'Materials Fee', 'Registration Fee', 'Extra Session'][rand(0, 3)],
                        'amount' => $itemAmount,
                        'credits' => rand(1, 10),
                    ]);
                }

                $invoice->update(['amount' => $totalAmount]);
            }
        }

        $this->command->info('Invoices seeded successfully!');
        $this->command->info('Total: ' . Invoice::count() . ' invoices created');
        $this->command->info('Total: ' . InvoiceItem::count() . ' invoice items created');
    }
}

