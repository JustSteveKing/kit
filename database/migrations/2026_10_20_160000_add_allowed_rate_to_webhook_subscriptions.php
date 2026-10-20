<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Deliveries a minute the endpoint granted (WebHook-Allowed-Rate); null means no limit.
        Schema::table('webhook_subscriptions', function (Blueprint $table): void {
            $table->unsignedInteger('allowed_rate')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('webhook_subscriptions', function (Blueprint $table): void {
            $table->dropColumn('allowed_rate');
        });
    }
};
