<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Override;

/** @extends Factory<User> */
final class UserFactory extends Factory
{
    /** @var class-string<User> */
    #[Override]
    protected $model = User::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'id' => mb_strtolower((string) Str::ulid()),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn(array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }
}
