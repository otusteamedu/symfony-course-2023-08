<?php

namespace App\Controller\Api\CreateUser\v5;

use App\Client\StatsdAPIClient;
use App\Controller\Api\CreateUser\v5\Input\CreateUserDTO;
use App\Controller\Api\CreateUser\v5\Output\UserIsCreatedDTO;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CreateUserManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface $serializer,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly LoggerInterface $logger,
        private readonly StatsdAPIClient $statsdAPIClient,
    ) {
    }

    public function saveUser(CreateUserDTO $saveUserDTO): UserIsCreatedDTO
    {
        $this->statsdAPIClient->increment('save_user_v5_attempt');
        $this->logger->debug('This is debug message');
        $this->logger->info('This is info message');
        $this->logger->notice('This is notice message');
        $this->logger->warning('This is warning message');
        $this->logger->error('This is error message');
        $this->logger->critical('This is critical message');
        $this->logger->alert('This is alert message');
        $this->logger->emergency('This is emergency message');
        $user = new User();
        $user->setLogin($saveUserDTO->login);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, $saveUserDTO->password));
        $user->setRoles($saveUserDTO->roles);
        $user->setAge($saveUserDTO->age);
        $user->setIsActive($saveUserDTO->isActive);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $result = new UserIsCreatedDTO();
        $context = (new SerializationContext())->setGroups(['video-user-info', 'user-id-list']);
        $result->loadFromJsonString($this->serializer->serialize($user, 'json', $context));

        return $result;
    }
}
