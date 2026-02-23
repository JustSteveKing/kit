<?php

declare(strict_types=1);

return [
    'ttl_minutes' => (int) env('IDEMPOTENCY_TTL_MINUTES', 10),
];
