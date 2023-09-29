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
        #[Assert\GreaterThan(18)]
        public int $age = 0,

        public bool $isActive = false,

        #[Assert\Type('array')]
        public array $followers = [],
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(...[
            'login' => $user->getLogin(),
            'password' => $user->getPassword(),
            'age' => $user->getAge(),
            'isActive' => $user->isActive(),
            'followers' => array_map(
                static function (User $user) {
                    return [
                        'id' => $user->getId(),
                        'login' => $user->getLogin(),
                        'password' => $user->getPassword(),
                        'age' => $user->getAge(),
                        'isActive' => $user->isActive(),
                    ];
                },
                $user->getFollowers()
            ),
        ]);
    }
}
