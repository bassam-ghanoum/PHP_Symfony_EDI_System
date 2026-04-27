<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Exception\DatabaseException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function findByOrderNumber(string $orderNumber): ?Order
    {
        try {
            return $this->findOneBy(['orderNumber' => $orderNumber]);
        } catch (\Throwable $e) {
            throw DatabaseException::fromThrowable($e, 'finding order by order number');
        }
    }
}
