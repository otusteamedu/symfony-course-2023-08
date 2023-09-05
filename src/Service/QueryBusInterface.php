<?php

namespace App\Service;

use App\Domain\Query\QueryInterface;

interface QueryBusInterface
{
    /**
     * @template T
     *
     * @param QueryInterface<T> $query
     *
     * @return T
     */
    public function query(QueryInterface $query);
}
