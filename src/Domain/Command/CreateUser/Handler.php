<?php

namespace App\Domain\Command\CreateUser;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class Handler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(CreateUserCommand $command): int
    {
        $user = new User();
        $user->setLogin($command->getLogin());
        $user->setPassword($command->getPassword());
        $user->setRoles($command->getRoles());
        $user->setAge($command->getAge());
        $user->setIsActive($command->isActive());
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user->getId();
    }
}
