<?php

namespace App\DTO;

class SendNotificationAsyncDTO
{
    private int $userId;

    private string $text;

    public function __construct(int $userId, string $text)
    {
        $this->userId = $userId;
        $this->text = $text;
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
