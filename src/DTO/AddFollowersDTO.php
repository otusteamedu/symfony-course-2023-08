<?php

namespace App\DTO;

use JsonException;
use Symfony\Component\Serializer\Annotation\Ignore;

class AddFollowersDTO
{
    #[Ignore]
    private array $payload;

    private int $userId;

    private string $followerLogin;

    private int $count;

    public function __construct(int $userId, string $followerLogin, int $count)
    {
        $this->payload = ['userId' => $userId, 'followerLogin' => $followerLogin, 'count' => $count];
        $this->userId = $userId;
        $this->followerLogin = $followerLogin;
        $this->count = $count;
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

    public function getFollowerLogin(): string
    {
        return $this->followerLogin;
    }

    public function getCount(): int
    {
        return $this->count;
    }
}
