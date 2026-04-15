<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Sms\SmsSender;
use Espo\Entities\Sms;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

use Exception;

class SmsCompose
{
    public function __construct(
        private EntityManager $entityManager,
        private SmsSender $smsSender,
        private User $user,
    ) {
        if ($this->user->isPortal()) {
            throw new Forbidden("Portal users cannot send SMS.");
        }
    }

    /**
     * POST /api/v1/SmsCompose/send
     *
     * Body: { to, body, parentType?, parentId?, parentName? }
     * Returns: { id, status }
     *
     * @throws BadRequest
     * @throws Forbidden
     * @throws Error
     */
    public function postActionSend(Request $request): object
    {
        $data = $request->getParsedBody();

        $to = $data->to ?? null;
        $body = $data->body ?? null;
        $parentType = $data->parentType ?? null;
        $parentId = $data->parentId ?? null;
        $parentName = $data->parentName ?? null;

        if (!$to || !is_string($to)) {
            throw new BadRequest("Missing 'to' phone number.");
        }

        if (!$body || !is_string($body)) {
            throw new BadRequest("Missing 'body' message text.");
        }

        /** @var Sms $sms */
        $sms = $this->entityManager->getNewEntity(Sms::ENTITY_TYPE);

        $sms->set('to', trim($to));
        $sms->set('body', $body);
        $sms->set('status', Sms::STATUS_SENDING);

        if ($parentType && $parentId) {
            $sms->set('parentType', $parentType);
            $sms->set('parentId', $parentId);
            $sms->set('parentName', $parentName);
        }

        // Send first (SmsSender sets status → Sent and populates dateSent).
        // Numbers hook (beforeSave) will convert the 'to' string to phone number links.
        try {
            $this->smsSender->send($sms);
        } catch (Exception $e) {
            $sms->setStatus(Sms::STATUS_FAILED);

            $this->entityManager->saveEntity($sms);

            throw new Error("SMS send failed: " . $e->getMessage());
        }

        $this->entityManager->saveEntity($sms);

        return (object) [
            'id' => $sms->getId(),
            'status' => $sms->getStatus(),
        ];
    }
}
