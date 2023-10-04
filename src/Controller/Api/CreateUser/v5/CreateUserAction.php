<?php

namespace App\Controller\Api\CreateUser\v5;

use App\Controller\Api\CreateUser\v5\Input\CreateUserDTO;
use App\Controller\Common\ErrorResponseTrait;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class CreateUserAction extends AbstractFOSRestController
{
    use ErrorResponseTrait;

    public function __construct(private readonly CreateUserManager $saveUserManager)
    {
    }

    #[Rest\Post(path: '/api/v5/users')]
    public function saveUserAction(CreateUserDTO $request, ConstraintViolationListInterface $validationErrors): Response
    {
        if ($validationErrors->count()) {
            $view = $this->createValidationErrorResponse(Response::HTTP_BAD_REQUEST, $validationErrors);
            return $this->handleView($view);
        }
        $user = $this->saveUserManager->saveUser($request);
        [$data, $code] = ($user->id === null) ? [['success' => false], 400] : [['user' => $user], 200];

        return $this->handleView($this->view($data, $code));
    }
}
