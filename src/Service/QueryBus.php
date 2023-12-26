<?php

namespace App\Service;

use App\Domain\Query\QueryInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class QueryBus implements QueryBusInterface
{
    public function __construct(
        private readonly MessageBusInterface $baseQueryBus
    ) {
    }

    /**
     * @return mixed
     */
    public function query(QueryInterface $query)
    {
        $envelope = $this->baseQueryBus->dispatch($query);
        /** @var HandledStamp|null $handledStamp */
        $handledStamp = $envelope->last(HandledStamp::class);

        return $handledStamp?->getResult();
    }
}
