<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;

class AdminBillingController extends Controller
{
    /**
     * Dashboard KPIs for admin billing (aggregated from invoices + users).
     */
    public function summary(Request $request)
    {
        $totalRevenue = (float) Invoice::where('status', 'paid')->sum('amount');

        $pendingBase = Invoice::where('status', 'pending');
        $pendingAmount = (float) (clone $pendingBase)->sum('amount');
        $pendingCount = (clone $pendingBase)->count();

        $overdueBase = Invoice::where('status', 'overdue');
        $overdueAmount = (float) (clone $overdueBase)->sum('amount');
        $overdueCount = (clone $overdueBase)->count();

        $activeStudents = User::where('role', 'student')->count();

        $now = now();
        $thisMonthStart = $now->copy()->startOfMonth();
        $thisMonthEnd = $now->copy()->endOfMonth();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();

        // Month revenue: prefer paid_date; if missing, fall back to issue_date for paid invoices
        $paidThisMonth = (float) Invoice::where('status', 'paid')
            ->where(function ($q) use ($thisMonthStart, $thisMonthEnd) {
                $q->whereBetween('paid_date', [$thisMonthStart, $thisMonthEnd])
                    ->orWhere(function ($q2) use ($thisMonthStart, $thisMonthEnd) {
                        $q2->whereNull('paid_date')
                            ->whereBetween('issue_date', [$thisMonthStart, $thisMonthEnd]);
                    });
            })
            ->sum('amount');

        $paidLastMonth = (float) Invoice::where('status', 'paid')
            ->where(function ($q) use ($lastMonthStart, $lastMonthEnd) {
                $q->whereBetween('paid_date', [$lastMonthStart, $lastMonthEnd])
                    ->orWhere(function ($q2) use ($lastMonthStart, $lastMonthEnd) {
                        $q2->whereNull('paid_date')
                            ->whereBetween('issue_date', [$lastMonthStart, $lastMonthEnd]);
                    });
            })
            ->sum('amount');

        $revenueChangePercent = null;
        if ($paidLastMonth > 0) {
            $revenueChangePercent = round((($paidThisMonth - $paidLastMonth) / $paidLastMonth) * 100, 1);
        } elseif ($paidThisMonth > 0) {
            $revenueChangePercent = 100.0;
        } else {
            $revenueChangePercent = 0.0;
        }

        $newStudentsThisMonth = User::where('role', 'student')
            ->whereBetween('created_at', [$thisMonthStart, $thisMonthEnd])
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue' => $totalRevenue,
                'pending_amount' => $pendingAmount,
                'pending_count' => $pendingCount,
                'overdue_amount' => $overdueAmount,
                'overdue_count' => $overdueCount,
                'active_students' => $activeStudents,
                'revenue_change_percent_vs_last_month' => $revenueChangePercent,
                'paid_revenue_this_month' => $paidThisMonth,
                'paid_revenue_last_month' => $paidLastMonth,
                'new_students_this_month' => $newStudentsThisMonth,
            ],
        ]);
    }

    /**
     * Get billing overview: active packages, students per package, revenue per package
     */
    public function packageStats(Request $request)
    {
        $packages = Package::where('is_active', true)->get();

        $stats = $packages->map(function ($package) {
            $revenue = Payment::where('package_id', $package->id)
                ->where('status', 'approved')
                ->sum('amount');
            $activeStudents = User::where('package_id', $package->id)
                ->where('subscription_status', 'active')
                ->sum('current_student_count');

            return [
                'id' => $package->id,
                'name' => $package->name,
                'price' => $package->price,
                'active_students' => (int) $activeStudents,
                'revenue' => (float) $revenue,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'packages' => $stats,
                'total_revenue_from_packages' => $stats->sum('revenue'),
                'total_active_students' => $stats->sum('active_students'),
            ],
        ]);
    }

    public function index(Request $request)
    {
        $query = Invoice::with(['student.user', 'parent', 'tutor.user', 'items']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by student
        if ($request->has('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        // Filter by parent
        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        // Date range
        if ($request->has('date_from')) {
            $query->where('issue_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('issue_date', '<=', $request->date_to);
        }

        // Search: invoice number, description, student/parent/tutor names
        if ($request->filled('search')) {
            $term = '%' . trim((string) $request->get('search')) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('student.user', function ($uq) use ($term) {
                        $uq->where('name', 'like', $term);
                    })
                    ->orWhereHas('parent', function ($pq) use ($term) {
                        $pq->where('name', 'like', $term);
                    })
                    ->orWhereHas('tutor.user', function ($tq) use ($term) {
                        $tq->where('name', 'like', $term);
                    });
            });
        }

        $perPage = $request->get('per_page', 15);
        $sortBy = $request->get('sort_by', 'created_at');
        $order = $request->get('order', 'desc');
        $allowedSort = ['created_at', 'issue_date', 'due_date', 'amount'];
        if (!in_array($sortBy, $allowedSort)) {
            $sortBy = 'created_at';
        }
        $invoices = $query->orderBy($sortBy, $order === 'asc' ? 'asc' : 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string|max:50|unique:invoices',
            'student_id' => 'nullable|exists:students,id',
            'parent_id' => 'nullable|exists:users,id',
            'tutor_id' => 'nullable|exists:tutors,id',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'due_date' => 'required|date',
            'issue_date' => 'required|date',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.amount' => 'required|numeric|min:0',
            'items.*.credits' => 'nullable|integer',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => $validated['invoice_number'],
            'student_id' => $validated['student_id'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'tutor_id' => $validated['tutor_id'] ?? null,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'] ?? 'USD',
            'due_date' => $validated['due_date'],
            'issue_date' => $validated['issue_date'],
            'period_start' => $validated['period_start'] ?? null,
            'period_end' => $validated['period_end'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        // Create invoice items
        foreach ($validated['items'] as $item) {
            $invoice->items()->create([
                'description' => $item['description'],
                'amount' => $item['amount'],
                'credits' => $item['credits'] ?? null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $invoice->load(['items', 'student.user', 'parent', 'tutor.user']),
            'message' => 'Invoice created successfully',
        ], 201);
    }

    public function show($id)
    {
        $invoice = Invoice::with(['items', 'student.user', 'parent', 'tutor.user'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $invoice,
        ]);
    }

    public function update(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);

        $validated = $request->validate([
            'status' => 'sometimes|in:pending,paid,overdue,cancelled',
            'paid_date' => 'nullable|date',
            'payment_method' => 'nullable|string|max:50',
            'transaction_id' => 'nullable|string',
        ]);

        $invoice->update($validated);

        return response()->json([
            'success' => true,
            'data' => $invoice->load(['items', 'student.user', 'parent', 'tutor.user']),
            'message' => 'Invoice updated successfully',
        ]);
    }
}

