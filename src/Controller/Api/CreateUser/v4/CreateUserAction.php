<?php

namespace App\Controller\Api\CreateUser\v4;

use App\Entity\User;
use App\Manager\UserManager;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\RequestParam;
use JsonException;
use Symfony\Component\HttpFoundation\Response;
use App\DTO\ManageUserDTO;

class CreateUserAction extends AbstractFOSRestController
{
    public function __construct(private readonly UserManager $userManager)
    {
    }

    /**
     * @throws JsonException
     */
    #[Rest\Post(path: '/api/v4/users')]
    #[RequestParam(name: 'login')]
    #[RequestParam(name: 'password')]
    #[RequestParam(name: 'roles')]
    #[RequestParam(name: 'age', requirements: '\d+')]
    #[RequestParam(name: 'isActive', requirements: 'true|false')]
    public function __invoke(
        string $login,
        string $password,
        string $roles,
        string $age,
        string $isActive,
    ): Response {
        $userDTO = new ManageUserDTO(...[
            'login' => $login,
            'password' => $password,
            'age' => (int)$age,
            'isActive' => $isActive === 'true',
            'roles' => json_decode($roles, true, 512, JSON_THROW_ON_ERROR),
        ]);

        $userId = $this->userManager->saveUserFromDTO(new User(), $userDTO);
        [$data, $code] = ($userId === null) ? [['success' => false], 400] : [['id' => $userId], 200];

        return $this->handleView($this->view($data, $code));
    }
}
