<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Locale;
use App\Models\Translation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TranslationController extends Controller
{
  public function index()
  {
    return response()->json(Translation::with('locale', 'tags')->paginate(20));
  }

  public function store(Request $request)
  {
    $validated = $request->validate([
      'locale_id' => 'required|exists:locales,id',
      'key' => 'required|string|max:255',
      'value' => 'required|string',
      'tags' => 'array',
      'tags.*' => 'exists:tags,id',
    ]);

    if (Translation::where('locale_id', $validated['locale_id'])->where('key', $validated['key'])->exists()) {
      return response()->json(['message' => 'Translation key already exists for this locale.'], 409);
    }

    $translation = Translation::create($validated);

    if (isset($validated['tags'])) {
      $translation->tags()->attach($validated['tags']);
    }

    $localeCode = Locale::find($validated['locale_id'])->code;
    Cache::forget("translations.export.{$localeCode}");

    return response()->json($translation->load('locale', 'tags'), 201);
  }

  public function show(Translation $translation)
  {
    return response()->json($translation->load('locale', 'tags'));
  }

  public function update(Request $request, Translation $translation)
  {
    $validated = $request->validate([
      'locale_id' => 'sometimes|required|exists:locales,id',
      'key' => 'sometimes|required|string|max:255',
      'value' => 'sometimes|required|string',
      'tags' => 'array',
      'tags.*' => 'exists:tags,id',
    ]);

    if (isset($validated['key']) || isset($validated['locale_id'])) {
      $localeId = $validated['locale_id'] ?? $translation->locale_id;
      $key = $validated['key'] ?? $translation->key;

      if (Translation::where('locale_id', $localeId)
        ->where('key', $key)
        ->where('id', '!=', $translation->id)
        ->exists()) {
        return response()->json(['message' => 'Translation key already exists for this locale.'], 409);
      }
    }

    $translation->update($validated);

    if (isset($validated['tags'])) {
      $translation->tags()->sync($validated['tags']);
    } else if (array_key_exists('tags', $validated) && empty($validated['tags'])) {
      $translation->tags()->detach();
    }

    $localeCode = $translation->locale->code;
    Cache::forget("translations.export.{$localeCode}");

    return response()->json($translation->load('locale', 'tags'));
  }

  public function destroy(Translation $translation)
  {
    $localeCode = $translation->locale->code;
    Cache::forget("translations.export.{$localeCode}");

    $translation->delete();
    return response()->json(null, 204);
  }

  public function search(Request $request)
  {
    $query = Translation::query()->with('locale', 'tags');

    if ($request->has('key')) {
      $query->where('key', 'like', '%' . $request->input('key') . '%');
    }
    if ($request->has('content')) {
      $query->where('value', 'like', '%' . $request->input('content') . '%');
    }
    if ($request->has('tag')) {
      $tagId = $request->input('tag');
      $query->whereHas('tags', function ($q) use ($tagId) {
        $q->where('tags.id', $tagId);
      });
    }
    if ($request->has('locale')) {
      $localeId = $request->input('locale');
      $query->where('locale_id', $localeId);
    }

    return response()->json($query->paginate(20));
  }

  /**
   * Provides a JSON export for frontend applications.
   * Caches the result to ensure fast responses for subsequent requests.
   */
  public function export(string $localeCode)
  {
    $translations = Cache::remember("translations.export.{$localeCode}", 3600, function () use ($localeCode) {
      $locale = Locale::where('code', $localeCode)->firstOrFail();

      return Translation::where('locale_id', $locale->id)
        ->pluck('value', 'key');
    });

    return response()->json($translations);
  }
}