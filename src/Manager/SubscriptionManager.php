<?php

namespace App\Manager;

use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class SubscriptionManager
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function addSubscription(User $author, User $follower): void
    {
        $subscription = new Subscription();
        $subscription->setAuthor($author);
        $subscription->setFollower($follower);
        $subscription->setCreatedAt();
        $subscription->setUpdatedAt();
        $author->addSubscriptionFollower($subscription);
        $follower->addSubscriptionAuthor($subscription);
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();
    }

    /**
     * @return Subscription[]
     */
    public function findAllByAuthor(User $author): array
    {
        $subscriptionRepository = $this->entityManager->getRepository(Subscription::class);
        return $subscriptionRepository->findBy(['author' => $author]) ?? [];
    }
}
