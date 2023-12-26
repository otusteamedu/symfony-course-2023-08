<?php

namespace App\ApiPlatform\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Tweet;
use App\Service\AsyncService;

class AsyncMessageTweetProcessorDecorator implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $persistProcessor,
        private readonly ProcessorInterface $removeProcessor,
        private readonly AsyncService $asyncService,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if ($operation instanceof DeleteOperationInterface) {
            return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        }

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        if ($data instanceof Tweet) {
            $this->asyncService->publishToExchange(AsyncService::PUBLISH_TWEET, $result->toAMPQMessage());
        }
        return $result;
    }
}
