<?php

namespace App\Service;

use App\DTO\ManageUserDTO;
use App\Entity\User;
use App\Manager\SubscriptionManager;
use App\Manager\UserManager;

class SubscriptionService
{
    public function __construct(
        private readonly UserManager $userManager,
        private readonly SubscriptionManager $subscriptionManager
    )
    {
    }

    public function subscribe(int $authorId, int $followerId): bool
    {
        $author = $this->userManager->findUser($authorId);
        if (!($author instanceof User)) {
            return false;
        }
        $follower = $this->userManager->findUser($followerId);
        if (!($follower instanceof User)) {
            return false;
        }

        $this->subscriptionManager->addSubscription($author, $follower);

        return true;
    }

    public function addFollowers(User $user, string $followerLogin, int $count): int
    {
        $createdFollowers = 0;
        for ($i = 0; $i < $count; $i++) {
            $followerId = $this->userManager->saveUserFromDTO(
                new User(),
                new ManageUserDTO("{$followerLogin}_#$i", $followerLogin, $i, true)
            );
            if ($followerId !== null) {
                $this->subscribe($user->getId(), $followerId);
                $createdFollowers++;
            }
        }

        return $createdFollowers;
    }
}
