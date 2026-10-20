<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\ResetPassword;
use App\Notifications\VerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Override;
use SensitiveParameter;

/**
 * @property Carbon|null $email_verified_at
 */
final class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasUlids;
    use MustVerifyEmailTrait;
    use Notifiable;

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = ['name', 'email', 'password'];

    /**
     * @var list<string>
     */
    #[Override]
    protected $hidden = ['password', 'remember_token'];

    /**
     * @return HasMany<WebhookSubscription, $this>
     */
    public function webhookSubscriptions(): HasMany
    {
        return $this->hasMany(WebhookSubscription::class);
    }

    /**
     * Queued, in the locale of the request that asked for it: a queued job otherwise runs in the
     * application's default locale.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmail()->locale(app()->getLocale()));
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification(#[SensitiveParameter] $token): void
    {
        $this->notify(new ResetPassword($token)->locale(app()->getLocale()));
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
