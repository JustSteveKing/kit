<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Laravel's password reset email, sent from the queue so a slow mail server never slows a request,
 * and so the time to answer does not depend on whether the account exists.
 */
final class ResetPassword extends BaseResetPassword implements ShouldQueue
{
    use Queueable;
}
