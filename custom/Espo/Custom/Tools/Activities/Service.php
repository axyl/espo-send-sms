<?php

namespace Espo\Custom\Tools\Activities;

use Espo\Core\Acl;
use Espo\Core\FieldProcessing\ListLoadProcessor;
use Espo\Core\Record\ServiceContainer as RecordServiceContainer;
use Espo\Core\Select\Helpers\RelationQueryHelper;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Core\ServiceFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Sms;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Select;

/**
 * Extends the core Activities Service to properly support the Sms entity
 * in activity/history UNION queries.
 *
 * The core base query hardcodes `name` and does not map `dateSent` to the
 * common `dateStart` alias. Sms does not have those base activity fields,
 * so it needs a dedicated UNION-compatible projection.
 *
 * This class is still used via DI binding in `Espo\Custom\Binding`. Keep that
 * binding in place unless the generated SQL for the full Activities pipeline
 * has been revalidated; prior consolidation attempts caused HTTP 500 responses
 * with SQLSTATE[21000] cardinality violations.
 */
class Service extends \Espo\Modules\Crm\Tools\Activities\Service
{
    public function __construct(
        ListLoadProcessor $listLoadProcessor,
        RecordServiceContainer $recordServiceContainer,
        SelectBuilderFactory $selectBuilderFactory,
        Config $config,
        Metadata $metadata,
        Acl $acl,
        ServiceFactory $serviceFactory,
        EntityManager $entityManager,
        User $user,
        RelationQueryHelper $relationQueryHelper,
        private SmsQueryBuilder $smsQueryBuilder,
    ) {
        parent::__construct(
            $listLoadProcessor,
            $recordServiceContainer,
            $selectBuilderFactory,
            $config,
            $metadata,
            $acl,
            $serviceFactory,
            $entityManager,
            $user,
            $relationQueryHelper,
        );
    }

    protected function getActivitiesBaseQuery(Entity $entity, string $scope, array $statusList = []): Select
    {
        if ($scope === Sms::ENTITY_TYPE) {
            return $this->getActivitiesSmsQuery($entity, $statusList);
        }

        return parent::getActivitiesBaseQuery($entity, $scope, $statusList);
    }

    /**
     * Build a UNION-compatible 16-column SELECT for the Sms entity.
     *
     * Column order must exactly match the other entity-type queries:
     *   id, name, dateStart, dateEnd, dateStartDate, dateEndDate, _scope,
     *   assignedUserId, assignedUserName, parentType, parentId, status,
     *   createdAt, hasAttachment, fromEmailAddressName, fromString
     *
     * `body` is aliased as `name` and `dateSent` as `dateStart` so the result
     * matches the core activity/history row contract.
     *
     * @param string[] $statusList
     */
    protected function getActivitiesSmsQuery(Entity $entity, array $statusList = []): Select
    {
        return $this->smsQueryBuilder->buildUnionCompatible($entity, $statusList);
    }
}
