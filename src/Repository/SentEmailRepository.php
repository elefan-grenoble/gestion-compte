<?php

namespace App\Repository;

use App\Entity\SentEmail;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends EntityRepository<SentEmail>
 */
class SentEmailRepository extends EntityRepository
{
    /**
     * Query of the history, newest first, narrowed by the given criteria.
     *
     * The mails sent together (same batch) are one item: the first mail of the
     * batch that matches the criteria stands for the group (see batchSummaries()
     * for the figures of the whole group).
     *
     * Known criteria (all optional): from (\DateTimeInterface), to
     * (\DateTimeInterface, the whole day is included), recipient, subject,
     * source, status.
     *
     * @param array<string, mixed> $criteria
     */
    public function createFilteredQueryBuilder(array $criteria): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e')->orderBy('e.createdAt', 'DESC')->addOrderBy('e.id', 'DESC');

        $alone = array_merge(['e.batchId IS NULL'], $this->conditions($qb, 'e', $criteria));
        $inBatch = array_merge(['h.batchId IS NOT NULL'], $this->conditions($qb, 'h', $criteria));

        $qb->andWhere(sprintf(
            '(%s) OR e.id IN (SELECT MIN(h.id) FROM %s h WHERE %s GROUP BY h.batchId)',
            implode(' AND ', $alone),
            $this->getClassName(),
            implode(' AND ', $inBatch)
        ));

        return $qb;
    }

    /**
     * Mails of a batch, oldest first.
     */
    public function createBatchQueryBuilder(string $batchId): QueryBuilder
    {
        return $this->createQueryBuilder('e')
            ->where('e.batchId = :batch')
            ->setParameter('batch', $batchId)
            ->orderBy('e.id', 'ASC')
        ;
    }

    /**
     * Figures of the batches: number of mails, of failed and pending ones.
     *
     * @param string[] $batchIds
     *
     * @return array<string, array{total: int, sent: int, failed: int, pending: int, queued: int}> indexed by batch id
     */
    public function batchSummaries(array $batchIds): array
    {
        $batchIds = array_values(array_unique(array_filter($batchIds)));
        if (!$batchIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('e')
            ->select('e.batchId AS batchId', 'COUNT(e.id) AS total')
            ->addSelect("SUM(CASE WHEN e.status = 'sent' THEN 1 ELSE 0 END) AS sent")
            ->addSelect("SUM(CASE WHEN e.status = 'failed' THEN 1 ELSE 0 END) AS failed")
            ->addSelect("SUM(CASE WHEN e.status = 'queued' THEN 1 ELSE 0 END) AS queued")
            ->addSelect("SUM(CASE WHEN e.status = 'pending' THEN 1 ELSE 0 END) AS pending")
            ->where('e.batchId IN (:ids)')
            ->setParameter('ids', $batchIds)
            ->groupBy('e.batchId')
            ->getQuery()
            ->getArrayResult()
        ;

        $summaries = [];
        foreach ($rows as $row) {
            $summaries[$row['batchId']] = [
                'total' => (int) $row['total'],
                'sent' => (int) $row['sent'],
                'failed' => (int) $row['failed'],
                'pending' => (int) $row['pending'],
                'queued' => (int) $row['queued'],
            ];
        }

        return $summaries;
    }

    /**
     * DQL conditions of the criteria on the given alias (the parameters are
     * set on the query).
     *
     * @param array<string, mixed> $criteria
     *
     * @return string[]
     */
    private function conditions(QueryBuilder $qb, string $alias, array $criteria): array
    {
        $conditions = [];
        if (!empty($criteria['from'])) {
            $from = \DateTime::createFromFormat('Y-m-d H:i:s', $criteria['from']->format('Y-m-d') . ' 00:00:00');
            $conditions[] = $alias . '.createdAt >= :from';
            $qb->setParameter('from', $from);
        }
        if (!empty($criteria['to'])) {
            $to = \DateTime::createFromFormat('Y-m-d H:i:s', $criteria['to']->format('Y-m-d') . ' 23:59:59');
            $conditions[] = $alias . '.createdAt <= :to';
            $qb->setParameter('to', $to);
        }
        if (!empty($criteria['recipient'])) {
            $conditions[] = $alias . '.recipients LIKE :recipient';
            $qb->setParameter('recipient', '%' . addcslashes(trim($criteria['recipient']), '%_') . '%');
        }
        if (!empty($criteria['subject'])) {
            $conditions[] = $alias . '.subject LIKE :subject';
            $qb->setParameter('subject', '%' . addcslashes(trim($criteria['subject']), '%_') . '%');
        }
        if (!empty($criteria['source'])) {
            $conditions[] = $alias . '.source = :source';
            $qb->setParameter('source', $criteria['source']);
        }
        if (!empty($criteria['status'])) {
            // "Waiting" covers the queued mails and the ones being handed to the server.
            $statuses = SentEmail::STATUS_QUEUED === $criteria['status'] ? [SentEmail::STATUS_QUEUED, SentEmail::STATUS_PENDING] : [$criteria['status']];
            $conditions[] = $alias . '.status IN (:statuses)';
            $qb->setParameter('statuses', $statuses);
        }

        return $conditions;
    }
}
