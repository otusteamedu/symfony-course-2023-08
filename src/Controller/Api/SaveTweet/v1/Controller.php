<?php

namespace App\Controller\Api\SaveTweet\v1;

use App\Controller\Common\ErrorResponseTrait;
use App\Service\AsyncService;
use App\Manager\TweetManager;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations\RequestParam;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class Controller extends AbstractFOSRestController
{
    use ErrorResponseTrait;

    public function __construct(private TweetManager $tweetManager, private AsyncService $asyncService)
    {
    }

    #[Route(path: '/api/v1/tweet', methods: ['POST'])]
    #[RequestParam(name: 'authorId', requirements: '\d+')]
    #[RequestParam(name: 'text')]
    #[RequestParam(name: 'async', requirements: '0|1', nullable: true)]
    public function saveTweetAction(int $authorId, string $text, ?int $async): Response
    {
        $tweet = $this->tweetManager->saveTweet($authorId, $text);
        $success = $tweet !== null;
        if ($success) {
            if ($async === 1) {
                $this->asyncService->publishToExchange(AsyncService::PUBLISH_TWEET, $tweet->toAMPQMessage());
            } else {
                return $this->handleView(View::create(['message' => 'Sync post is no longer supported'], 400));
            }
        }
        $code = $success ? 200 : 400;

        return $this->handleView($this->view(['success' => $success], $code));
    }
}
