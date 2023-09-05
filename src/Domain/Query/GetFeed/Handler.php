<?php

namespace App\Domain\Query\GetFeed;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use FeedBundle\Entity\Feed;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class Handler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(GetFeedQuery $query): GetFeedQueryResult
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $reader = $userRepository->find($query->getUserId());
        if (!($reader instanceof User)) {
            return new GetFeedQueryResult([]);
        }

        $feedRepository = $this->entityManager->getRepository(Feed::class);
        $feed = $feedRepository->findOneBy(['readerId' => $reader]);

        if ($feed === null) {
            $tweets = [];
        } else {
            $tweets = array_slice($feed->getTweets(), -$query->getCount());
        }

        return new GetFeedQueryResult($tweets);
    }
}
