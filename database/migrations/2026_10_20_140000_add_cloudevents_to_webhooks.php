<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // CloudEvents' source and subject, kept with the event so a redelivery sends the same.
        Schema::table('webhook_events', function (Blueprint $table): void {
            $table->string('source')->default('');
            $table->string('subject')->nullable();
        });

        // When the endpoint consented to deliveries (CloudEvents webhook handshake), if it has.
        Schema::table('webhook_subscriptions', function (Blueprint $table): void {
            $table->timestamp('validated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('webhook_subscriptions', function (Blueprint $table): void {
            $table->dropColumn('validated_at');
        });

        Schema::table('webhook_events', function (Blueprint $table): void {
            $table->dropColumn(['source', 'subject']);
        });
    }
};
