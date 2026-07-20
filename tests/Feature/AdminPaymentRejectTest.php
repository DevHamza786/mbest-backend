<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaymentRejectTest extends TestCase
{
    use RefreshDatabase;

    private function makePackage(string $name, float $price): Package
    {
        return Package::create([
            'name' => $name,
            'price' => $price,
            'description' => "{$name} package",
            'student_limit' => 2,
            'allows_one_on_one' => false,
            'bank_details' => 'Bank details here',
            'is_active' => true,
        ]);
    }

    public function test_rejecting_first_time_payment_resets_user_to_no_package(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $package = $this->makePackage('Starter', 99.00);

        $parent = User::factory()->create([
            'role' => 'parent',
            'package_id' => $package->id,
            'subscription_status' => 'pending',
        ]);

        $payment = Payment::create([
            'parent_id' => $parent->id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'payment_slip_path' => 'payments/slips/fake.pdf',
            'status' => 'pending',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/admin/payments/{$payment->id}/reject", [
                'admin_notes' => 'Payment slip unreadable',
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $parent->refresh();
        $this->assertNull($parent->package_id);
        $this->assertNull($parent->subscription_status);
    }

    public function test_rejecting_upgrade_payment_does_not_touch_active_parents_package(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $currentPackage = $this->makePackage('Starter', 99.00);
        $requestedPackage = $this->makePackage('Pro', 199.00);

        // Reflects the Task 1 fix: an active parent stays 'active' even with a pending
        // upgrade Payment on a different package.
        $parent = User::factory()->create([
            'role' => 'parent',
            'package_id' => $currentPackage->id,
            'subscription_status' => 'active',
        ]);

        $payment = Payment::create([
            'parent_id' => $parent->id,
            'package_id' => $requestedPackage->id,
            'amount' => $requestedPackage->price,
            'payment_slip_path' => 'payments/slips/fake.pdf',
            'status' => 'pending',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/admin/payments/{$payment->id}/reject", [
                'admin_notes' => 'Not eligible for this package',
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $parent->refresh();
        $this->assertEquals($currentPackage->id, $parent->package_id);
        $this->assertEquals('active', $parent->subscription_status);
    }

    public function test_new_user_defaults_to_pending_subscription_status(): void
    {
        // create() returns the in-memory model as constructed; it never re-fetches the
        // row, so a DB-level default (applied by the migration, not the factory) only
        // shows up after an explicit refresh from the database.
        $tutor = User::factory()->create(['role' => 'tutor']);
        $tutor->refresh();

        $this->assertEquals('pending', $tutor->subscription_status);
    }
}
