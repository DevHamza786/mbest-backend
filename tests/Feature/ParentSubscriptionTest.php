<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ParentSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

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

    public function test_first_time_subscription_payment_sets_user_pending(): void
    {
        $parent = User::factory()->create([
            'role' => 'parent',
            'package_id' => null,
            'subscription_status' => 'pending',
        ]);
        $token = $parent->createToken('test-token')->plainTextToken;
        $package = $this->makePackage('Starter', 99.00);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/parent/subscription/payment', [
                'package_id' => $package->id,
                'payment_slip' => UploadedFile::fake()->create('slip.pdf', 10, 'application/pdf'),
            ]);

        $response->assertStatus(201)->assertJson(['success' => true]);

        $parent->refresh();
        $this->assertEquals($package->id, $parent->package_id);
        $this->assertEquals('pending', $parent->subscription_status);
    }

    public function test_active_parent_submitting_payment_does_not_change_live_subscription(): void
    {
        $currentPackage = $this->makePackage('Starter', 99.00);
        $newPackage = $this->makePackage('Pro', 199.00);

        $parent = User::factory()->create([
            'role' => 'parent',
            'package_id' => $currentPackage->id,
            'subscription_status' => 'active',
        ]);
        $token = $parent->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/parent/subscription/payment', [
                'package_id' => $newPackage->id,
                'payment_slip' => UploadedFile::fake()->create('slip.pdf', 10, 'application/pdf'),
            ]);

        $response->assertStatus(201)->assertJson(['success' => true]);

        $parent->refresh();
        $this->assertEquals($currentPackage->id, $parent->package_id);
        $this->assertEquals('active', $parent->subscription_status);

        $this->assertDatabaseHas('payments', [
            'parent_id' => $parent->id,
            'package_id' => $newPackage->id,
            'status' => 'pending',
        ]);
    }
}
