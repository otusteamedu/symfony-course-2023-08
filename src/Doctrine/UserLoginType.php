<?php

namespace App\Doctrine;

use App\Domain\ValueObject\UserLogin;

class UserLoginType extends AbstractStringType
{
    public function getName()
    {
        return 'userLogin';
    }

    protected function getConcreteValueObjectType(): string
    {
        return UserLogin::class;
    }
}
