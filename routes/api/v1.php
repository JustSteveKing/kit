<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\CreateTokenController;
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
use App\Http\Controllers\Api\V1\Webhooks\CreateWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\DeleteWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\GrantWebhookConsentController;
use App\Http\Controllers\Api\V1\Webhooks\ListWebhookDeliveriesController;
use App\Http\Controllers\Api\V1\Webhooks\ListWebhooksController;
use App\Http\Controllers\Api\V1\Webhooks\OpenWebhookConsentLinkController;
use App\Http\Controllers\Api\V1\Webhooks\PingWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\RedeliverWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\RotateWebhookSecretController;
use App\Http\Controllers\Api\V1\Webhooks\ShowWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\UpdateWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\ValidateWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', RegisterController::class)
    ->middleware(['idempotent', 'throttle:auth-register'])
    ->name('v1.auth.register');
Route::post('/auth/login', LoginController::class)
    ->middleware('throttle:auth-login')
    ->name('v1.auth.login');
Route::post('/auth/password/forgot', ForgotPasswordController::class)
    ->middleware(['idempotent', 'throttle:auth-password'])
    ->name('v1.auth.password.forgot');
Route::post('/auth/password/reset', ResetPasswordController::class)
    ->middleware('throttle:auth-password')
    ->name('v1.auth.password.reset');
Route::get('/auth/password/reset/{token}', ShowResetPasswordTokenController::class)
    ->name('password.reset');
Route::get('/auth/email/verify/{id}/{hash}', VerifyEmailController::class)
    ->whereUlid('id')
    ->where('hash', '[0-9a-f]{40}')
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// The URL is the credential: it is signed, expires, and is sent only to the endpoint asked.
// The CloudEvents webhook spec asks for both methods; each is its own documented operation.
Route::get('/webhooks/{webhook}/consent', OpenWebhookConsentLinkController::class)
    ->whereUlid('webhook')
    ->middleware('throttle:6,1')
    ->name('v1.webhooks.consent');
Route::post('/webhooks/{webhook}/consent', GrantWebhookConsentController::class)
    ->whereUlid('webhook')
    ->middleware('throttle:6,1')
    ->name('v1.webhooks.consent.grant');

Route::middleware(['auth:sanctum', 'throttle:auth-protected'])->group(function (): void {
    Route::get('/auth/me', MeController::class)
        ->middleware(['abilities:auth:me', 'conditional'])
        ->name('v1.auth.me');
    Route::post('/auth/logout', LogoutController::class)
        ->middleware('abilities:auth:logout')
        ->name('v1.auth.logout');
    Route::get('/auth/tokens', ListTokensController::class)
        ->middleware('abilities:auth:tokens:read')
        ->name('v1.auth.tokens.index');
    Route::post('/auth/tokens', CreateTokenController::class)
        ->middleware(['abilities:auth:tokens:create', 'idempotent'])
        ->name('v1.auth.tokens.store');
    Route::delete('/auth/tokens', DeleteAllTokensController::class)
        ->middleware('abilities:auth:tokens:delete')
        ->name('v1.auth.tokens.destroy-all');
    Route::delete('/auth/tokens/{token_id}', DeleteTokenController::class)
        ->whereNumber('token_id')
        ->middleware('abilities:auth:tokens:delete')
        ->name('v1.auth.tokens.destroy');
    Route::post('/auth/email/verification-notification', SendEmailVerificationNotificationController::class)
        ->middleware(['abilities:auth:verification:send', 'throttle:6,1'])
        ->name('v1.auth.email.verification-notification');

    Route::get('/webhooks', ListWebhooksController::class)
        ->middleware('abilities:webhooks:read')
        ->name('v1.webhooks.index');
    Route::post('/webhooks', CreateWebhookController::class)
        ->middleware(['abilities:webhooks:write', 'idempotent'])
        ->name('v1.webhooks.store');
    Route::get('/webhooks/{webhook}', ShowWebhookController::class)
        ->whereUlid('webhook')
        ->middleware('abilities:webhooks:read')
        ->name('v1.webhooks.show');
    Route::patch('/webhooks/{webhook}', UpdateWebhookController::class)
        ->whereUlid('webhook')
        ->middleware('abilities:webhooks:write')
        ->name('v1.webhooks.update');
    Route::delete('/webhooks/{webhook}', DeleteWebhookController::class)
        ->whereUlid('webhook')
        ->middleware('abilities:webhooks:write')
        ->name('v1.webhooks.destroy');
    Route::post('/webhooks/{webhook}/secret', RotateWebhookSecretController::class)
        ->whereUlid('webhook')
        ->middleware('abilities:webhooks:write')
        ->name('v1.webhooks.secret.rotate');
    Route::post('/webhooks/{webhook}/validate', ValidateWebhookController::class)
        ->whereUlid('webhook')
        ->middleware(['abilities:webhooks:write', 'throttle:6,1'])
        ->name('v1.webhooks.validate');
    Route::post('/webhooks/{webhook}/ping', PingWebhookController::class)
        ->whereUlid('webhook')
        ->middleware(['abilities:webhooks:write', 'throttle:6,1'])
        ->name('v1.webhooks.ping');
    Route::get('/webhooks/{webhook}/deliveries', ListWebhookDeliveriesController::class)
        ->whereUlid('webhook')
        ->middleware('abilities:webhooks:read')
        ->name('v1.webhooks.deliveries.index');
    Route::post('/webhooks/{webhook}/deliveries/{delivery}/redeliver', RedeliverWebhookController::class)
        ->whereUlid(['webhook', 'delivery'])
        ->middleware(['abilities:webhooks:write', 'throttle:6,1'])
        ->name('v1.webhooks.deliveries.redeliver');
});
