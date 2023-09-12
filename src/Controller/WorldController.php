<?php

namespace App\Controller;

use App\Entity\User;
use App\Manager\UserManager;
use App\Service\UserBuilderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class WorldController extends AbstractController
{
    public function __construct(private readonly UserManager        $userManager,
                                private readonly UserBuilderService $userBuilderService
    )
    {
    }

    public function hello(): Response
    {
        $user = $this->userBuilderService->createUserWithTweets(
            'Charles Dickens',
            ['Oliver Twist', 'The Christmas Carol']
        );
        $userData = $this->userManager->findUserWithTweetsWithQueryBuilder($user->getId());

        return $this->json($userData->toArray());
    }
}
