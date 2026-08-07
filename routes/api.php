<?php

use App\Http\Controllers\Api\V1\BookController;
use App\Http\Controllers\TokenController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/tokens', [TokenController::class, 'store'])->name('api.tokens.store');

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::apiResource('books', BookController::class)->missing(function (): JsonResponse {
        return response()->json(['error' => '書籍が見つかりませんでした。'], 404);
    })->only(['index', 'show']);

    Route::apiResource('books', BookController::class)
        ->missing(function (): JsonResponse {
            return response()->json(['error' => '書籍が見つかりませんでした。'], 404);
        })
        ->only(['store', 'update', 'destroy'])
        ->middleware('auth:sanctum');
});
