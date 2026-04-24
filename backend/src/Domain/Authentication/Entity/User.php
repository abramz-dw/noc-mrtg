<?php

namespace App\Domain\Authentication\Entity;

class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $passwordHash,
        public readonly string $fullName,
        public readonly string $email,
        public readonly string $role,
        public readonly string $status,
        public readonly ?string $lastLogin = null,
        public readonly int $failedLoginAttempts = 0,
        public readonly ?string $lockedUntil = null
    ) {}

    public function isActive(): bool
    {
        return $this->status === 'Active';
    }

    public function isLocked(): bool
    {
        if ($this->lockedUntil === null) {
            return false;
        }
        return new \DateTime($this->lockedUntil) > new \DateTime();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'Admin';
    }

    public function isNoc(): bool
    {
        return $this->role === 'NOC';
    }

    public function isSupport(): bool
    {
        return $this->role === 'Support';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'Customer';
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }
}
