<?php

namespace App\Manager;

use App\Entity\SmsNotification;
use Doctrine\ORM\EntityManagerInterface;

final class SmsNotificationManager
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function saveSmsNotification(string $phone, string $text): void {
        $smsNotification = new SmsNotification();
        $smsNotification->setPhone($phone);
        $smsNotification->setText($text);
        $this->entityManager->persist($smsNotification);
        $this->entityManager->flush();
    }
}
