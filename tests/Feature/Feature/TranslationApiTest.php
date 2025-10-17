<?php

namespace Tests\Feature;

use App\Models\Locale;
use App\Models\Tag;
use App\Models\Translation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TranslationApiTest extends TestCase
{
  use RefreshDatabase;

  protected $user;
  protected $token;
  protected $enLocale;
  protected $frLocale;
  protected $mobileTag;
  protected $webTag;

  /**
   * Set up the test environment.
   * Create a user, generate a Sanctum token, and create some base locales/tags.
   */
  protected function setUp(): void
  {
    parent::setUp();

    $this->user = User::factory()->create();

    $this->token = $this->user->createToken('test_token')->plainTextToken;

    $this->enLocale = Locale::create(['code' => 'en', 'name' => 'English']);
    $this->frLocale = Locale::create(['code' => 'fr', 'name' => 'French']);
    $this->mobileTag = Tag::create(['name' => 'mobile']);
    $this->webTag = Tag::create(['name' => 'web']);
    Tag::create(['name' => 'desktop']);
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
  public function an_authenticated_user_can_create_a_translation_without_tags()
  {
    $response = $this->authenticated()->postJson('/api/translations', [
      'locale_id' => $this->enLocale->id,
      'key' => 'welcome.message',
      'value' => 'Welcome to our app!',
    ]);

    $response->assertStatus(201)
    ->assertJson([
      'locale_id' => $this->enLocale->id,
      'key' => 'welcome.message',
      'value' => 'Welcome to our app!',
    ])
      ->assertJsonCount(0, 'tags');

    $this->assertDatabaseHas('translations', [
      'locale_id' => $this->enLocale->id,
      'key' => 'welcome.message',
    ]);

    $this->assertFalse(Cache::has("translations.export.{$this->enLocale->code}"));
  }

  /** @test */
  public function an_authenticated_user_can_create_a_translation_with_tags()
  {
    $response = $this->authenticated()->postJson('/api/translations', [
      'locale_id' => $this->enLocale->id,
      'key' => 'greeting.title',
      'value' => 'Hello there!',
      'tags' => [$this->mobileTag->id, $this->webTag->id],
    ]);

    $response->assertStatus(201)
      ->assertJson([
        'locale_id' => $this->enLocale->id,
        'key' => 'greeting.title',
      ])
      ->assertJsonFragment(['name' => 'mobile'])
      ->assertJsonFragment(['name' => 'web']);

    $translation = Translation::first();
    $this->assertCount(2, $translation->tags);
    $this->assertTrue($translation->tags->contains($this->mobileTag));
    $this->assertTrue($translation->tags->contains($this->webTag));

    $this->assertFalse(Cache::has("translations.export.{$this->enLocale->code}"));
  }

  /** @test */
  public function unauthenticated_users_cannot_create_translations()
  {
    $response = $this->postJson('/api/translations', [
      'locale_id' => $this->enLocale->id,
      'key' => 'unauth.key',
      'value' => 'Unauthorized value',
    ]);

    $response->assertStatus(401);
  }

  /** @test */
  public function creating_a_translation_requires_locale_id_key_and_value()
  {
    $response = $this->authenticated()->postJson('/api/translations', [
      'key' => 'missing.locale',
      'value' => 'Missing Locale',
    ]);
    $response->assertStatus(422)->assertJsonValidationErrors(['locale_id']);

    $response = $this->authenticated()->postJson('/api/translations', [
      'locale_id' => $this->enLocale->id,
      'value' => 'Missing Key',
    ]);
    $response->assertStatus(422)->assertJsonValidationErrors(['key']);

    $response = $this->authenticated()->postJson('/api/translations', [
      'locale_id' => $this->enLocale->id,
      'key' => 'missing.value',
    ]);
    $response->assertStatus(422)->assertJsonValidationErrors(['value']);
  }

  /** @test */
  public function creating_a_translation_requires_a_unique_key_per_locale()
  {
    Translation::create([
      'locale_id' => $this->enLocale->id,
      'key' => 'unique.key',
      'value' => 'Original English',
    ]);

    $response = $this->authenticated()->postJson('/api/translations', [
      'locale_id' => $this->enLocale->id,
      'key' => 'unique.key',
      'value' => 'Duplicate English',
    ]);

    $response->assertStatus(409)
    ->assertJson(['message' => 'Translation key already exists for this locale.']);

    $this->assertDatabaseCount('translations', 1);
  }

  /** @test */
  public function creating_a_translation_allows_same_key_for_different_locales()
  {
    Translation::create([
      'locale_id' => $this->enLocale->id,
      'key' => 'common.key',
      'value' => 'Hello',
    ]);

    $response = $this->authenticated()->postJson('/api/translations', [
      'locale_id' => $this->frLocale->id,
      'key' => 'common.key',
      'value' => 'Bonjour',
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseCount('translations', 2);
  }

  /** @test */
  public function creating_a_translation_with_non_existent_locale_or_tag_id_fails()
  {
    $response = $this->authenticated()->postJson('/api/translations', [
      'locale_id' => 999,
      'key' => 'test.key',
      'value' => 'Test value',
    ]);
    $response->assertStatus(422)->assertJsonValidationErrors(['locale_id']);

    $response = $this->authenticated()->postJson('/api/translations', [
      'locale_id' => $this->enLocale->id,
      'key' => 'test.key',
      'value' => 'Test value',
      'tags' => [999],
    ]);
    $response->assertStatus(422)->assertJsonValidationErrors(['tags.0']);
  }

  /** @test */
  public function an_authenticated_user_can_view_all_translations()
  {
    Translation::factory()->count(5)->create(['locale_id' => $this->enLocale->id]);
    Translation::factory()->count(2)->create(['locale_id' => $this->frLocale->id]);

    $response = $this->authenticated()->getJson('/api/translations');

    $response->assertOk()
      ->assertJsonCount(7, 'data');
  }

  /** @test */
  public function an_authenticated_user_can_view_a_single_translation()
  {
    $translation = Translation::create([
      'locale_id' => $this->enLocale->id,
      'key' => 'single.view',
      'value' => 'View this translation',
    ]);
    $translation->tags()->attach($this->mobileTag);

    $response = $this->authenticated()->getJson('/api/translations/' . $translation->id);

    $response->assertOk()
      ->assertJson([
        'key' => 'single.view',
        'value' => 'View this translation',
      ])
      ->assertJsonFragment(['code' => $this->enLocale->code])
      ->assertJsonFragment(['name' => $this->mobileTag->name]);
  }

  /** @test */
  public function viewing_a_non_existent_translation_returns_404()
  {
    $response = $this->authenticated()->getJson('/api/translations/999');
    $response->assertStatus(404);
  }

  /** @test */
  public function an_authenticated_user_can_update_a_translation_without_tags()
  {
    $translation = Translation::create([
      'locale_id' => $this->enLocale->id,
      'key' => 'old.key',
      'value' => 'Old value',
    ]);
    $this->assertFalse(Cache::has("translations.export.{$this->enLocale->code}"));

    $response = $this->authenticated()->putJson('/api/translations/' . $translation->id, [
      'key' => 'new.key',
      'value' => 'New value',
    ]);

    $response->assertOk()
      ->assertJson([
        'key' => 'new.key',
        'value' => 'New value',
      ]);

    $this->assertDatabaseHas('translations', [
      'id' => $translation->id,
      'key' => 'new.key',
      'value' => 'New value',
    ]);
    $this->assertDatabaseMissing('translations', [
      'key' => 'old.key',
    ]);
    $this->assertFalse(Cache::has("translations.export.{$this->enLocale->code}"));
  }

  /** @test */
  public function an_authenticated_user_can_update_a_translation_tags()
  {
    $translation = Translation::create([
      'locale_id' => $this->enLocale->id,
      'key' => 'tag.update',
      'value' => 'Initial tag value',
    ]);
    $translation->tags()->attach($this->mobileTag);

    $response = $this->authenticated()->putJson('/api/translations/' . $translation->id, [
      'tags' => [$this->webTag->id],
    ]);

    $response->assertOk()
      ->assertJsonFragment(['name' => 'web'])
      ->assertJsonMissing(['name' => 'mobile']);

    $this->assertDatabaseHas('tag_translation', [
      'tag_id' => $this->webTag->id,
      'translation_id' => $translation->id,
    ]);
    $this->assertDatabaseMissing('tag_translation', [
      'tag_id' => $this->mobileTag->id,
      'translation_id' => $translation->id,
    ]);
    $this->assertFalse(Cache::has("translations.export.{$this->enLocale->code}"));
  }

  /** @test */
  public function an_authenticated_user_can_remove_all_tags_from_a_translation()
  {
    $translation = Translation::create([
      'locale_id' => $this->enLocale->id,
      'key' => 'no.tags',
      'value' => 'Value with tags initially',
    ]);
    $translation->tags()->attach($this->mobileTag);

    $response = $this->authenticated()->putJson('/api/translations/' . $translation->id, [
      'tags' => [],
    ]);

    $response->assertOk();
    $this->assertCount(0, $translation->fresh()->tags);
    $this->assertFalse(Cache::has("translations.export.{$this->enLocale->code}"));
  }

  /** @test */
  public function updating_a_translation_with_duplicate_key_per_locale_fails()
  {
    Translation::create([
      'locale_id' => $this->enLocale->id,
      'key' => 'master.key',
      'value' => 'Master value',
    ]);
    $translationToUpdate = Translation::create([
      'locale_id' => $this->enLocale->id,
      'key' => 'other.key',
      'value' => 'Other value',
    ]);

    $response = $this->authenticated()->putJson('/api/translations/' . $translationToUpdate->id, [
      'key' => 'master.key',
    ]);

    $response->assertStatus(409)
    ->assertJson(['message' => 'Translation key already exists for this locale.']);
  }

  /** @test */
  public function an_authenticated_user_can_delete_a_translation()
  {
    $translation = Translation::create([
      'locale_id' => $this->enLocale->id,
      'key' => 'to.delete',
      'value' => 'Delete me',
    ]);
    $translation->tags()->attach($this->mobileTag);
    $this->assertFalse(Cache::has("translations.export.{$this->enLocale->code}"));

    $response = $this->authenticated()->deleteJson('/api/translations/' . $translation->id);

    $response->assertStatus(204);

    $this->assertDatabaseMissing('translations', [
      'id' => $translation->id,
    ]);
    $this->assertDatabaseMissing('tag_translation', [
      'translation_id' => $translation->id,
    ]);
    $this->assertFalse(Cache::has("translations.export.{$this->enLocale->code}"));
  }

  /** @test */
  public function deleting_a_non_existent_translation_returns_404()
  {
    $response = $this->authenticated()->deleteJson('/api/translations/999');
    $response->assertStatus(404);
  }

  /** @test */
  public function an_authenticated_user_can_search_translations_by_key()
  {
    Translation::create(['locale_id' => $this->enLocale->id, 'key' => 'app.homepage', 'value' => 'Home']);
    Translation::create(['locale_id' => $this->enLocale->id, 'key' => 'app.about', 'value' => 'About Us']);
    Translation::create(['locale_id' => $this->frLocale->id, 'key' => 'app.homepage', 'value' => 'Accueil']);

    $response = $this->authenticated()->getJson('/api/translations/search?key=homepage');

    $response->assertOk()
      ->assertJsonCount(2, 'data')
      ->assertJsonFragment(['key' => 'app.homepage']);
  }

  /** @test */
  public function an_authenticated_user_can_search_translations_by_content()
  {
    Translation::create(['locale_id' => $this->enLocale->id, 'key' => 'greeting', 'value' => 'Hello World']);
    Translation::create(['locale_id' => $this->frLocale->id, 'key' => 'greeting', 'value' => 'Bonjour Monde']);
    Translation::create(['locale_id' => $this->enLocale->id, 'key' => 'farewell', 'value' => 'Goodbye']);

    $response = $this->authenticated()->getJson('/api/translations/search?content=Monde');

    $response->assertOk()
      ->assertJsonCount(1, 'data')
      ->assertJsonFragment(['value' => 'Bonjour Monde']);
  }

  /** @test */
  public function an_authenticated_user_can_search_translations_by_tag()
  {
    $trans1 = Translation::create(['locale_id' => $this->enLocale->id, 'key' => 'tag.one', 'value' => 'Value One']);
    $trans1->tags()->attach($this->mobileTag);

    $trans2 = Translation::create(['locale_id' => $this->enLocale->id, 'key' => 'tag.two', 'value' => 'Value Two']);
    $trans2->tags()->attach($this->webTag);

    $response = $this->authenticated()->getJson('/api/translations/search?tag=' . $this->mobileTag->id);

    $response->assertOk()
      ->assertJsonCount(1, 'data')
      ->assertJsonFragment(['key' => 'tag.one']);
  }

  /** @test */
  public function an_authenticated_user_can_search_translations_by_locale()
  {
    Translation::create(['locale_id' => $this->enLocale->id, 'key' => 'en.key', 'value' => 'English value']);
    Translation::create(['locale_id' => $this->frLocale->id, 'key' => 'fr.key', 'value' => 'French value']);

    $response = $this->authenticated()->getJson('/api/translations/search?locale=' . $this->enLocale->id);

    $response->assertOk()
      ->assertJsonCount(1, 'data')
      ->assertJsonFragment(['value' => 'English value']);
  }

  /** @test */
  public function an_authenticated_user_can_search_translations_with_multiple_parameters()
  {
    $trans1 = Translation::create(['locale_id' => $this->enLocale->id, 'key' => 'home.title', 'value' => 'Welcome']);
    $trans1->tags()->attach($this->webTag);

    $trans2 = Translation::create(['locale_id' => $this->enLocale->id, 'key' => 'mobile.title', 'value' => 'Mobile App']);
    $trans2->tags()->attach($this->mobileTag);

    $trans3 = Translation::create(['locale_id' => $this->frLocale->id, 'key' => 'home.title', 'value' => 'Accueil']);
    $trans3->tags()->attach($this->webTag);

    $response = $this->authenticated()->getJson('/api/translations/search?locale=' . $this->enLocale->id . '&tag=' . $this->webTag->id . '&key=home');

    $response->assertOk()
      ->assertJsonCount(1, 'data')
      ->assertJsonFragment(['key' => 'home.title', 'value' => 'Welcome']);
  }

  /** @test */
  public function search_endpoint_returns_empty_data_if_no_match()
  {
    Translation::create(['locale_id' => $this->enLocale->id, 'key' => 'some.key', 'value' => 'Some value']);

    $response = $this->authenticated()->getJson('/api/translations/search?key=non.existent');

    $response->assertOk()
      ->assertJsonCount(0, 'data');
  }
}