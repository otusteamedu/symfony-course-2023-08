<?php

namespace App\Entity;

use JetBrains\PhpStorm\ArrayShape;

class User
{
    public function __construct(
        private string $firstName,
        private string $middleName,
        private string $lastName,
        private string $phone,
    ) {
    }

    public function getMiddleName(): string
    {
        return $this->middleName;
    }

    public function setMiddleName(string $middleName): void
    {
        $this->middleName = $middleName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): void
    {
        $this->phone = $phone;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    #[ArrayShape(['firstName' => 'string', 'middleName' => 'string', 'lastName' => 'string', 'phone' => 'string'])]
    public function toArray(): array
    {
        return [
            'firstName' => $this->firstName,
            'middleName' => $this->middleName,
            'lastName' => $this->lastName,
            'phone' => $this->phone,
        ];
    }
}