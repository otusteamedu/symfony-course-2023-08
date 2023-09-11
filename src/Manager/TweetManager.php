<?php

namespace App\Manager;

use App\Entity\Tweet;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class TweetManager
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function postTweet(User $author, string $text): void
    {
        $tweet = new Tweet();
        $tweet->setAuthor($author);
        $tweet->setText($text);
        $tweet->setCreatedAt();
        $tweet->setUpdatedAt();
        $author->addTweet($tweet);
        $this->entityManager->persist($tweet);
        $this->entityManager->flush();
    }
}
