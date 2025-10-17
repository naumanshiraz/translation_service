<?php

namespace Database\Seeders;

use App\Models\Locale;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LargeTranslationSeeder extends Seeder
{
  public function run(): void
  {
    // Ensure locales exist
    if (Locale::count() == 0) {
      Locale::create(['code' => 'en', 'name' => 'English']);
      Locale::create(['code' => 'fr', 'name' => 'French']);
      Locale::create(['code' => 'es', 'name' => 'Spanish']);
    }
    $locales = Locale::all();

    // Ensure tags exist
    if (Tag::count() == 0) {
      Tag::create(['name' => 'mobile']);
      Tag::create(['name' => 'desktop']);
      Tag::create(['name' => 'web']);
      Tag::create(['name' => 'marketing']);
      Tag::create(['name' => 'legal']);
    }
    $tags = Tag::all();

    $chunkSize = 5000;
    $totalTranslations = 100000; // Aim for 100k+

    // --- Clean up existing data securely ---
    DB::statement('SET FOREIGN_KEY_CHECKS=0;'); // Temporarily disable FK checks
    DB::table('tag_translation')->truncate();
    DB::table('translations')->truncate();
    DB::statement('SET FOREIGN_KEY_CHECKS=1;'); // Re-enable FK checks

    $this->command->info("Seeding {$totalTranslations} translations...");
    $bar = $this->command->getOutput()->createProgressBar($totalTranslations);
    $bar->start();

    for ($i = 0; $i < ($totalTranslations / $chunkSize); $i++) {
      $translationsToInsert = [];
      for ($j = 0; $j < $chunkSize; $j++) {
        $locale = $locales->random();
        $translationsToInsert[] = [
          'locale_id' => $locale->id,
          'key' => 'app_key_' . uniqid() . '_' . ($i * $chunkSize + $j + 1), // More robust unique key
          'value' => 'This is a sample translation for ' . $locale->code . ' number ' . ($i * $chunkSize + $j + 1),
          'created_at' => now(),
          'updated_at' => now(),
        ];
      }

      // --- Step 1: Insert translations and get their actual IDs ---
      // Using `insertGetId` would be ideal, but it's for a single row.
      // For multiple rows, we need to get the range of IDs.
      // This assumes IDs are sequential and no other writes happen concurrently.
      $initialMaxId = DB::table('translations')->max('id') ?? 0;
      DB::table('translations')->insert($translationsToInsert);
      $finalMaxId = DB::table('translations')->max('id');

      // Get the range of IDs that were just inserted
      $insertedTranslationIds = range($initialMaxId + 1, $finalMaxId);

      // --- Step 2: Build tag_translation data using actual IDs ---
      $tagTranslationToInsert = [];
      foreach ($insertedTranslationIds as $translationId) {
        // Attach 1-3 random tags to each translation
        $randomTags = $tags->random(rand(1, min(3, $tags->count())));
        foreach ($randomTags as $tag) {
          $tagTranslationToInsert[] = [
            'tag_id' => $tag->id,
            'translation_id' => $translationId,
          ];
        }
      }

      // --- Step 3: Insert into the pivot table ---
      if (!empty($tagTranslationToInsert)) {
        DB::table('tag_translation')->insert($tagTranslationToInsert);
      }
      $bar->advance($chunkSize);
    }

    $bar->finish();
    $this->command->info("\nSeeding complete. Total translations: " . DB::table('translations')->count());
  }
}