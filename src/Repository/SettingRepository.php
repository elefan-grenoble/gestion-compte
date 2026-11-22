<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\ORM\EntityRepository;

class SettingRepository extends EntityRepository
{
    /**
     * @return array<string, ?string>
     */
    public function findAllAsKeyValue(): array
    {
        $rows = $this->createQueryBuilder('setting')
            ->getQuery()
            ->getArrayResult()
        ;

        $result = [];
        foreach ($rows as $row) {
            $result[$row['key']] = json_decode($row['value']);
        }

        return $result;
    }
}
