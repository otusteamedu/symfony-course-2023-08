<?php

namespace App\Facade;

use FeedBundle\Service\FeedService;

class FeedFacade
{

    public function __construct(private readonly FeedService $feedService)
    {
    }

    public function getFeed(int $userId, int $count): array
    {
        return $this->feedService->getFeed($userId, $count);
    }
}
