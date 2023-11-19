<?php

namespace App\Client;

use GuzzleHttp\Client;

class FeedClient
{
    public function __construct(
        private readonly Client $client,
        private readonly string $baseUrl
    ) {
    }

    public function getFeed(int $userId, int $count): array
    {
        $response = $this->client->get("{$this->baseUrl}/server-api/v1/get-feed", [
            'query' => [
                'userId' => $userId,
                'count' => $count,
            ],
        ]);
        $responseData = json_decode($response->getBody(), true);

        return $responseData['tweets'];
    }
}
