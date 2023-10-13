<?php

namespace App\Controller\Api\CreateUser\v5;

use App\Controller\Api\CreateUser\v5\Input\CreateUserDTO;
use App\Controller\Api\CreateUser\v5\Output\UserIsCreatedDTO;

interface CreateUserManagerInterface
{
    public function saveUser(CreateUserDTO $saveUserDTO): UserIsCreatedDTO;
}
