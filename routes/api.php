<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LocaleController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TranslationController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/login', function (Request $request) {
  $request->validate([
    'email' => ['required', 'email'],
    'password' => ['required'],
  ]);

  $user = User::where('email', $request->email)->first();

  if (! $user || ! Hash::check($request->password, $user->password)) {
    throw ValidationException::withMessages([
      'email' => ['The provided credentials are incorrect.'],
    ]);
  }

  return response()->json([
    'token' => $user->createToken('auth_token')->plainTextToken,
    'message' => 'Logged in successfully!'
  ]);
});

Route::middleware('auth:sanctum')->group(function () {
  Route::get('/user', function (Request $request) {
    return $request->user();
  });

  Route::post('/logout', function (Request $request) {
    $request->user()->currentAccessToken()->delete();
    return response()->json(['message' => 'Logged out successfully!']);
  });

  Route::get('translations/search', [TranslationController::class, 'search']);

  Route::get('translations/export/{localeCode}', [TranslationController::class, 'export']);

  Route::apiResource('locales', LocaleController::class);
  Route::apiResource('tags', TagController::class);
  Route::apiResource('translations', TranslationController::class);
});
