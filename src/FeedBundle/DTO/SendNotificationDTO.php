<?php

namespace FeedBundle\DTO;

use JsonException;
use Symfony\Component\Serializer\Annotation\Ignore;

class SendNotificationDTO
{
    #[Ignore]
    private array $payload;

    private int $userId;

    private string $text;

    public function __construct(int $userId, string $text)
    {
        $this->payload = ['userId' => $userId, 'text' => $text];
        $this->userId = $userId;
        $this->text = $text;
    }

    /**
     * @throws JsonException
     */
    public function toAMQPMessage(): string
    {
        return json_encode($this->payload, JSON_THROW_ON_ERROR);
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getText(): string
    {
        return $this->text;
    }
}
