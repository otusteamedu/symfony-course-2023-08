<?php

namespace App\Controller\Api\CreateUser\v5;

use App\Controller\Api\CreateUser\v5\Input\CreateUserDTO;
use App\Controller\Api\CreateUser\v5\Output\UserIsCreatedDTO;
use App\Domain\ValueObject\UserLogin;
use App\Entity\User;
use App\Event\CreateUserEvent;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use StatsdBundle\Client\StatsdAPIClient;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CreateUserManager implements CreateUserManagerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface $serializer,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly StatsdAPIClient $statsdAPIClient,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function saveUser(CreateUserDTO $saveUserDTO): UserIsCreatedDTO
    {
        $this->statsdAPIClient->increment('save_user_v5_attempt');
        $user = new User();
        $user->setLogin(UserLogin::fromString($saveUserDTO->login));
        $user->setPassword($this->userPasswordHasher->hashPassword($user, $saveUserDTO->password));
        $user->setRoles($saveUserDTO->roles);
        $user->setAge($saveUserDTO->age);
        $user->setIsActive($saveUserDTO->isActive);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->eventDispatcher->dispatch(new CreateUserEvent($user->getLogin()->getValue()));

        $result = new UserIsCreatedDTO();
        $context = (new SerializationContext())->setGroups(['video-user-info', 'user-id-list']);
        $result->loadFromJsonString($this->serializer->serialize($user, 'json', $context));

        return $result;
    }
}
