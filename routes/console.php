<?php

declare(strict_types=1);

use App\Models\WebhookEvent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Webhook events older than webhooks.retention_days go, with their deliveries and attempts.
Schedule::command('model:prune', ['--model' => [WebhookEvent::class]])->daily();
