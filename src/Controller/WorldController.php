<?php

namespace App\Controller;

use App\Entity\User;
use App\Manager\UserManager;
use App\Service\UserBuilderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class WorldController extends AbstractController
{
    public function __construct(private readonly UserManager $userManager)
    {
    }

    public function hello(): Response
    {
        $users = $this->userManager->findUsersByCriteria('Ivan Ivanov');

        return $this->json(array_map(static fn(User $user) => $user->toArray(), $users));
    }
}
