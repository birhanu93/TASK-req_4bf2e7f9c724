<?php

declare(strict_types=1);

namespace App\Entity;

final class User
{
    /** @var string[] */
    private array $roles;

    /**
     * @param string[] $roles
     */
    public function __construct(
        private string $id,
        private string $username,
        private string $passwordHash,
        array $roles,
        private ?string $encryptedProfile = null,
        private bool $active = true,
    ) {
        $this->roles = array_values(array_unique($roles));
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $hash): void
    {
        $this->passwordHash = $hash;
    }

    /**
     * @return string[]
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function addRole(string $role): void
    {
        if (!$this->hasRole($role)) {
            $this->roles[] = $role;
        }
    }

    public function removeRole(string $role): void
    {
        $this->roles = array_values(array_filter($this->roles, fn ($r) => $r !== $role));
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function getEncryptedProfile(): ?string
    {
        return $this->encryptedProfile;
    }

    public function setEncryptedProfile(?string $blob): void
    {
        $this->encryptedProfile = $blob;
    }
}
