<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Locale;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
  public function index()
  {
    return response()->json(Locale::all());
  }

  public function store(Request $request)
  {
    $validated = $request->validate([
      'code' => 'required|string|unique:locales|max:10',
      'name' => 'required|string|max:50',
    ]);

    $locale = Locale::create($validated);
    return response()->json($locale, 201);
  }

  public function show(Locale $locale)
  {
    return response()->json($locale);
  }

  public function update(Request $request, Locale $locale)
  {
    $validated = $request->validate([
      'code' => 'sometimes|required|string|unique:locales,code,' . $locale->id . '|max:10',
      'name' => 'sometimes|required|string|max:50',
    ]);

    $locale->update($validated);
    return response()->json($locale);
  }

  public function destroy(Locale $locale)
  {
    $locale->delete();
    return response()->json(null, 204);
  }
}