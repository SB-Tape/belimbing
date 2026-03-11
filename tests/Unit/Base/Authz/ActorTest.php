<?php

use App\Base\Authz\DTO\Actor;
use App\Base\Authz\Enums\PrincipalType;
use App\Base\Foundation\Contracts\CompanyScoped;
use Illuminate\Contracts\Auth\Authenticatable;

it('builds a human user actor from a company scoped user', function (): void {
    $user = new class implements Authenticatable, CompanyScoped
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): int
        {
            return 42;
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }

        public function getCompanyId(): ?int
        {
            return 7;
        }
    };

    $actor = Actor::forUser($user);

    expect($actor->type)->toBe(PrincipalType::HUMAN_USER)
        ->and($actor->id)->toBe(42)
        ->and($actor->companyId)->toBe(7)
        ->and($actor->actingForUserId)->toBeNull();
});

it('builds an actor from a user company_id attribute when company scoped is unavailable', function (): void {
    $user = new class implements Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): int
        {
            return 99;
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }

        public function getAttribute(string $key): mixed
        {
            return $key === 'company_id' ? '15' : null;
        }
    };

    $actor = Actor::forUser($user, PrincipalType::DIGITAL_WORKER, actingForUserId: 5, attributes: ['source' => 'test']);

    expect($actor->type)->toBe(PrincipalType::DIGITAL_WORKER)
        ->and($actor->id)->toBe(99)
        ->and($actor->companyId)->toBe(15)
        ->and($actor->actingForUserId)->toBe(5)
        ->and($actor->attributes)->toBe(['source' => 'test']);
});
