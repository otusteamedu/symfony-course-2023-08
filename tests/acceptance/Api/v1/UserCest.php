<?php

namespace AcceptanceTests\Api\v1;

use App\Tests\AcceptanceTester;
use Codeception\Util\HttpCode;

class UserCest
{
    public function testAddUserActionForAdmin(AcceptanceTester $I): void
    {
        $I->amAdmin();
        $I->sendPost('/api/v4/users', $this->getAddUserParams());
        $I->canSeeResponseCodeIs(HttpCode::OK);
        $I->canSeeResponseMatchesJsonType(['id' => 'integer:>0']);
    }

    public function testAddUserActionForUser(AcceptanceTester $I): void
    {
        $I->amUser();
        $I->sendPost('/api/v4/users', $this->getAddUserParams());
        $I->canSeeResponseContains('Access Denied.');
        $I->canSeeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    private function getAddUserParams(): array
    {
        return [
            'login' => 'other_user',
            'password' => 'other_password',
            'roles' => '["ROLE_USER"]',
            'age' => 23,
            'isActive' => 'true',
        ];
    }
}
