<?php

namespace FeedBundle\DTO;

class SendNotificationDTO
{
    private int $userId;

    private string $text;

    private string $preferred;

    public function __construct(int $userId, string $text, string $preferred)
    {
        $this->userId = $userId;
        $this->text = $text;
        $this->preferred = $preferred;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getPreferred(): string
    {
        return $this->preferred;
    }
}
