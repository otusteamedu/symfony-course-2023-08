<?php

namespace App\Controller\Common;

class Error
{
    public function __construct(
        public readonly string $propertyPath,
        public readonly string $message
    ) {
    }
}
