<?php

namespace App\Service;

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
