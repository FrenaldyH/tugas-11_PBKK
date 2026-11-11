<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatAiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public: get a token. Throttled against password guessing.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

// Protected: valid Sanctum Bearer token required, otherwise 401.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/logout', [AuthController::class, 'logout']);

    // Throttled so a leaked token cannot drain the LLM server
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/chat-ai', [ChatAiController::class, 'store']);
        Route::post('/chat-ai/stream', [ChatAiController::class, 'stream']);
    });
    Route::get('/chat-ai/history', [ChatAiController::class, 'history']);
});
