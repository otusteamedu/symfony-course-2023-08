<?php

namespace App\Controller\Api\GetFeed\v1;

use App\Domain\Query\GetFeed\GetFeedQuery;
use App\Domain\Query\GetFeed\GetFeedQueryResult;
use App\Service\QueryBusInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Response;

class Controller extends AbstractFOSRestController
{
    /** @var int */
    private const DEFAULT_FEED_SIZE = 20;

    /**
     * @param QueryBusInterface<GetFeedQueryResult> $queryBus
     */
    public function __construct(
        private readonly QueryBusInterface $queryBus
    )
    {
    }

    #[Rest\Get('/api/v1/get-feed')]
    #[Rest\QueryParam(name: 'userId', requirements: '\d+')]
    #[Rest\QueryParam(name: 'count', requirements: '\d+', nullable: true)]
    public function getFeedAction(int $userId, ?int $count = null): View
    {
        $count = $count ?? self::DEFAULT_FEED_SIZE;
        $result = $this->queryBus->query(new GetFeedQuery($userId, $count));

        return View::create($result, $result->isEmpty() ? Response::HTTP_NO_CONTENT : Response::HTTP_OK);
    }
}
