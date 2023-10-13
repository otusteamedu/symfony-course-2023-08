<?php

namespace App\EventSubscriber;

use App\Event\CreateUserEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CreateUserEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CreateUserEvent::class => 'logCreateUser'
        ];
    }

    public function logCreateUser(CreateUserEvent $event): void
    {
        $this->logger->info('User created with login: ' . $event->getLogin());
    }
}
