<?php

namespace App\Http\Controllers\Api\V1\Parent;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class ParentBillingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $parent = $user->parentModel;

        if (!$parent) {
            return response()->json([
                'success' => false,
                'message' => 'Parent profile not found',
            ], 404);
        }

        $query = Invoice::where('parent_id', $user->id)
            ->with(['student.user', 'items']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by child
        if ($request->has('child_id')) {
            $query->where('student_id', $request->child_id);
        }

        $perPage = $request->get('per_page', 15);
        $invoices = $query->orderBy('issue_date', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $parent = $user->parentModel;

        if (!$parent) {
            return response()->json([
                'success' => false,
                'message' => 'Parent profile not found',
            ], 404);
        }

        $invoice = Invoice::where('parent_id', $user->id)
            ->with(['student.user', 'items', 'tutor.user'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $invoice,
        ]);
    }

    public function downloadPdf(Request $request, $id)
    {
        $user = $request->user();
        $parent = $user->parentModel;

        if (!$parent) {
            return response()->json([
                'success' => false,
                'message' => 'Parent profile not found',
            ], 404);
        }

        $invoice = Invoice::where('parent_id', $user->id)
            ->with(['student.user', 'items', 'tutor.user', 'parent'])
            ->findOrFail($id);

        // Generate PDF
        try {
            $pdf = Pdf::loadView('invoices.pdf', ['invoice' => $invoice]);
            return $pdf->download('invoice-' . $invoice->invoice_number . '.pdf');
        } catch (\Exception $e) {
            \Log::error('PDF generation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF. Please contact support.',
            ], 500)->header('Content-Type', 'application/json');
        }
    }
}

