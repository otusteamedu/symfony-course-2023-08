<?php

namespace App\Controller\Api\AddFollowers\v1;

use App\Manager\UserManager;
use App\Service\SubscriptionService;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\RequestParam;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class Controller extends AbstractFOSRestController
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly UserManager $userManager
    )
    {
    }

    #[Route(path: '/api/v1/add-followers', methods: ['POST'])]
    #[RequestParam(name: 'userId', requirements: '\d+')]
    #[RequestParam(name: 'followersLogin')]
    #[RequestParam(name: 'count', requirements: '\d+')]
    public function addFollowersAction(int $userId, string $followersLogin, int $count): Response
    {
        $user = $this->userManager->findUser($userId);
        if ($user !== null) {
            $createdFollowers = $this->subscriptionService->addFollowers($user, $followersLogin, $count);
            $view = $this->view(['created' => $createdFollowers], 200);
        } else {
            $view = $this->view(['success' => false], 404);
        }

        return $this->handleView($view);
    }
}
