<?php

namespace App\Domain\Query\GetFeed;

use JMS\Serializer\Annotation as JMS;

class GetFeedQueryResult
{
    public function __construct(
        /** @JMS\Type("array") */
        private readonly array $tweets,
    ) {
    }

    public function getTweets(): array
    {
        return $this->tweets;
    }

    public function isEmpty(): bool
    {
        return empty($this->tweets);
    }
}
