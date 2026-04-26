<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Dispatch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DispatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dispatch::class);
    }

    public function findByDispatcherNumberAndOrderNumber(string $dispatcherNumber, string $orderNumber): ?Dispatch
    {
        return $this->findOneBy([
            'dispatcherNumber' => $dispatcherNumber,
            'orderNumber' => $orderNumber,
        ]);
    }
}
