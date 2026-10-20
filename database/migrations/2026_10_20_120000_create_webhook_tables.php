<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('webhook_subscriptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->string('description')->nullable();
            $table->json('events');
            $table->text('secret');
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->string('disabled_reason')->nullable();
            // When the endpoint consented to deliveries (CloudEvents webhook handshake), if it has,
            // and the latest validation request: only its answer or its callback may grant consent.
            $table->timestamp('validated_at')->nullable();
            $table->string('validation_request', 32)->nullable();
            // Deliveries a minute the endpoint granted (WebHook-Allowed-Rate); null means no limit.
            $table->unsignedInteger('allowed_rate')->nullable();
            $table->timestamps();
        });

        // The event itself, a CloudEvent: what every delivery and redelivery of it sends, byte for byte.
        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('source');
            $table->string('subject')->nullable();
            $table->json('data');
            $table->timestamp('created_at')->index();
        });

        // One per event and subscription: the delivery's state, not its history.
        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('webhook_subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('webhook_event_id')->constrained()->cascadeOnDelete();
            $table->string('state');
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamps();
        });

        // One per HTTP attempt. A null response_status means no response arrived at all.
        Schema::create('webhook_delivery_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('webhook_delivery_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->string('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('attempted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_delivery_attempts');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('webhook_subscriptions');
    }
};
