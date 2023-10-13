<?php

namespace App\Controller\Api\CreateUser\v5;

use App\Controller\Api\CreateUser\v5\Input\CreateUserDTO;
use App\Controller\Api\CreateUser\v5\Output\UserIsCreatedDTO;
use Psr\Log\LoggerInterface;

class CreateUserManagerLoggerDecorator implements CreateUserManagerInterface
{
    public function __construct(
        private readonly CreateUserManagerInterface $manager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function saveUser(CreateUserDTO $saveUserDTO): UserIsCreatedDTO
    {
        $this->logger->info('Creating new user');

        try {
            $result = $this->manager->saveUser($saveUserDTO);
        } catch (\Throwable $e) {
            $this->logger->error('Creation error');
            throw $e;
        }

        $this->logger->info('New user created');

        return $result;
    }
}
