<?php

namespace App\Controller\Api\GetTweets\v1;

use App\Entity\Tweet;
use App\Manager\TweetManager;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class Controller extends AbstractFOSRestController
{
    public function __construct(private readonly TweetManager $tweetManager)
    {
    }

    #[Route(path: '/api/v1/tweet', methods: ['GET'])]
    public function getTweetsAction(Request $request): Response
    {
        $perPage = $request->query->get('perPage');
        $page = $request->query->get('page');
        $tweets = $this->tweetManager->getTweets($page ?? 0, $perPage ?? 20);
        $code = empty($tweets) ? 204 : 200;
        $view = $this->view(['tweets' => $tweets], $code);

        return $this->handleView($view);
    }

}
