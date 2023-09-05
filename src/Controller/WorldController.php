<?php

namespace App\Controller;

use App\Manager\UserManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class WorldController extends AbstractController
{
    public function __construct(private readonly UserManager $userManager)
    {
    }
    public function hello(): Response
    {
        return $this->render(
            'user-vue.twig',
            [
                'users' => json_encode($this->userManager->getUsersListVue(), JSON_THROW_ON_ERROR)
            ]
        );
    }
}
