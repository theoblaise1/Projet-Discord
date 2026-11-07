<?php

namespace App\Repository;

use App\Entity\Channel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Channel>
 */
class ChannelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Channel::class);
    }

    /**
     * Tous les salons, dans leur ordre de création (comme sur Discord,
     * un nouveau salon apparaît en bas de la liste).
     *
     * @return list<Channel>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('o')
            ->join('c.owner', 'o')
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
