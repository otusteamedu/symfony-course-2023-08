<?php

namespace App\Controller\Api\AddFollowers\v1;

use App\Manager\UserManager;
use App\Service\AsyncService;
use App\Service\SubscriptionService;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\RequestParam;
use JsonException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class Controller extends AbstractFOSRestController
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly UserManager $userManager,
        private readonly AsyncService $asyncService,
    )
    {
    }

    /**
     * @throws JsonException
     */
    #[Route(path: '/api/v1/add-followers', methods: ['POST'])]
    #[RequestParam(name: 'userId', requirements: '\d+')]
    #[RequestParam(name: 'followersLogin')]
    #[RequestParam(name: 'count', requirements: '\d+')]
    #[RequestParam(name: 'async', requirements: '0|1')]
    public function addFollowersAction(int $userId, string $followersLogin, int $count, int $async): Response
    {
        $user = $this->userManager->findUser($userId);
        if ($user !== null) {
            if ($async === 0) {
                $createdFollowers = $this->subscriptionService->addFollowers($user, $followersLogin, $count);
                $view = $this->view(['created' => $createdFollowers], 200);
            } else {
                $message = $this->subscriptionService->getFollowersMessages($user, $followersLogin, $count);
                $result = $this->asyncService->publishMultipleToExchange(AsyncService::ADD_FOLLOWER, $message);
                $view = $this->view(['success' => $result], $result ? 200 : 500);
            }
        } else {
            $view = $this->view(['success' => false], 404);
        }

        return $this->handleView($view);
    }
}
