<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Laravel's verification email, sent from the queue so a slow mail server never slows a request.
 */
final class VerifyEmail extends BaseVerifyEmail implements ShouldQueue
{
    use Queueable;
}
