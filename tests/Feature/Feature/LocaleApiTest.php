<?php

namespace Tests\Feature;

use App\Models\Locale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleApiTest extends TestCase
{
  use RefreshDatabase;

  protected $user;
  protected $token;

  /**
   * Set up the test environment.
   * Create a user and generate a Sanctum token for authentication.
   */
  protected function setUp(): void
  {
    parent::setUp();

    $this->user = User::factory()->create();

    $this->token = $this->user->createToken('test_token')->plainTextToken;
  }

  /**
   * Helper method to include the Authorization header with the token.
   */
  protected function authenticated()
  {
    return $this->withHeaders([
      'Authorization' => 'Bearer ' . $this->token,
    ]);
  }

  /** @test */
  public function an_authenticated_user_can_create_a_locale()
  {
    $response = $this->authenticated()->postJson('/api/locales', [
      'code' => 'en',
      'name' => 'English',
    ]);

    $response->assertStatus(201)
    ->assertJson([
      'code' => 'en',
      'name' => 'English',
    ]);

    $this->assertDatabaseHas('locales', [
      'code' => 'en',
      'name' => 'English',
    ]);
  }

  /** @test */
  public function unauthenticated_users_cannot_create_locales()
  {
    $response = $this->postJson('/api/locales', [
      'code' => 'de',
      'name' => 'German',
    ]);

    $response->assertStatus(401);
  }

  /** @test */
  public function creating_a_locale_requires_a_unique_code()
  {
    Locale::create(['code' => 'es', 'name' => 'Spanish']);

    $response = $this->authenticated()->postJson('/api/locales', [
      'code' => 'es',
      'name' => 'Español',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['code']);

    $this->assertDatabaseCount('locales', 1);
  }

  /** @test */
  public function creating_a_locale_requires_code_and_name()
  {
    $response = $this->authenticated()->postJson('/api/locales', [
      'code' => 'fr',
    ]);

    $response->assertStatus(422)
      ->assertJsonValidationErrors(['name']);

    $response = $this->authenticated()->postJson('/api/locales', [
      'name' => 'Italian',
    ]);

    $response->assertStatus(422)
      ->assertJsonValidationErrors(['code']);
  }

  /** @test */
  public function an_authenticated_user_can_view_all_locales()
  {
    Locale::factory()->count(3)->create(); // Use factory if you have one
    // Or manually create:
    Locale::create(['code' => 'en', 'name' => 'English']);
    Locale::create(['code' => 'fr', 'name' => 'French']);

    $response = $this->authenticated()->getJson('/api/locales');

    $response->assertOk() // 200 OK
    ->assertJsonCount(2) // Based on the two created above
    ->assertJsonFragment(['code' => 'en'])
      ->assertJsonFragment(['code' => 'fr']);
  }

  /** @test */
  public function an_authenticated_user_can_view_a_single_locale()
  {
    $locale = Locale::create(['code' => 'de', 'name' => 'German']);

    $response = $this->authenticated()->getJson('/api/locales/' . $locale->id);

    $response->assertOk()
      ->assertJson([
        'code' => 'de',
        'name' => 'German',
      ]);
  }

  /** @test */
  public function viewing_a_non_existent_locale_returns_404()
  {
    $response = $this->authenticated()->getJson('/api/locales/999'); // Assuming 999 doesn't exist

    $response->assertStatus(404);
  }

  /** @test */
  public function an_authenticated_user_can_update_a_locale()
  {
    $locale = Locale::create(['code' => 'jp', 'name' => 'Japanese']);

    $response = $this->authenticated()->putJson('/api/locales/' . $locale->id, [
      'code' => 'ja',
      'name' => 'Nihongo',
    ]);

    $response->assertOk()
      ->assertJson([
        'code' => 'ja',
        'name' => 'Nihongo',
      ]);

    $this->assertDatabaseHas('locales', [
      'id' => $locale->id,
      'code' => 'ja',
      'name' => 'Nihongo',
    ]);
  }

  /** @test */
  public function updating_a_locale_with_duplicate_code_fails()
  {
    Locale::create(['code' => 'it', 'name' => 'Italian']);
    $localeToUpdate = Locale::create(['code' => 'pt', 'name' => 'Portuguese']);

    $response = $this->authenticated()->putJson('/api/locales/' . $localeToUpdate->id, [
      'code' => 'it',
    ]);

    $response->assertStatus(422)
      ->assertJsonValidationErrors(['code']);

    $this->assertDatabaseHas('locales', [
      'id' => $localeToUpdate->id,
      'code' => 'pt',
      'name' => 'Portuguese',
    ]);
  }

  /** @test */
  public function updating_a_locale_with_its_own_code_is_allowed()
  {
    $locale = Locale::create(['code' => 'dk', 'name' => 'Danish']);

    $response = $this->authenticated()->putJson('/api/locales/' . $locale->id, [
      'code' => 'dk',
      'name' => 'Dansk',
    ]);

    $response->assertOk()
      ->assertJson([
        'code' => 'dk',
        'name' => 'Dansk',
      ]);
  }

  /** @test */
  public function an_authenticated_user_can_delete_a_locale()
  {
    $locale = Locale::create(['code' => 'se', 'name' => 'Swedish']);

    $response = $this->authenticated()->deleteJson('/api/locales/' . $locale->id);

    $response->assertStatus(204);

    $this->assertDatabaseMissing('locales', [
      'id' => $locale->id,
    ]);
  }

  /** @test */
  public function deleting_a_non_existent_locale_returns_404()
  {
    $response = $this->authenticated()->deleteJson('/api/locales/999');

    $response->assertStatus(404);
  }
}