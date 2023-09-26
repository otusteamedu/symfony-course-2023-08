<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\User;

class ManageUserDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 32)]
        public string $login = '',

        #[Assert\NotBlank]
        #[Assert\Length(max: 32)]
        public string $password = '',

        #[Assert\NotBlank]
        public int $age = 0,

        public bool $isActive = false,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(...[
            'login' => $user->getLogin(),
            'password' => $user->getPassword(),
            'age' => $user->getAge(),
            'isActive' => $user->isActive(),
        ]);
    }
}
