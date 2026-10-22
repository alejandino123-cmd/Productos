<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Segundo paso del usuario normal. No usan token: se identifican con el
// header X-Login-Ticket que entrega /login. Tienen límite de intentos por minuto.
Route::post('/2fa/email/send', [AuthController::class, 'sendCode'])->middleware('throttle:3,1');
Route::post('/verify-code', [AuthController::class, 'verifyCode'])->middleware('throttle:5,1');
Route::get('/2fa/generate', [TwoFactorController::class, 'generateQr'])->middleware('throttle:10,1');
Route::post('/2fa/verify', [TwoFactorController::class, 'verifyQr'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::apiResource('products', ProductController::class);
});