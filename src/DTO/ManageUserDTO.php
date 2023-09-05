<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\Request;
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

        #[Assert\Type('array')]
        public array $roles = [],

        #[Assert\Length(max: 11)]
        public ?string $phone = null,

        #[Assert\Length(max: 128)]
        public ?string $email = null,

        #[Assert\Length(max: 10)]
        public ?string $preferred = null,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(...[
            'login' => $user->getLogin()->getValue(),
            'password' => $user->getPassword(),
            'age' => $user->getAge(),
            'isActive' => $user->isActive(),
            'roles' => $user->getRoles(),
            'followers' => array_map(
                static function (User $user) {
                    return [
                        'id' => $user->getId(),
                        'login' => $user->getLogin()->getValue(),
                        'password' => $user->getPassword(),
                        'age' => $user->getAge(),
                        'isActive' => $user->isActive(),
                    ];
                },
                $user->getFollowers()
            ),
            'phone' => $user->getPhone(),
            'email' => $user->getEmail(),
            'preferred' => $user->getPreferred(),
        ]);
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            login: $request->request->get('login') ?? $request->query->get('login'),
            password: $request->request->get('password') ?? $request->query->get('password'),
            age: $request->request->get('age') ?? $request->query->get('age'),
            isActive: $request->request->get('isActive') ?? $request->query->get('isActive'),
            roles: $request->request->get('roles') ?? $request->query->get('roles') ?? [],
        );
    }
}
