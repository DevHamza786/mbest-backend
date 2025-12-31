<?php

namespace App\Http\Controllers\Api\V1\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Tutor;
use App\Models\TutoringSession;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TutorHoursController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $query = TutoringSession::where('teacher_id', $tutor->id)
            ->where('status', 'completed')
            ->with(['students.user']);

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        // Filter by paid status
        if ($request->has('paid')) {
            if ($request->paid == 'true') {
                $query->where('ready_for_invoicing', true)
                      ->whereHas('invoice', function ($q) {
                          $q->where('status', 'paid');
                      });
            } else {
                $query->where(function ($q) {
                    $q->where('ready_for_invoicing', false)
                      ->orWhereDoesntHave('invoice', function ($q) {
                          $q->where('status', 'paid');
                      });
                });
            }
        }

        $perPage = $request->get('per_page', 15);
        $sessions = $query->orderBy('date', 'desc')
                          ->orderBy('start_time', 'desc')
                          ->paginate($perPage);

        // Calculate hours and earnings for each session
        $sessions->getCollection()->transform(function ($session) use ($tutor) {
            // Format date as Y-m-d string to avoid double time specification
            $dateStr = $session->date instanceof \Carbon\Carbon 
                ? $session->date->format('Y-m-d') 
                : $session->date;
            $start = Carbon::parse($dateStr . ' ' . $session->start_time);
            $end = Carbon::parse($dateStr . ' ' . $session->end_time);
            $hours = $start->diffInMinutes($end) / 60;

            $session->hours = round($hours, 2);
            $session->earnings = round($hours * $tutor->hourly_rate, 2);
            $session->paid = $session->ready_for_invoicing && 
                           Invoice::where('tutor_id', $tutor->id)
                                  ->where('status', 'paid')
                                  ->where(function ($q) use ($session) {
                                      $q->where('period_start', '<=', $session->date)
                                        ->where('period_end', '>=', $session->date);
                                  })
                                  ->exists();

            return $session;
        });

        // Calculate totals
        $totalHours = TutoringSession::where('teacher_id', $tutor->id)
            ->where('status', 'completed')
            ->get()
            ->sum(function ($session) {
                // Format date as Y-m-d string to avoid double time specification
                $dateStr = $session->date instanceof \Carbon\Carbon 
                    ? $session->date->format('Y-m-d') 
                    : $session->date;
                $start = Carbon::parse($dateStr . ' ' . $session->start_time);
                $end = Carbon::parse($dateStr . ' ' . $session->end_time);
                return $start->diffInMinutes($end) / 60;
            });

        $totalEarnings = round($totalHours * $tutor->hourly_rate, 2);

        $paidHours = TutoringSession::where('teacher_id', $tutor->id)
            ->where('status', 'completed')
            ->where('ready_for_invoicing', true)
            ->get()
            ->filter(function ($session) use ($tutor) {
                return Invoice::where('tutor_id', $tutor->id)
                              ->where('status', 'paid')
                              ->where(function ($q) use ($session) {
                                  $q->where('period_start', '<=', $session->date)
                                    ->where('period_end', '>=', $session->date);
                              })
                              ->exists();
            })
            ->sum(function ($session) {
                // Format date as Y-m-d string to avoid double time specification
                $dateStr = $session->date instanceof \Carbon\Carbon 
                    ? $session->date->format('Y-m-d') 
                    : $session->date;
                $start = Carbon::parse($dateStr . ' ' . $session->start_time);
                $end = Carbon::parse($dateStr . ' ' . $session->end_time);
                return $start->diffInMinutes($end) / 60;
            });

        $paidEarnings = round($paidHours * $tutor->hourly_rate, 2);

        return response()->json([
            'success' => true,
            'data' => $sessions,
            'summary' => [
                'total_hours' => round($totalHours, 2),
                'total_earnings' => $totalEarnings,
                'paid_hours' => round($paidHours, 2),
                'paid_earnings' => $paidEarnings,
                'pending_hours' => round($totalHours - $paidHours, 2),
                'pending_earnings' => round($totalEarnings - $paidEarnings, 2),
            ],
        ]);
    }

    public function invoices(Request $request)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $query = Invoice::where('tutor_id', $tutor->id)
            ->with(['student.user', 'parent', 'items', 'session.students.user']);

        // Filter by session_id
        if ($request->has('session_id')) {
            $query->where('session_id', $request->session_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('issue_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('issue_date', '<=', $request->date_to);
        }

        $perPage = $request->get('per_page', 15);
        $invoices = $query->orderBy('issue_date', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    public function createInvoice(Request $request)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $validated = $request->validate([
            'session_id' => 'required|exists:tutoring_sessions,id',
            'invoice_number' => 'required|string|unique:invoices,invoice_number',
            'issue_date' => 'required|date',
            'period_start' => 'required|date',
            'period_end' => 'required|date',
            'tutor_address' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.rate' => 'nullable|numeric|min:0',
            'items.*.amount' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'due_date' => 'nullable|date',
        ]);

        // Verify the session belongs to this tutor
        $session = TutoringSession::where('id', $validated['session_id'])
            ->where('teacher_id', $tutor->id)
            ->firstOrFail();

        // Calculate due date (30 days from issue date if not provided)
        $dueDate = $validated['due_date'] ?? Carbon::parse($validated['issue_date'])->addDays(30)->format('Y-m-d');

        // Create invoice
        $invoice = Invoice::create([
            'invoice_number' => $validated['invoice_number'],
            'tutor_id' => $tutor->id,
            'session_id' => $validated['session_id'],
            'student_id' => $session->students()->first()?->id, // Get first student if available
            'amount' => $validated['total_amount'],
            'currency' => 'USD',
            'status' => 'pending',
            'issue_date' => $validated['issue_date'],
            'due_date' => $dueDate,
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'tutor_address' => $validated['tutor_address'],
            'notes' => $validated['notes'] ?? null,
            'description' => 'Tutor invoice for session',
        ]);

        // Create invoice items
        foreach ($validated['items'] as $item) {
            // Include quantity and rate in description if provided for reference
            $description = $item['description'];
            if (isset($item['quantity']) && isset($item['rate'])) {
                $description .= sprintf(' (Qty: %s @ $%s/hr)', $item['quantity'], number_format($item['rate'], 2));
            }
            
            $invoice->items()->create([
                'description' => $description,
                'amount' => $item['amount'],
                'credits' => $item['quantity'] ?? 0,
            ]);
        }

        // Mark session as ready for invoicing
        $session->update(['ready_for_invoicing' => true]);

        return response()->json([
            'success' => true,
            'data' => $invoice->load(['items', 'tutor', 'student', 'session']),
            'message' => 'Invoice created successfully',
        ], 201);
    }
}

