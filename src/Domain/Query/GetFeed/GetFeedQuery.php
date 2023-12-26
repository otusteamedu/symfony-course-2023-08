<?php

namespace App\Domain\Query\GetFeed;

use App\Domain\Query\QueryInterface;

/**
 * @implements QueryInterface<GetFeedQueryResult>
 */
class GetFeedQuery implements QueryInterface
{
    public function __construct(
        private readonly int $userId,
        private readonly int $count,
    ) {
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getCount(): int
    {
        return $this->count;
    }
}
