<?php

namespace App\Service;

use App\Manager\UserManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthService
{
    public function __construct(
        private readonly UserManager $userManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    )
    {
    }

    public function isCredentialsValid(string $login, string $password): bool
    {
        $user = $this->userManager->findUserByLogin($login);
        if ($user === null) {
            return false;
        }

        return $this->passwordHasher->isPasswordValid($user, $password);
    }

    public function getToken(string $login): ?string
    {
        return $this->userManager->updateUserToken($login);
    }
}
