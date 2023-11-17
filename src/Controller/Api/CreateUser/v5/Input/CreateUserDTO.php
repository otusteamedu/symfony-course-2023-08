<?php

namespace App\Controller\Api\CreateUser\v5\Input;

use App\DTO\Traits\SafeLoadFieldsTrait;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

class CreateUserDTO
{
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(max: 32)]
    #[OA\Property(example: 'my_user')]
    public string $login;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(max: 32)]
    #[OA\Property(example: 'pass')]
    public string $password;

    #[Assert\NotBlank]
    #[Assert\Type('array')]
    #[OA\Property(type: 'array', items: new OA\Items(type: 'string', example: 'ROLE_USER'))]
    public array $roles;

    #[Assert\NotBlank]
    #[Assert\Type('numeric')]
    #[OA\Property(example: 12)]
    public int $age;

    #[Assert\NotBlank]
    #[Assert\Type('bool')]
    #[OA\Property(property: 'isActive')]
    public bool $isActive;

    public function getSafeFields(): array
    {
        return ['login', 'password', 'roles', 'age', 'isActive'];
    }
}
