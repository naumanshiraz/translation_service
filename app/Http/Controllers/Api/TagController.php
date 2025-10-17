<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
  public function index()
  {
    return response()->json(Tag::all());
  }

  public function store(Request $request)
  {
    $validated = $request->validate([
      'name' => 'required|string|unique:tags|max:255',
    ]);

    $tag = Tag::create($validated);
    return response()->json($tag, 201);
  }

  public function show(Tag $tag)
  {
    return response()->json($tag);
  }

  public function update(Request $request, Tag $tag)
  {
    $validated = $request->validate([
      'name' => 'sometimes|required|string|unique:tags,name,' . $tag->id . '|max:255',
    ]);

    $tag->update($validated);
    return response()->json($tag);
  }

  public function destroy(Tag $tag)
  {
    $tag->delete();
    return response()->json(null, 204);
  }
}