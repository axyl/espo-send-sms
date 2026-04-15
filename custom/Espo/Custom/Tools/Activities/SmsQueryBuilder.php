<?php

namespace Espo\Custom\Tools\Activities;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Name\Field;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Entities\PhoneNumber;
use Espo\Entities\Sms;
use Espo\Entities\User;
use Espo\Modules\Crm\Entities\Account;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Select;

use RuntimeException;

class SmsQueryBuilder
{
    public function __construct(
        private SelectBuilderFactory $selectBuilderFactory,
        private EntityManager $entityManager,
    ) {}

    /**
     * @param string[] $statusList
     */
    public function buildUnionCompatible(Entity $entity, array $statusList = []): Select
    {
        return $this->build(
            $entity,
            [
                'id',
                ['body', 'name'],
                ['dateSent', 'dateStart'],
                ['null', 'dateEnd'],
                ['null', 'dateStartDate'],
                ['null', 'dateEndDate'],
                ['"Sms"', '_scope'],
                ['createdById', 'assignedUserId'],
                ['createdByName', 'assignedUserName'],
                ['parentType', 'parentType'],
                ['parentId', 'parentId'],
                ['status', 'status'],
                [Field::CREATED_AT, Field::CREATED_AT],
                ['false', 'hasAttachment'],
                ['null', 'fromEmailAddressName'],
                ['null', 'fromString'],
            ],
            $statusList
        );
    }

    /**
     * @param array<int, mixed> $select
     * @param string[] $statusList
     */
    public function build(Entity $entity, array $select, array $statusList = []): Select
    {
        try {
            $builder = $this->selectBuilderFactory
                ->create()
                ->from(Sms::ENTITY_TYPE)
                ->withStrictAccessControl()
                ->buildQueryBuilder()
                ->select($select);
        } catch (BadRequest|Forbidden $e) {
            throw new RuntimeException($e->getMessage());
        }

        if ($statusList !== []) {
            $builder->where(['status' => $statusList]);
        }

        $this->applyEntityFilter($builder, $entity);

        return $builder->build();
    }

    private function applyEntityFilter(object $builder, Entity $entity): void
    {
        $entityType = $entity->getEntityType();
        $id = $entity->getId();

        if ($entity instanceof User) {
            $builder->where(['createdById' => $id]);

            return;
        }

        $where = [];

        if ($entityType === Account::ENTITY_TYPE) {
            $where[] = ['parentId' => $id, 'parentType' => Account::ENTITY_TYPE];
        } else {
            $where[] = [
                'parentId' => $id,
                'parentType' => $entityType,
            ];
        }

        $phoneNumberIdList = $this->getPhoneNumberIdList($entity);

        if ($phoneNumberIdList !== []) {
            $builder
                ->leftJoin('toPhoneNumbers', 'matchedPhoneNumbers')
                ->distinct();

            $where[] = [
                'matchedPhoneNumbers.id' => $phoneNumberIdList,
            ];
        }

        $builder->where(
            count($where) === 1 ?
                $where[0] :
                ['OR' => $where]
        );
    }

    /**
     * @return string[]
     */
    private function getPhoneNumberIdList(Entity $entity): array
    {
        if (!$entity->hasId()) {
            return [];
        }

        $collection = $this->entityManager
            ->getRDBRepository(PhoneNumber::RELATION_ENTITY_PHONE_NUMBER)
            ->sth()
            ->select(['phoneNumberId'])
            ->where([
                'entityType' => $entity->getEntityType(),
                'entityId' => $entity->getId(),
                'deleted' => false,
            ])
            ->find();

        $idList = [];

        foreach ($collection as $item) {
            $phoneNumberId = $item->get('phoneNumberId');

            if (is_string($phoneNumberId) && $phoneNumberId !== '') {
                $idList[] = $phoneNumberId;
            }
        }

        return array_values(array_unique($idList));
    }
}
