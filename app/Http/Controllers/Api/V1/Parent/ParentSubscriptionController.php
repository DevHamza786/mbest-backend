<?php

namespace App\Http\Controllers\Api\V1\Parent;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ParentSubscriptionController extends Controller
{
    public function index()
    {
        $packages = Package::active()->orderBy('price')->get();

        return response()->json([
            'success' => true,
            'data' => $packages,
        ]);
    }

    public function show($id)
    {
        $package = Package::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $package,
        ]);
    }

    public function submitPayment(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'package_id' => 'required|exists:packages,id',
            'payment_slip' => 'required|file|mimes:jpeg,jpg,png,pdf|max:10240', // 10MB max
        ]);

        $package = Package::findOrFail($validated['package_id']);

        // Check if package is active
        if (!$package->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'This package is not available',
            ], 400);
        }

        // Store payment slip
        $paymentSlipPath = $request->file('payment_slip')->store('payments/slips', 'public');

        // Create payment record
        $payment = Payment::create([
            'parent_id' => $user->id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'payment_slip_path' => $paymentSlipPath,
            'status' => 'pending',
        ]);

        // Update user's package (but keep status as pending)
        $user->update([
            'package_id' => $package->id,
            'subscription_status' => 'pending',
        ]);

        // Notify admin
        $admins = \App\Models\User::where('role', 'admin')->get();
        $notificationService = new \App\Services\NotificationService();
        foreach ($admins as $admin) {
            $notificationService->createNotification(
                $admin->id,
                'payment',
                'New Payment Pending Approval',
                "Parent {$user->name} has submitted a payment for package '{$package->name}'. Amount: $" . number_format($package->price, 2),
                ['payment_id' => $payment->id, 'parent_id' => $user->id],
                'high'
            );
        }

        return response()->json([
            'success' => true,
            'data' => $payment->load('package'),
            'message' => 'Payment submitted successfully. Waiting for admin approval.',
        ], 201);
    }

    public function getMySubscription(Request $request)
    {
        $user = $request->user();
        
        $subscription = [
            'package' => $user->package ? $user->package : null,
            'status' => $user->subscription_status,
            'approved_at' => $user->subscription_approved_at,
            'current_student_count' => $user->current_student_count,
            'limits' => $user->package ? [
                'student_limit' => $user->package->student_limit,
                'allows_one_on_one' => $user->package->allows_one_on_one,
                'classes' => $user->package->classes->map(function ($class) {
                    return [
                        'id' => $class->id,
                        'name' => $class->name,
                        'code' => $class->code,
                    ];
                }),
            ] : null,
            'pending_payment' => Payment::where('parent_id', $user->id)
                ->where('status', 'pending')
                ->with('package')
                ->latest()
                ->first(),
        ];

        return response()->json([
            'success' => true,
            'data' => $subscription,
        ]);
    }
}
