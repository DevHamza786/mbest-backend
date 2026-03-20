<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Models\Package;
use App\Models\ClassModel;
use App\Services\StudentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::with(['parent', 'package', 'approver']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter pending only
        if ($request->boolean('pending_only')) {
            $query->pending();
        }

        $perPage = $request->get('per_page', 15);
        $payments = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    public function show($id)
    {
        $payment = Payment::with(['parent', 'package', 'approver'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $payment,
        ]);
    }

    public function approve(Request $request, $id)
    {
        $payment = Payment::with(['parent', 'package'])->findOrFail($id);

        if ($payment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Payment is not pending approval',
            ], 400);
        }

        DB::transaction(function () use ($payment, $request) {
            // Update payment status
            $payment->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'admin_notes' => $request->input('admin_notes'),
            ]);

            // Activate parent subscription
            $parent = $payment->parent;
            $parent->update([
                'package_id' => $payment->package_id,
                'subscription_status' => 'active',
                'subscription_approved_at' => now(),
            ]);

            // Upgrade flow: when a parent upgrades package, auto-enroll existing students
            // into any newly included classes (so the upgrade applies immediately).
            $studentService = new StudentService();

            $package = $payment->package->load('classes');
            $packageClassIds = $package->classes->pluck('id')->toArray();

            $students = $parent->parentModel ? $parent->parentModel->students()->get() : collect();
            $classesById = ClassModel::whereIn('id', $packageClassIds)->get()->keyBy('id');

            foreach ($students as $student) {
                $existingClassIds = $student->classes()->pluck('classes.id')->toArray();

                foreach ($packageClassIds as $classId) {
                    if (in_array($classId, $existingClassIds, true)) continue;

                    $class = $classesById->get($classId);
                    if (!$class) continue;

                    try {
                        $studentService->enrollStudentInClass($student, $class);
                    } catch (\Exception $e) {
                        // Enrollment can fail if the student is already enrolled or validation rejects;
                        // don't block approval.
                        \Log::warning('Auto-enroll during package upgrade failed', [
                            'payment_id' => $payment->id,
                            'parent_id' => $parent->id,
                            'student_id' => $student->id,
                            'class_id' => $classId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Create notification for parent
            $notificationService = new \App\Services\NotificationService();
            $notificationService->createNotification(
                $parent->id,
                'payment',
                'Payment Approved',
                "Your payment for package '{$payment->package->name}' has been approved. Your subscription is now active.",
                ['payment_id' => $payment->id, 'package_id' => $payment->package_id],
                'high'
            );
        });

        return response()->json([
            'success' => true,
            'data' => $payment->fresh(['parent', 'package', 'approver']),
            'message' => 'Payment approved and subscription activated',
        ]);
    }

    public function reject(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);

        if ($payment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Payment is not pending approval',
            ], 400);
        }

        $validated = $request->validate([
            'admin_notes' => 'required|string',
        ]);

        $payment->update([
            'status' => 'rejected',
            'admin_notes' => $validated['admin_notes'],
        ]);

        // Notify parent
        $notificationService = new \App\Services\NotificationService();
        $notificationService->createNotification(
            $payment->parent_id,
            'payment',
            'Payment Rejected',
            "Your payment has been rejected. Reason: {$validated['admin_notes']}",
            ['payment_id' => $payment->id],
            'high'
        );

        return response()->json([
            'success' => true,
            'data' => $payment->fresh(['parent', 'package']),
            'message' => 'Payment rejected',
        ]);
    }
}
