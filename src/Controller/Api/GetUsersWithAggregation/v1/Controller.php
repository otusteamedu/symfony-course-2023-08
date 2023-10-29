<?php

namespace App\Controller\Api\GetUsersWithAggregation\v1;

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

    #[Route(path: '/api/v1/get-users-with-aggregation', methods: ['GET'])]
    #[QueryParam(name: 'field')]
    public function getUsersWithAggregationAction(string $field): Response
    {
        return $this->handleView($this->view($this->userManager->findUserWithAggregation($field), 200));
    }
}
