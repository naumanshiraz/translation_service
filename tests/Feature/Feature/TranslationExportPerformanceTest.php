<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    User::create([
      'name' => 'Test User',
      'email' => 'test@example.com',
      'password' => Hash::make('password'),
    ]);
  }

  /** @test */
  public function a_user_can_login_and_get_a_token()
  {
    $response = $this->postJson('/api/login', [
      'email' => 'test@example.com',
      'password' => 'password',
    ]);

    $response->assertOk()
      ->assertJsonStructure(['token', 'message'])
      ->assertJson(['message' => 'Logged in successfully!']);

    $this->assertNotNull($response->json('token'));
  }

  /** @test */
  public function login_fails_with_invalid_credentials()
  {
    $response = $this->postJson('/api/login', [
      'email' => 'test@example.com',
      'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
      ->assertJsonValidationErrors('email');
  }

  /** @test */
  public function an_authenticated_user_can_access_protected_routes()
  {
    $user = User::first();
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withHeaders([
      'Authorization' => 'Bearer ' . $token,
    ])->getJson('/api/user');

    $response->assertOk()
      ->assertJson(['email' => 'test@example.com']);
  }

  /** @test */
  public function an_unauthenticated_user_cannot_access_protected_routes()
  {
    $response = $this->getJson('/api/user');
    $response->assertStatus(401);
  }

  /** @test */
  public function an_authenticated_user_can_logout()
  {
    $user = User::first();
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withHeaders([
      'Authorization' => 'Bearer ' . $token,
    ])->postJson('/api/logout');

    $response->assertOk()
      ->assertJson(['message' => 'Logged out successfully!']);

    $response = $this->withHeaders([
      'Authorization' => 'Bearer ' . $token,
    ])->getJson('/api/user');

    $response->assertStatus(401);
  }
}