<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagApiTest extends TestCase
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
  public function an_authenticated_user_can_create_a_tag()
  {
    $response = $this->authenticated()->postJson('/api/tags', [
      'name' => 'mobile',
    ]);

    $response->assertStatus(201)
    ->assertJson([
      'name' => 'mobile',
    ]);

    $this->assertDatabaseHas('tags', [
      'name' => 'mobile',
    ]);
  }

  /** @test */
  public function unauthenticated_users_cannot_create_tags()
  {
    $response = $this->postJson('/api/tags', [
      'name' => 'desktop',
    ]);

    $response->assertStatus(401);
  }

  /** @test */
  public function creating_a_tag_requires_a_unique_name()
  {
    Tag::create(['name' => 'web']);

    $response = $this->authenticated()->postJson('/api/tags', [
      'name' => 'web',
    ]);

    $response->assertStatus(422)
    ->assertJsonValidationErrors(['name']);

    $this->assertDatabaseCount('tags', 1);
  }

  /** @test */
  public function creating_a_tag_requires_a_name()
  {
    $response = $this->authenticated()->postJson('/api/tags', [
    ]);

    $response->assertStatus(422)
      ->assertJsonValidationErrors(['name']);
  }

  /** @test */
  public function an_authenticated_user_can_view_all_tags()
  {
    Tag::create(['name' => 'mobile']);
    Tag::create(['name' => 'desktop']);

    $response = $this->authenticated()->getJson('/api/tags');

    $response->assertOk()
    ->assertJsonCount(2)
    ->assertJsonFragment(['name' => 'mobile'])
      ->assertJsonFragment(['name' => 'desktop']);
  }

  /** @test */
  public function an_authenticated_user_can_view_a_single_tag()
  {
    $tag = Tag::create(['name' => 'marketing']);

    $response = $this->authenticated()->getJson('/api/tags/' . $tag->id);

    $response->assertOk()
      ->assertJson([
        'name' => 'marketing',
      ]);
  }

  /** @test */
  public function viewing_a_non_existent_tag_returns_404()
  {
    $response = $this->authenticated()->getJson('/api/tags/999');

    $response->assertStatus(404);
  }

  /** @test */
  public function an_authenticated_user_can_update_a_tag()
  {
    $tag = Tag::create(['name' => 'old_tag']);

    $response = $this->authenticated()->putJson('/api/tags/' . $tag->id, [
      'name' => 'new_tag',
    ]);

    $response->assertOk()
      ->assertJson([
        'name' => 'new_tag',
      ]);

    $this->assertDatabaseHas('tags', [
      'id' => $tag->id,
      'name' => 'new_tag',
    ]);
    $this->assertDatabaseMissing('tags', [
      'name' => 'old_tag',
    ]);
  }

  /** @test */
  public function updating_a_tag_with_duplicate_name_fails()
  {
    Tag::create(['name' => 'existing_tag']);
    $tagToUpdate = Tag::create(['name' => 'another_tag']);

    $response = $this->authenticated()->putJson('/api/tags/' . $tagToUpdate->id, [
      'name' => 'existing_tag',
    ]);

    $response->assertStatus(422)
      ->assertJsonValidationErrors(['name']);

    $this->assertDatabaseHas('tags', [
      'id' => $tagToUpdate->id,
      'name' => 'another_tag',
    ]);
  }

  /** @test */
  public function updating_a_tag_with_its_own_name_is_allowed()
  {
    $tag = Tag::create(['name' => 'existing_name']);

    $response = $this->authenticated()->putJson('/api/tags/' . $tag->id, [
      'name' => 'existing_name',
    ]);

    $response->assertOk()
      ->assertJson([
        'name' => 'existing_name',
      ]);
  }

  /** @test */
  public function an_authenticated_user_can_delete_a_tag()
  {
    $tag = Tag::create(['name' => 'temporary_tag']);

    $response = $this->authenticated()->deleteJson('/api/tags/' . $tag->id);

    $response->assertStatus(204);

    $this->assertDatabaseMissing('tags', [
      'id' => $tag->id,
    ]);
  }

  /** @test */
  public function deleting_a_non_existent_tag_returns_404()
  {
    $response = $this->authenticated()->deleteJson('/api/tags/999');

    $response->assertStatus(404);
  }
}