<?php

declare(strict_types=1);

namespace App\Webhooks;

enum DeliveryState: string
{
    case Pending = 'pending';
    case Retrying = 'retrying';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
