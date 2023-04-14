<?php

namespace App\Consumer\AddFollowers;

use App\Consumer\AddFollowers\Input\Message;
use App\Entity\User;
use App\Service\SubscriptionService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use JsonException;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;

class Consumer implements ConsumerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly SubscriptionService $subscriptionService
    )
    {
    }

    public function execute(AMQPMessage $msg): int
    {
        try {
            $message = Message::createFromQueue($msg->getBody());
            $errors = $this->validator->validate($message);
            if ($errors->count() > 0) {
                return $this->reject((string)$errors);
            }
        } catch (JsonException $e) {
            return $this->reject($e->getMessage());
        }

        if ($message->getFollowerLogin() === 'multi_follower_error2 #11') {
            throw new Exception('Planned error');
        }

        try {
            $userRepository = $this->entityManager->getRepository(User::class);
            $user = $userRepository->find($message->getUserId());
            if (!($user instanceof User)) {
                return $this->reject(sprintf('User ID %s was not found', $message->getUserId()));
            }

            $this->subscriptionService->addFollowers($user, $message->getFollowerLogin(), $message->getCount());
            sleep(1);
        } catch (Throwable $e) {
            return $this->reject($e->getMessage());
        } finally {
            $this->entityManager->clear();
            $this->entityManager->getConnection()->close();
        }

        return self::MSG_ACK;
    }

    private function reject(string $error): int
    {
        echo "Incorrect message: $error";

        return self::MSG_REJECT;
    }
}
