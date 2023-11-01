<?php

namespace App\Controller\Api\GetUsersByQueryWithAggregation\v1;

use App\Manager\UserManager;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class Controller extends AbstractFOSRestController
{
    private UserManager $userManager;

    public function __construct(UserManager $userManager)
    {
        $this->userManager = $userManager;
    }

    #[Route(path: '/api/v1/get-users-by-query-with-aggregation', methods: ['GET'])]
    #[QueryParam(name: 'query')]
    #[QueryParam(name: 'field')]
    public function getUsersByQueryWithAggregationAction(string $query, string $field): Response
    {
        return $this->handleView($this->view($this->userManager->findUserByQueryWithAggregation($query, $field), 200));
    }
}
