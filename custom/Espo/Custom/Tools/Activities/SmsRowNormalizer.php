<?php

namespace Espo\Custom\Tools\Activities;

use Espo\Entities\Sms;

class SmsRowNormalizer
{
    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function normalize(array $row): array
    {
        if (($row['_scope'] ?? Sms::ENTITY_TYPE) !== Sms::ENTITY_TYPE) {
            return $row;
        }

        $row['name'] = $row['name'] ?? $row['body'] ?? null;
        $row['dateStart'] = $row['dateStart'] ?? $row['dateSent'] ?? null;
        $row['assignedUserId'] = $row['assignedUserId'] ?? $row['createdById'] ?? null;
        $row['assignedUserName'] = $row['assignedUserName'] ?? $row['createdByName'] ?? null;

        return $row;
    }

    /**
     * @param array<int, array<string, mixed>|object> $list
     * @return array<int, object>
     */
    public function normalizeList(array $list): array
    {
        return array_map(
            fn ($item) => (object) $this->normalize((array) $item),
            $list
        );
    }
}
