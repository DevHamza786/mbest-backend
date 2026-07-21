<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentStudentLimitTest extends TestCase
{
    use RefreshDatabase;

    private function makePackage(int $studentLimit): Package
    {
        return Package::create([
            'name' => 'Starter',
            'price' => 99.00,
            'description' => 'Starter package',
            'student_limit' => $studentLimit,
            'allows_one_on_one' => false,
            'bank_details' => 'Bank details here',
            'is_active' => true,
        ]);
    }

    private function validStudentPayload(): array
    {
        return [
            'name' => 'Jamie Smith',
            'email' => 'jamie.smith@gmail.com',
            'password' => 'Passw0rd!',
            'grade' => 'Year 4',
            'school' => 'Riverside Primary',
        ];
    }

    public function test_adding_student_within_limit_succeeds(): void
    {
        $package = $this->makePackage(studentLimit: 2);

        $parent = User::factory()->create([
            'role' => 'parent',
            'package_id' => $package->id,
            'subscription_status' => 'active',
            'current_student_count' => 0,
        ]);
        $token = $parent->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/parent/children', $this->validStudentPayload());

        $response->assertStatus(201)->assertJson(['success' => true]);
    }

    public function test_adding_student_over_limit_returns_limit_reached_flag(): void
    {
        $package = $this->makePackage(studentLimit: 1);

        $parent = User::factory()->create([
            'role' => 'parent',
            'package_id' => $package->id,
            'subscription_status' => 'active',
            'current_student_count' => 1,
        ]);
        $token = $parent->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/parent/children', $this->validStudentPayload());

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'limit_reached' => true,
            ]);

        $this->assertDatabaseMissing('users', ['email' => 'jamie.smith@gmail.com']);
    }
}
