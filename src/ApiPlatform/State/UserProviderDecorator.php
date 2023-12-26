<?php

namespace App\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\DTO\UserDTO;
use App\Entity\Subscription;
use App\Entity\User;

class UserProviderDecorator implements ProviderInterface
{
    public function __construct(private readonly ProviderInterface $itemProvider)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $item = $this->itemProvider->provide($operation, $uriVariables, $context);

        if ($item instanceof User) {
            $userDTO = new UserDTO();
            $userDTO->login = $item->getLogin();
            $userDTO->email = $item->getEmail();
            $userDTO->phone = $item->getPhone();
            $userDTO->followers = array_map(
                static function (Subscription $subscription): string {
                    return $subscription->getFollower()->getLogin();
                },
                $item->getSubscriptionFollowers()
            );
            $userDTO->followed = array_map(
                static function (Subscription $subscription): string {
                    return $subscription->getAuthor()->getLogin();
                },
                $item->getFollowed()
            );

            return $userDTO;
        }

        return $item;
    }
}
