<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\DeleteAllTokensController;
use App\Http\Controllers\Api\V1\Auth\DeleteTokenController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\ListTokensController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\SendEmailVerificationNotificationController;
use App\Http\Controllers\Api\V1\Auth\ShowResetPasswordTokenController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', RegisterController::class)
    ->middleware(['idempotency', 'throttle:auth-register'])
    ->name('v1.auth.register');
Route::post('/auth/login', LoginController::class)
    ->middleware('throttle:auth-login')
    ->name('v1.auth.login');
Route::post('/auth/password/forgot', ForgotPasswordController::class)
    ->middleware(['idempotency', 'throttle:auth-password'])
    ->name('v1.auth.password.forgot');
Route::post('/auth/password/reset', ResetPasswordController::class)
    ->middleware('throttle:auth-password')
    ->name('v1.auth.password.reset');
Route::get('/auth/password/reset/{token}', ShowResetPasswordTokenController::class)
    ->name('password.reset');
Route::get('/auth/email/verify/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware(['auth:sanctum', 'throttle:auth-protected'])->group(function (): void {
    Route::get('/auth/me', MeController::class)
        ->middleware('abilities:auth:me')
        ->name('v1.auth.me');
    Route::post('/auth/logout', LogoutController::class)
        ->middleware('abilities:auth:logout')
        ->name('v1.auth.logout');
    Route::get('/auth/tokens', ListTokensController::class)
        ->middleware('abilities:auth:tokens:read')
        ->name('v1.auth.tokens.index');
    Route::delete('/auth/tokens', DeleteAllTokensController::class)
        ->middleware('abilities:auth:tokens:delete')
        ->name('v1.auth.tokens.destroy-all');
    Route::delete('/auth/tokens/{token_id}', DeleteTokenController::class)
        ->middleware('abilities:auth:tokens:delete')
        ->name('v1.auth.tokens.destroy');
    Route::post('/auth/email/verification-notification', SendEmailVerificationNotificationController::class)
        ->middleware(['abilities:auth:verification:send', 'throttle:6,1'])
        ->name('v1.auth.email.verification-notification');
});
