<?php

namespace App\Controller\Api\CreateUser\v5;

use App\Controller\Api\CreateUser\v5\Input\CreateUserDTO;
use App\Controller\Api\CreateUser\v5\Output\UserIsCreatedDTO;
use App\Controller\Common\ErrorResponseTrait;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use OpenApi\Attributes as OA;

class CreateUserAction extends AbstractFOSRestController
{
    use ErrorResponseTrait;

    public function __construct(private readonly CreateUserManagerInterface $saveUserManager)
    {
    }

    #[Rest\Post(path: '/api/v5/users')]
    #[OA\Post(
        operationId: 'addUser',
        requestBody: new OA\RequestBody(
            description: 'Input data format',
            content: new Model(type: CreateUserDTO::class),
        ),
        tags: ['Пользователи'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new Model(type: UserIsCreatedDTO::class),
            )
        ]
    )]
    public function saveUserAction(#[MapRequestPayload] CreateUserDTO $request): Response
    {
        $user = $this->saveUserManager->saveUser($request);
        [$data, $code] = ($user->id === null) ? [['success' => false], 400] : [['user' => $user], 200];

        return $this->handleView($this->view($data, $code));
    }
}
