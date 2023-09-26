<?php

namespace App\EventListener;

use App\Exception\DeprecatedApiException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class DeprecatedApiExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof DeprecatedApiException) {
            $response = new Response();
            $response->setContent($exception->getMessage());
            $response->setStatusCode(Response::HTTP_GONE);
            $event->setResponse($response);
        }
    }
}
