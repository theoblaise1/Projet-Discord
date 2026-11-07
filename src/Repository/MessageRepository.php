<?php

namespace App\Repository;

use App\Entity\Channel;
use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Les messages sont triés par identifiant : il est strictement croissant,
 * ce qui permet de charger "les messages après le n°X" (rafraîchissement)
 * ou "avant le n°X" (historique) sans ambiguïté.
 *
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * Les $limit derniers messages du salon, du plus ancien au plus récent.
     *
     * @return list<Message>
     */
    public function findLatestInChannel(Channel $channel, int $limit): array
    {
        $messages = $this->createChannelQueryBuilder($channel)
            ->orderBy('m.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_reverse($messages);
    }

    /**
     * Les $limit messages qui précèdent le message n°$beforeId, du plus ancien au plus récent.
     *
     * @return list<Message>
     */
    public function findBeforeInChannel(Channel $channel, int $beforeId, int $limit): array
    {
        $messages = $this->createChannelQueryBuilder($channel)
            ->andWhere('m.id < :beforeId')
            ->setParameter('beforeId', $beforeId)
            ->orderBy('m.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_reverse($messages);
    }

    /**
     * Les messages publiés après le message n°$afterId, du plus ancien au plus récent.
     *
     * @return list<Message>
     */
    public function findAfterInChannel(Channel $channel, int $afterId, int $limit): array
    {
        return $this->createChannelQueryBuilder($channel)
            ->andWhere('m.id > :afterId')
            ->setParameter('afterId', $afterId)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function hasMessagesBefore(Channel $channel, int $messageId): bool
    {
        $id = $this->createQueryBuilder('m')
            ->select('m.id')
            ->where('m.channel = :channel')
            ->andWhere('m.id < :messageId')
            ->setParameter('channel', $channel)
            ->setParameter('messageId', $messageId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null !== $id;
    }

    /**
     * Nombre de messages par salon.
     *
     * @return array<int, int> identifiant du salon => nombre de messages
     */
    public function countByChannel(): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.channel) AS channelId', 'COUNT(m.id) AS total')
            ->groupBy('m.channel')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['channelId']] = (int) $row['total'];
        }

        return $counts;
    }

    private function createChannelQueryBuilder(Channel $channel): QueryBuilder
    {
        // On charge l'auteur en même temps que le message (évite le problème N+1).
        return $this->createQueryBuilder('m')
            ->addSelect('a')
            ->join('m.author', 'a')
            ->where('m.channel = :channel')
            ->setParameter('channel', $channel);
    }
}
