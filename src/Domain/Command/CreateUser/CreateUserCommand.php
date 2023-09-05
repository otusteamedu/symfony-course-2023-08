<?php

namespace App\Domain\Command\CreateUser;

use App\Controller\Api\CreateUser\v5\Input\CreateUserDTO;
use App\Domain\ValueObject\UserLogin;
use JMS\Serializer\Annotation as JMS;

final class CreateUserCommand
{
    private function __construct(
        private readonly UserLogin $login,
        private readonly string $password,
        /** @JMS\Type("array<string>") */
        private readonly array $roles,
        private readonly int $age,
        private readonly bool $isActive,
    ) {
    }

    public function getLogin(): UserLogin
    {
        return $this->login;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getAge(): int
    {
        return $this->age;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public static function createFromRequest(CreateUserDTO $request): self
    {
        return new self(
            new UserLogin($request->login),
            $request->password,
            $request->roles,
            $request->age,
            $request->isActive,
        );
    }
}
