<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Issues Sanctum tokens. Every token carries its own expires_at, since sanctum.expiration is off
 * so that API keys can outlive a sign-in session.
 */
final class AccessTokens
{
    /**
     * A session token, from register or login: the default abilities, expiring after
     * sanctum.lifetimes.session_minutes, or never when that is not positive.
     */
    public function issueSession(User $user, string $deviceName): NewAccessToken
    {
        $minutes = config()->integer('sanctum.lifetimes.session_minutes', 120);

        return $user->createToken(
            $deviceName,
            $this->configuredAbilities('sanctum.abilities.default'),
            $minutes > 0 ? now()->addMinutes($minutes) : null,
        );
    }

    /**
     * An API key with the given abilities, which the caller has already checked with grantableBy().
     *
     * @param  list<string>  $abilities
     */
    public function issueApiKey(User $user, string $name, array $abilities, Carbon $expiresAt): NewAccessToken
    {
        return $user->createToken($name, $abilities, $expiresAt);
    }

    /**
     * The abilities a token can grant to an API key: those it holds itself, out of the abilities
     * routes check. A token holding "*" can grant any of them.
     *
     * @return list<string>
     */
    public function grantableBy(PersonalAccessToken $token): array
    {
        $available = $this->configuredAbilities('sanctum.abilities.available');
        $held = array_filter(
            is_array($token->abilities) ? $token->abilities : [],
            is_string(...),
        );

        if (in_array('*', $held, true)) {
            return $available;
        }

        return array_values(array_intersect($available, $held));
    }

    public function defaultApiKeyExpiry(): Carbon
    {
        return now()->addDays(config()->integer('sanctum.lifetimes.api_key_default_days', 90));
    }

    public function latestApiKeyExpiry(): Carbon
    {
        return now()->addDays(config()->integer('sanctum.lifetimes.api_key_max_days', 365));
    }

    /**
     * @return list<string>
     */
    private function configuredAbilities(string $key): array
    {
        return array_values(array_filter(
            config()->array($key, []),
            static fn(mixed $ability): bool => is_string($ability) && '' !== mb_trim($ability),
        ));
    }
}
