<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_with_valid_credentials(): void
    {
        $gym = Gym::factory()->create();
        $user = User::factory()->member()->for($gym)->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()->assertJson([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => 'member',
                'gym_id' => $gym->id,
                'gym' => ['id' => $gym->id, 'name' => $gym->name],
            ],
        ]);
        $this->assertIsString($response->json('token'));
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
    }

    public function test_authenticated_user_can_fetch_their_own_profile(): void
    {
        $user = User::factory()->staff()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/me');

        $response->assertOk()->assertJson([
            'data' => [
                'id' => $user->id,
                'role' => 'staff',
            ],
        ]);
    }

    public function test_unauthenticated_request_cannot_fetch_profile(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_user_can_log_out_and_revoke_their_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout');

        $response->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
