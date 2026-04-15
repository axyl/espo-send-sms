<?php

namespace Espo\Custom\Tools\Activities;

use Espo\Core\Name\Field;
use Espo\Core\Record\Collection as RecordCollection;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Sms;
use Espo\Modules\Crm\Tools\Activities\FetchParams;
use Espo\Modules\Crm\Tools\Activities\Service as CoreService;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;

use PDO;

class MergedService
{
    public function __construct(
        private CoreService $coreService,
        private Config $config,
        private Metadata $metadata,
        private EntityManager $entityManager,
        private SmsQueryBuilder $smsQueryBuilder,
        private SmsRowNormalizer $smsRowNormalizer,
    ) {}

    public function getActivities(string $scope, string $id, FetchParams $params): RecordCollection
    {
        return $this->get($scope, $id, $params, false);
    }

    public function getHistory(string $scope, string $id, FetchParams $params): RecordCollection
    {
        return $this->get($scope, $id, $params, true);
    }

    private function get(string $scope, string $id, FetchParams $params, bool $isHistory): RecordCollection
    {
        $targetEntityType = $params->getEntityType();

        if ($targetEntityType && $targetEntityType !== Sms::ENTITY_TYPE) {
            return $isHistory ?
                $this->coreService->getHistory($scope, $id, $params) :
                $this->coreService->getActivities($scope, $id, $params);
        }

        $entity = $this->entityManager->getEntityById($scope, $id);

        if (!$entity) {
            return RecordCollection::create(new EntityCollection(), 0);
        }

        $limit = $params->getMaxSize() ?? 20;
        $offset = $params->getOffset() ?? 0;
        $fetchSize = $offset + $limit;

        $configKey = $isHistory ? 'historyEntityList' : 'activitiesEntityList';
        $originalList = $this->config->get($configKey) ?? [];
        $filteredList = array_values(array_filter($originalList, fn ($item) => $item !== Sms::ENTITY_TYPE));

        try {
            // The working path fetches the non-Sms portion through the core
            // service with Sms temporarily removed, then merges Sms separately.
            // This avoids the mixed UNION generation that previously produced
            // cardinality errors when Sms was folded into the core query path.
            $this->config->set($configKey, $filteredList, true);

            $coreParams = new FetchParams($fetchSize, 0, null);
            $coreResult = $isHistory ?
                $this->coreService->getHistory($scope, $id, $coreParams) :
                $this->coreService->getActivities($scope, $id, $coreParams);
        } finally {
            $this->config->set($configKey, $originalList, true);
        }

        $smsResult = $this->getSms($entity, $fetchSize, $isHistory);

        $entityList = [];

        foreach ($coreResult->getCollection() as $item) {
            $entityList[] = $item;
        }

        foreach ($smsResult->getCollection() as $item) {
            $entityList[] = $item;
        }

        usort($entityList, [$this, 'compareEntities']);

        $pagedList = array_slice($entityList, $offset, $limit);

        $total = $coreResult->getTotal();
        $smsTotal = $smsResult->getTotal();

        if ($total !== null && $total >= 0 && $smsTotal !== null && $smsTotal >= 0) {
            $total += $smsTotal;
        } else {
            $total = null;
        }

        return RecordCollection::create(new EntityCollection($pagedList), $total);
    }

    private function getSms(Entity $entity, int $fetchSize, bool $isHistory): RecordCollection
    {
        $statusList = $this->metadata->get(['scopes', Sms::ENTITY_TYPE, $isHistory ? 'historyStatusList' : 'activityStatusList']) ??
            ($isHistory ? [Sms::STATUS_SENT, Sms::STATUS_ARCHIVED] : []);

        if (!$isHistory && $statusList === []) {
            return RecordCollection::create(new EntityCollection(), 0);
        }

        $baseQuery = $this->smsQueryBuilder->build(
            $entity,
            [
                'id',
                'body',
                'dateSent',
                'status',
                Field::CREATED_AT,
                'createdById',
                'createdByName',
                'parentType',
                'parentId',
            ],
            $statusList
        );

        $countQuery = $this->entityManager
            ->getQueryBuilder()
            ->select()
            ->fromQuery($baseQuery, 'c')
            ->select('COUNT:(c.id)', 'count')
            ->build();

        $countRow = $this->entityManager
            ->getQueryExecutor()
            ->execute($countQuery)
            ->fetch(PDO::FETCH_ASSOC) ?: [];

        $query = $this->entityManager
            ->getQueryBuilder()
            ->select()
            ->clone($baseQuery)
            ->order('dateSent', 'DESC')
            ->order(Field::CREATED_AT, 'DESC')
            ->limit(0, $fetchSize)
            ->build();

        $rowList = $this->entityManager
            ->getQueryExecutor()
            ->execute($query)
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $collection = new EntityCollection();

        foreach ($rowList as $row) {
            $sms = $this->entityManager->getNewEntity(Sms::ENTITY_TYPE);
            $row = $this->smsRowNormalizer->normalize($row);

            $sms->setMultiple($row);
            $sms->setAsFetched();

            $collection->append($sms);
        }

        return RecordCollection::create($collection, (int) ($countRow['count'] ?? 0));
    }

    private function compareEntities(Entity $a, Entity $b): int
    {
        $aDateStart = (string) $this->getSortDate($a);
        $bDateStart = (string) $this->getSortDate($b);

        if ($aDateStart !== $bDateStart) {
            return strcmp($bDateStart, $aDateStart);
        }

        $aCreatedAt = (string) ($a->get(Field::CREATED_AT) ?? '');
        $bCreatedAt = (string) ($b->get(Field::CREATED_AT) ?? '');

        return strcmp($bCreatedAt, $aCreatedAt);
    }

    private function getSortDate(Entity $entity): ?string
    {
        if ($entity->getEntityType() === Sms::ENTITY_TYPE) {
            return $entity->get('dateSent') ?? $entity->get(Field::CREATED_AT);
        }

        return $entity->get('dateStart') ?? $entity->get(Field::CREATED_AT);
    }
}
