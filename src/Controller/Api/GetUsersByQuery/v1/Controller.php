<?php

namespace App\Controller\Api\GetUsersByQuery\v1;

use App\Manager\UserManager;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class Controller extends AbstractFOSRestController
{
    public function __construct(private UserManager $userManager)
    {
    }

    #[Route(path: '/api/v1/get-users-by-query', methods: ['GET'])]
    #[QueryParam(name: 'query')]
    #[QueryParam(name: 'perPage', requirements: '\d+')]
    #[QueryParam(name: 'page', requirements: '\d+')]
    public function getUsersByQueryAction(string $query, int $perPage, int $page): Response
    {
        return $this->handleView($this->view($this->userManager->findUserByQuery($query, $perPage, $page), 200));
    }
}
